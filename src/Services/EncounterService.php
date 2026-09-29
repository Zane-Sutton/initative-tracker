<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CharacterRepository;
use App\Repositories\ConditionRepository;
use App\Repositories\EncounterRepository;
use App\Repositories\MonsterRepository;
use App\Repositories\ParticipantRepository;

final class EncounterService
{
    public function __construct(
        private readonly EncounterRepository $encounters = new EncounterRepository(),
        private readonly ParticipantRepository $participants = new ParticipantRepository(),
        private readonly CharacterRepository $characters = new CharacterRepository(),
        private readonly MonsterRepository $monsters = new MonsterRepository(),
        private readonly ConditionRepository $conditions = new ConditionRepository(),
        private readonly DiceRoller $diceRoller = new DiceRoller(),
        private readonly HpCalculator $hpCalculator = new HpCalculator(),
        private readonly TurnOrderEngine $turnOrderEngine = new TurnOrderEngine()
    ) {
    }

    /**
     * Get an encounter along with all its participants and active conditions.
     *
     * @return array{encounter: array<string,mixed>, participants: list<array<string,mixed>>}|null
     */
    public function getEncounterWithParticipants(int $id): ?array
    {
        $encounter = $this->encounters->find($id);
        if ($encounter === null) {
            return null;
        }

        $participants = $this->participants->findByEncounter($id);

        return [
            'encounter' => $encounter,
            'participants' => $participants,
        ];
    }

    /**
     * Add a player character snapshot to an encounter.
     */
    public function addCharacter(int $encounterId, int $characterId): int
    {
        $char = $this->characters->find($characterId);
        if ($char === null) {
            throw new \InvalidArgumentException("Character not found.");
        }

        $maxHp = (int) $char['max_hp'];
        $participantData = [
            'encounter_id' => $encounterId,
            'character_id' => $char['id'],
            'monster_id' => null,
            'display_name' => $char['name'],
            'initiative' => null,
            'initiative_bonus' => (int) $char['initiative_bonus'],
            'turn_order' => null,
            'armor_class' => (int) $char['armor_class'],
            'max_hp' => $maxHp,
            'current_hp' => $maxHp,
            'temp_hp' => 0,
            'is_active' => 1,
            'notes' => $char['notes'] ?? null,
        ];

        return $this->participants->create($participantData);
    }

    /**
     * Add one or more monster snapshots to an encounter with optional HP & Initiative rolling and auto-numbering.
     *
     * @return list<int> Created participant IDs
     */
    public function addMonster(
        int $encounterId,
        int $monsterId,
        int $quantity = 1,
        bool $rollHp = false,
        bool $rollInitiative = false,
        ?string $customName = null
    ): array
    {
        $monster = $this->monsters->find($monsterId);
        if ($monster === null) {
            throw new \InvalidArgumentException("Monster not found.");
        }

        $quantity = max(1, min(100, $quantity));
        $baseName = $customName !== null && trim($customName) !== '' ? trim($customName) : $monster['name'];

        // Determine existing numbered instances to avoid collisions (e.g. "Goblin 1", "Goblin 2")
        $existingNames = $this->participants->getExistingNames($encounterId, $baseName);
        $nextNumber = 1;
        $hasUnnumberedBase = false;

        foreach ($existingNames as $name) {
            if ($name === $baseName) {
                $hasUnnumberedBase = true;
            }
            if (preg_match('/^' . preg_quote($baseName, '/') . '\s+(\d+)$/i', $name, $m)) {
                $num = (int) $m[1];
                if ($num >= $nextNumber) {
                    $nextNumber = $num + 1;
                }
            }
        }

        $createdIds = [];
        for ($i = 0; $i < $quantity; $i++) {
            $displayName = $baseName;
            if ($quantity > 1 || $nextNumber > 1 || $hasUnnumberedBase) {
                $displayName = $baseName . ' ' . ($nextNumber + $i);
            }

            $hp = $this->hpCalculator->calculateStartingHp(
                (int) $monster['hp_average'],
                $monster['hp_formula'],
                $rollHp,
                $this->diceRoller
            );

            $init = null;
            if ($rollInitiative) {
                $init = $this->diceRoller->rollInitiative((int) $monster['initiative_bonus'])['total'];
            }

            $participantData = [
                'encounter_id' => $encounterId,
                'character_id' => null,
                'monster_id' => $monster['id'],
                'display_name' => $displayName,
                'initiative' => $init,
                'initiative_bonus' => (int) $monster['initiative_bonus'],
                'turn_order' => null,
                'armor_class' => (int) $monster['armor_class'],
                'max_hp' => $hp,
                'current_hp' => $hp,
                'temp_hp' => 0,
                'is_active' => 1,
                'notes' => null,
            ];

            $createdIds[] = $this->participants->create($participantData);
        }

        return $createdIds;
    }

