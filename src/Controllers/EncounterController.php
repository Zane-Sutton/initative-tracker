<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CharacterRepository;
use App\Repositories\ConditionRepository;
use App\Repositories\EncounterRepository;
use App\Repositories\MonsterRepository;
use App\Repositories\ParticipantRepository;
use App\Services\DiceRoller;
use App\Services\EncounterService;
use App\Validator;
use App\View;

use function App\abort;
use function App\flash;
use function App\redirect;
use function App\verify_csrf;

final class EncounterController
{
    private const DEFAULTS = \App\Rules::ENCOUNTER_DEFAULTS;

    private EncounterRepository $encounters;
    private ParticipantRepository $participants;
    private CharacterRepository $characters;
    private MonsterRepository $monsters;
    private ConditionRepository $conditions;
    private EncounterService $encounterService;
    private DiceRoller $diceRoller;

    public function __construct()
    {
        $this->encounters = new EncounterRepository();
        $this->participants = new ParticipantRepository();
        $this->characters = new CharacterRepository();
        $this->monsters = new MonsterRepository();
        $this->conditions = new ConditionRepository();
        $this->diceRoller = new DiceRoller();
        $this->encounterService = new EncounterService(
            $this->encounters,
            $this->participants,
            $this->characters,
            $this->monsters,
            $this->conditions,
            $this->diceRoller
        );
    }

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));

        View::render('encounters/index', [
            'encounters' => $this->encounters->allWithCounts($q),
            'q' => $q,
        ]);
    }

    public function create(): void
    {
        $this->form('New encounter', '/encounters', self::DEFAULTS);
    }

    public function store(): void
    {
        verify_csrf();

        $validator = $this->validate($_POST);
        if ($validator->fails()) {
            $this->form('New encounter', '/encounters', $_POST, $validator->errors());
            return;
        }

        $id = $this->encounters->create(array_merge($validator->data(), [
            'status' => 'planned',
            'current_round' => 0,
            'current_turn_order' => null,
        ]));

        flash('Encounter created.');
        redirect('/encounters/' . $id);
    }

    public function show(string $id): void
    {
        $intId = $this->parseId($id);
        $data = $this->encounterService->getEncounterWithParticipants($intId);
        if ($data === null) {
            abort(404, 'Encounter not found.');
        }

        // Available characters & monsters for adding
        $allCharacters = $this->characters->all();
        $allMonsters = $this->monsters->all();
        $allConditions = $this->conditions->allOrdered();

        View::render('encounters/show', [
            'encounter' => $data['encounter'],
            'participants' => $data['participants'],
            'characters' => $allCharacters,
            'monsters' => $allMonsters,
            'conditions' => $allConditions,
        ]);
    }

    public function edit(string $id): void
    {
        $encounter = $this->findOrFail($id);
        $this->form('Edit ' . $encounter['name'], '/encounters/' . $encounter['id'], $encounter);
    }

    public function update(string $id): void
    {
        verify_csrf();
        $encounter = $this->findOrFail($id);

        $validator = $this->validate($_POST);
        if ($validator->fails()) {
            $this->form('Edit ' . $encounter['name'], '/encounters/' . $encounter['id'], $_POST, $validator->errors());
            return;
        }

        $this->encounters->update((int) $encounter['id'], array_merge($encounter, $validator->data()));
        flash('Encounter updated.');
        redirect('/encounters/' . $encounter['id']);
    }

    public function destroy(string $id): void
    {
        verify_csrf();
        $encounter = $this->findOrFail($id);

        $this->encounters->delete((int) $encounter['id']);
        flash('Deleted ' . $encounter['name'] . '.');
        redirect('/encounters');
    }

    public function start(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $this->encounterService->startCombat($intId);
        $this->respondOrRedirect($intId, 'Combat started! Round 1.');
    }

    public function nextTurn(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $this->encounterService->nextTurn($intId);
        $this->respondOrRedirect($intId, 'Advanced to next turn.');
    }

    public function prevTurn(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $this->encounterService->prevTurn($intId);
        $this->respondOrRedirect($intId, 'Returned to previous turn.');
    }

    public function reset(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $this->encounterService->resetCombat($intId);
        $this->respondOrRedirect($intId, 'Encounter reset to planned.');
    }

    public function finish(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $this->encounterService->finishCombat($intId);
        $this->respondOrRedirect($intId, 'Encounter marked as finished.');
    }

    public function rollAllInitiative(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $monstersOnly = !empty($_POST['monsters_only']);
        $overwrite = !empty($_POST['overwrite']);

        $this->encounterService->rollInitiativeForEncounter($intId, $monstersOnly, $overwrite);
        $this->respondOrRedirect($intId, 'Initiative rolled.');
    }

    public function sortTurnOrder(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $this->encounterService->recalculateTurnOrder($intId);
        $this->respondOrRedirect($intId, 'Turn order updated.');
    }

    public function addCharacter(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $characterId = filter_var($_POST['character_id'] ?? null, FILTER_VALIDATE_INT);

        if ($characterId === false || $characterId <= 0) {
            flash('Please select a character.', 'error');
            redirect('/encounters/' . $intId);
        }

        $this->encounterService->addCharacter($intId, $characterId);
        flash('Character added to encounter.');
        redirect('/encounters/' . $intId);
    }

    public function addMonster(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $monsterId = filter_var($_POST['monster_id'] ?? null, FILTER_VALIDATE_INT);

        if ($monsterId === false || $monsterId <= 0) {
            flash('Please select a monster.', 'error');
            redirect('/encounters/' . $intId);
        }

        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $rollHp = !empty($_POST['roll_hp']);
        $rollInit = !empty($_POST['roll_initiative']);
        $customName = !empty($_POST['custom_name']) ? trim((string) $_POST['custom_name']) : null;

        $created = $this->encounterService->addMonster($intId, $monsterId, $quantity, $rollHp, $rollInit, $customName);
        flash(count($created) > 1 ? count($created) . ' monsters added.' : 'Monster added.');
        redirect('/encounters/' . $intId);
    }

    public function addCustom(string $id): void
    {
        verify_csrf();
        $intId = $this->parseId($id);

        $validator = \App\Rules::customParticipant($_POST);
        if ($validator->fails()) {
            flash(implode(' ', $validator->errors()), 'error');
            redirect('/encounters/' . $intId);
        }

        $this->encounterService->addCustomParticipant($intId, $validator->data());
        flash('Custom combatant added.');
        redirect('/encounters/' . $intId);
    }

    /**
     * Inline update for a combatant snapshot.
     */
    public function updateParticipant(string $id, string $participantId): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $pId = $this->parseId($participantId);

        $data = $this->getJsonOrPost();
        $updated = $this->encounterService->updateParticipant($pId, $data);

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true, 'participant' => $updated]);
        }

        flash('Combatant updated.');
        redirect('/encounters/' . $intId);
    }

    /**
     * Quick damage / heal / temp HP endpoint.
     */
    public function adjustHp(string $id, string $participantId): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $pId = $this->parseId($participantId);

        $input = $this->getJsonOrPost();
        $action = (string) ($input['action'] ?? 'damage');
        $amount = (int) ($input['amount'] ?? 0);

        $result = $this->encounterService->applyHpChange($pId, $action, $amount);

        if ($this->isJsonRequest()) {
            $this->jsonResponse([
                'success' => true,
                'participant' => $result['participant'],
                'calculation' => $result['calculation'],
            ]);
        }

        redirect('/encounters/' . $intId);
    }

    public function rollParticipantInitiative(string $id, string $participantId): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $pId = $this->parseId($participantId);

        $total = $this->encounterService->rollParticipantInitiative($pId);

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true, 'initiative' => $total]);
        }

        flash("Rolled initiative: $total");
        redirect('/encounters/' . $intId);
    }

    public function toggleCondition(string $id, string $participantId): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $pId = $this->parseId($participantId);
        $input = $this->getJsonOrPost();
        $conditionId = (int) ($input['condition_id'] ?? 0);

        if ($conditionId <= 0) {
            abort(400, 'Invalid condition ID.');
        }

        $active = $this->encounterService->toggleCondition($pId, $conditionId);

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true, 'active' => $active]);
        }

        redirect('/encounters/' . $intId);
    }

    public function toggleActive(string $id, string $participantId): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $pId = $this->parseId($participantId);

        $active = $this->encounterService->toggleActive($pId);

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true, 'is_active' => $active]);
        }

        redirect('/encounters/' . $intId);
    }

    public function deleteParticipant(string $id, string $participantId): void
    {
        verify_csrf();
        $intId = $this->parseId($id);
        $pId = $this->parseId($participantId);

        $this->encounterService->deleteParticipant($pId);

        if ($this->isJsonRequest()) {
            $this->jsonResponse(['success' => true]);
        }

        flash('Combatant removed.');
        redirect('/encounters/' . $intId);
    }

    /**
     * Live dice roller API endpoint.
     */
    public function rollDice(): void
    {
        $expression = trim((string) ($_GET['dice'] ?? $_POST['dice'] ?? '1d20'));

        try {
            $result = $this->diceRoller->roll($expression);
            $this->jsonResponse(['success' => true, 'result' => $result]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    private function validate(array $input): Validator
    {
        return \App\Rules::encounter($input);
    }

    /**
     * @param array<string,mixed>  $values
     * @param array<string,string> $errors
     */
    private function form(string $title, string $action, array $values, array $errors = []): void
    {
        if ($errors !== []) {
            http_response_code(422);
        }

        View::render('encounters/form', [
            'title' => $title,
            'action' => $action,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function findOrFail(string $id): array
    {
        $intId = $this->parseId($id);
        $row = $this->encounters->find($intId);

        return $row ?? abort(404, 'Encounter not found.');
    }

    private function parseId(string $id): int
    {
        $intId = filter_var($id, FILTER_VALIDATE_INT);
        if ($intId === false || $intId <= 0) {
            abort(404, 'Invalid ID.');
        }

        return $intId;
    }

    private function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $xReq = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || strtolower($xReq) === 'xmlhttprequest';
    }

    /**
     * @return array<string,mixed>
     */
    private function getJsonOrPost(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return $_POST;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function respondOrRedirect(int $encounterId, string $flashMessage): void
    {
        if ($this->isJsonRequest()) {
            $data = $this->encounterService->getEncounterWithParticipants($encounterId);
            $this->jsonResponse(['success' => true, 'data' => $data]);
        }

        flash($flashMessage);
        redirect('/encounters/' . $encounterId);
    }
}
