<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\MonsterRepository;
use App\Validator;
use App\View;

use function App\abort;
use function App\flash;
use function App\pretty_json;
use function App\redirect;
use function App\verify_csrf;

final class MonsterController
{
    private const DEFAULTS = \App\Rules::MONSTER_DEFAULTS;

    private MonsterRepository $monsters;

    public function __construct()
    {
        $this->monsters = new MonsterRepository();
    }

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));

        View::render('monsters/index', [
            'monsters' => $this->monsters->all($q),
            'q' => $q,
        ]);
    }

    public function show(string $id): void
    {
        View::render('monsters/show', ['monster' => $this->findOrFail($id)]);
    }

    public function create(): void
    {
        $this->form('New monster', '/monsters', self::DEFAULTS);
    }

    public function store(): void
    {
        verify_csrf();

        $validator = $this->validate($_POST);
        if ($validator->fails()) {
            $this->form('New monster', '/monsters', $_POST, $validator->errors());

            return;
        }

        $id = $this->monsters->create($validator->data());
        flash('Monster created.');
        redirect('/monsters/' . $id);
    }

    public function edit(string $id): void
    {
        $monster = $this->findOrFail($id);
        $monster['stats'] = pretty_json($monster['stats']);

        $this->form('Edit ' . $monster['name'], '/monsters/' . $monster['id'], $monster);
    }

    public function update(string $id): void
    {
        verify_csrf();
        $monster = $this->findOrFail($id);

        $validator = $this->validate($_POST);
        if ($validator->fails()) {
            $this->form('Edit ' . $monster['name'], '/monsters/' . $monster['id'], $_POST, $validator->errors());

            return;
        }

        $this->monsters->update((int) $monster['id'], $validator->data());
        flash('Monster updated.');
        redirect('/monsters/' . $monster['id']);
    }

    public function destroy(string $id): void
    {
        verify_csrf();
        $monster = $this->findOrFail($id);

        $this->monsters->delete((int) $monster['id']);
        flash('Deleted ' . $monster['name'] . '.');
        redirect('/monsters');
    }

    /** @param array<string,mixed> $input */
    private function validate(array $input): Validator
    {
        return \App\Rules::monster($input);
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

        View::render('monsters/form', [
            'title' => $title,
            'action' => $action,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    /** @return array<string,mixed> */
    private function findOrFail(string $id): array
    {
        $intId = filter_var($id, FILTER_VALIDATE_INT);
        $row = $intId === false ? null : $this->monsters->find($intId);

        return $row ?? abort(404, 'Monster not found.');
    }
}