    /**
     * Add a custom/ad-hoc participant to the encounter.
     *
     * @param array<string,mixed> $input
     */
    public function addCustomParticipant(int $encounterId, array $input): int
    {
        $maxHp = max(1, (int) ($input['max_hp'] ?? 10));
        $currentHp = isset($input['current_hp']) && $input['current_hp'] !== '' ? (int) $input['current_hp'] : $maxHp;

        $participantData = [
            'encounter_id' => $encounterId,
            'character_id' => null,
            'monster_id' => null,
            'display_name' => trim((string) ($input['display_name'] ?? 'Custom Combatant')),
            'initiative' => isset($input['initiative']) && $input['initiative'] !== '' ? (int) $input['initiative'] : null,
            'initiative_bonus' => (int) ($input['initiative_bonus'] ?? 0),
            'turn_order' => null,
            'armor_class' => (int) ($input['armor_class'] ?? 10),
            'max_hp' => $maxHp,
            'current_hp' => $currentHp,
            'temp_hp' => (int) ($input['temp_hp'] ?? 0),
            'is_active' => 1,
            'notes' => !empty($input['notes']) ? trim((string) $input['notes']) : null,
        ];

        return $this->participants->create($participantData);
    }

    /**
     * Update participant snapshot fields inline.
     * Note: This strictly updates ONLY the combatant row in encounter_participants,
     * leaving character and monster templates untouched.
     *
     * @param array<string,mixed> $data
     */
    public function updateParticipant(int $participantId, array $data): array
    {
        $p = $this->participants->find($participantId);
        if ($p === null) {
            throw new \InvalidArgumentException("Combatant not found.");
        }

        $fields = [
            'display_name' => isset($data['display_name']) ? trim((string) $data['display_name']) : $p['display_name'],
            'armor_class' => isset($data['armor_class']) ? (int) $data['armor_class'] : (int) $p['armor_class'],
            'max_hp' => isset($data['max_hp']) ? max(1, (int) $data['max_hp']) : (int) $p['max_hp'],
            'current_hp' => isset($data['current_hp']) ? max(0, (int) $data['current_hp']) : (int) $p['current_hp'],
            'temp_hp' => isset($data['temp_hp']) ? max(0, (int) $data['temp_hp']) : (int) $p['temp_hp'],
            'initiative' => array_key_exists('initiative', $data)
                ? ($data['initiative'] !== null && $data['initiative'] !== '' ? (int) $data['initiative'] : null)
                : $p['initiative'],
            'initiative_bonus' => isset($data['initiative_bonus']) ? (int) $data['initiative_bonus'] : (int) $p['initiative_bonus'],
            'turn_order' => array_key_exists('turn_order', $data)
                ? ($data['turn_order'] !== null && $data['turn_order'] !== '' ? (int) $data['turn_order'] : null)
                : $p['turn_order'],
            'is_active' => isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : (int) $p['is_active'],
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $p['notes'],
            'encounter_id' => $p['encounter_id'],
            'character_id' => $p['character_id'],
            'monster_id' => $p['monster_id'],
        ];

        $this->participants->update($participantId, $fields);

        return $this->participants->find($participantId);
    }

