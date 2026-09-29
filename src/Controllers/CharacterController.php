<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CharacterRepository;
use App\Validator;
use App\View;

use function App\abort;
use function App\flash;
use function App\redirect;
use function App\verify_csrf;

final class CharacterController
{
    private const DEFAULTS = \App\Rules::CHARACTER_DEFAULTS;

    private CharacterRepository $characters;

    public function __construct()
    {
        $this->characters = new CharacterRepository();
    }

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));

        View::render('characters/index', [
            'characters' => $this->characters->all($q),
            'q' => $q,
        ]);
    }

    public function create(): void
    {
        $this->form('New character', '/characters', self::DEFAULTS);
    }

    public function store(): void
    {
        verify_csrf();

        $validator = $this->validate($_POST);
        if ($validator->fails()) {
            $this->form('New character', '/characters', $_POST, $validator->errors());

            return;
        }

        $this->characters->create($validator->data());
        flash('Character created.');
        redirect('/characters');
    }

    public function edit(string $id): void
    {
        $character = $this->findOrFail($id);

        $this->form('Edit ' . $character['name'], '/characters/' . $character['id'], $character);
    }

    public function update(string $id): void
    {
        verify_csrf();
        $character = $this->findOrFail($id);

        $validator = $this->validate($_POST);
        if ($validator->fails()) {
            $this->form('Edit ' . $character['name'], '/characters/' . $character['id'], $_POST, $validator->errors());

            return;
        }

        $this->characters->update((int) $character['id'], $validator->data());
        flash('Character updated.');
        redirect('/characters');
    }

    public function destroy(string $id): void
    {
        verify_csrf();
        $character = $this->findOrFail($id);

        $this->characters->delete((int) $character['id']);
        flash('Deleted ' . $character['name'] . '.');
        redirect('/characters');
    }

    /** @param array<string,mixed> $input */
    private function validate(array $input): Validator
    {
        return \App\Rules::character($input);
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

        View::render('characters/form', [
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
        $row = $intId === false ? null : $this->characters->find($intId);

        return $row ?? abort(404, 'Character not found.');
    }
}