    /**
     * Apply HP modifications (damage, healing, temporary HP, direct set).
     *
     * @return array<string,mixed> Updated combatant info and calculation result
     */
    public function applyHpChange(int $participantId, string $action, int $amount): array
    {
        $p = $this->participants->find($participantId);
        if ($p === null) {
            throw new \InvalidArgumentException("Combatant not found.");
        }

        $currentHp = (int) $p['current_hp'];
        $tempHp = (int) $p['temp_hp'];
        $maxHp = (int) $p['max_hp'];
        $result = [];

        switch ($action) {
            case 'damage':
                $calc = $this->hpCalculator->applyDamage($currentHp, $tempHp, $amount);
                $this->participants->update($participantId, array_merge($p, [
                    'current_hp' => $calc['current_hp'],
                    'temp_hp' => $calc['temp_hp'],
                ]));
                $result = $calc;
                break;

            case 'heal':
                $calc = $this->hpCalculator->applyHealing($currentHp, $maxHp, $amount);
                $this->participants->update($participantId, array_merge($p, [
                    'current_hp' => $calc['current_hp'],
                ]));
                $result = $calc;
                break;

            case 'temp':
                $calc = $this->hpCalculator->applyTempHp($tempHp, $amount);
                $this->participants->update($participantId, array_merge($p, [
                    'temp_hp' => $calc['temp_hp'],
                ]));
                $result = $calc;
                break;

            case 'set_hp':
                $newHp = max(0, min($maxHp, $amount));
                $this->participants->update($participantId, array_merge($p, [
                    'current_hp' => $newHp,
                ]));
                $result = ['current_hp' => $newHp];
                break;

            case 'set_max_hp':
                $newMax = max(1, $amount);
                $newHp = min($newMax, $currentHp);
                $this->participants->update($participantId, array_merge($p, [
                    'max_hp' => $newMax,
                    'current_hp' => $newHp,
                ]));
                $result = ['max_hp' => $newMax, 'current_hp' => $newHp];
                break;

            default:
                throw new \InvalidArgumentException("Unknown HP action: '$action'");
        }

        $updated = $this->participants->find($participantId);

        return [
            'participant' => $updated,
            'calculation' => $result,
        ];
    }

    /**
     * Roll initiative for participants in an encounter and update turn order.
     */
    public function rollInitiativeForEncounter(
        int $encounterId,
        bool $monstersOnly = false,
        bool $overwriteExisting = false
    ): void {
        $participants = $this->participants->findByEncounter($encounterId);

        foreach ($participants as $p) {
            if ($monstersOnly && empty($p['monster_id'])) {
                continue;
            }

            if (!$overwriteExisting && $p['initiative'] !== null) {
                continue;
            }

            $bonus = (int) $p['initiative_bonus'];
            $rolled = $this->diceRoller->rollInitiative($bonus)['total'];

            $this->participants->update((int) $p['id'], array_merge($p, [
                'initiative' => $rolled,
            ]));
        }

        $this->recalculateTurnOrder($encounterId);
    }

    /**
     * Roll initiative for a single participant and update turn order.
     */
    public function rollParticipantInitiative(int $participantId): int
    {
        $p = $this->participants->find($participantId);
        if ($p === null) {
            throw new \InvalidArgumentException("Participant not found.");
        }

        $rolled = $this->diceRoller->rollInitiative((int) $p['initiative_bonus'])['total'];
        $this->participants->update($participantId, array_merge($p, [
            'initiative' => $rolled,
        ]));

        $this->recalculateTurnOrder((int) $p['encounter_id']);

        return $rolled;
    }

    /**
     * Recalculate and persist turn orders for all participants in an encounter based on 5e tie-breaking.
     */
    public function recalculateTurnOrder(int $encounterId): void
    {
        $participants = $this->participants->findByEncounter($encounterId);
        $sorted = $this->turnOrderEngine->sortParticipants($participants);

        $orderMap = [];
        foreach ($sorted as $p) {
            $orderMap[(int) $p['id']] = (int) $p['turn_order'];
        }

        $this->participants->updateTurnOrders($orderMap);
    }

    /**
     * Start encounter combat.
     *
     * @return array{encounter: array<string,mixed>, participants: list<array<string,mixed>>}
     */
    public function startCombat(int $encounterId): array
    {
        $this->recalculateTurnOrder($encounterId);
        $encounterData = $this->getEncounterWithParticipants($encounterId);
        if ($encounterData === null) {
            throw new \InvalidArgumentException("Encounter not found.");
        }

        $turnState = $this->turnOrderEngine->startCombat($encounterData['participants']);
        $this->encounters->updateProgress(
            $encounterId,
            'active',
            $turnState['round'],
            $turnState['turn_order']
        );

        return $this->getEncounterWithParticipants($encounterId);
    }

    /**
     * Advance to the next turn in the encounter.
     *
     * @return array{encounter: array<string,mixed>, participants: list<array<string,mixed>>}
     */
    public function nextTurn(int $encounterId): array
    {
        $data = $this->getEncounterWithParticipants($encounterId);
        if ($data === null) {
            throw new \InvalidArgumentException("Encounter not found.");
        }

        $encounter = $data['encounter'];
        $turnState = $this->turnOrderEngine->nextTurn(
            (int) $encounter['current_round'],
            $encounter['current_turn_order'] !== null ? (int) $encounter['current_turn_order'] : null,
            $data['participants']
        );

        $this->encounters->updateProgress(
            $encounterId,
            'active',
            $turnState['round'],
            $turnState['turn_order']
        );

        return $this->getEncounterWithParticipants($encounterId);
    }

    /**
     * Go back to previous turn in the encounter.
     *
     * @return array{encounter: array<string,mixed>, participants: list<array<string,mixed>>}
     */
    public function prevTurn(int $encounterId): array
    {
        $data = $this->getEncounterWithParticipants($encounterId);
        if ($data === null) {
            throw new \InvalidArgumentException("Encounter not found.");
        }

        $encounter = $data['encounter'];
        $turnState = $this->turnOrderEngine->prevTurn(
            (int) $encounter['current_round'],
            $encounter['current_turn_order'] !== null ? (int) $encounter['current_turn_order'] : null,
            $data['participants']
        );

        $this->encounters->updateProgress(
            $encounterId,
            'active',
            $turnState['round'],
            $turnState['turn_order']
        );

        return $this->getEncounterWithParticipants($encounterId);
    }

    /**
     * Reset combat status back to planned.
     */
    public function resetCombat(int $encounterId): void
    {
        $this->encounters->updateProgress($encounterId, 'planned', 0, null);
    }

    /**
     * Mark combat finished.
     */
    public function finishCombat(int $encounterId): void
    {
        $encounter = $this->encounters->find($encounterId);
        if ($encounter !== null) {
            $this->encounters->updateProgress(
                $encounterId,
                'finished',
                (int) $encounter['current_round'],
                $encounter['current_turn_order'] !== null ? (int) $encounter['current_turn_order'] : null
            );
        }
    }

    /**
     * Toggle condition on participant.
     */
    public function toggleCondition(int $participantId, int $conditionId): bool
    {
        $p = $this->participants->find($participantId);
        if ($p === null) {
            throw new \InvalidArgumentException("Participant not found.");
        }

        $enc = $this->encounters->find((int) $p['encounter_id']);
        $round = $enc ? (int) $enc['current_round'] : null;

        return $this->participants->toggleCondition($participantId, $conditionId, $round);
    }

    /**
     * Toggle participant active status (alive/active vs down/removed).
     */
    public function toggleActive(int $participantId): bool
    {
        $p = $this->participants->find($participantId);
        if ($p === null) {
            throw new \InvalidArgumentException("Participant not found.");
        }

        $newActive = empty($p['is_active']) ? 1 : 0;
        $this->participants->update($participantId, array_merge($p, ['is_active' => $newActive]));

        return $newActive === 1;
    }

    /**
     * Delete participant from encounter.
     */
    public function deleteParticipant(int $participantId): void
    {
        $p = $this->participants->find($participantId);
        if ($p !== null) {
            $this->participants->delete($participantId);
            $this->recalculateTurnOrder((int) $p['encounter_id']);
        }
    }
}
