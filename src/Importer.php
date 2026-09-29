<?php

declare(strict_types=1);

namespace App;

use App\Repositories\CharacterRepository;
use App\Repositories\MonsterRepository;
use App\Repositories\Repository;

/**
 * Imports characters and monsters from a JSON document:
 *
 *   { "characters": [ {...}, ... ], "monsters": [ {...}, ... ] }
 *
 * Everything is validated first; if any entry is invalid nothing is written.
 * Writes happen in a single transaction. Entries are matched to existing rows
 * by name: they are skipped, or overwritten when $updateExisting is true.
 */
final class Importer
{
    private const MAX_ENTRIES = 1000;
    private const SECTIONS = ['characters', 'monsters'];

    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly MonsterRepository $monsters,
    ) {
    }

    /**
     * @return array{
     *     characters: array{created:int,updated:int,skipped:int},
     *     monsters: array{created:int,updated:int,skipped:int},
     *     errors: list<string>
     * }
     */
    public function import(string $json, bool $updateExisting = false): array
    {
        $result = [
            'characters' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'monsters' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'errors' => [],
        ];

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result['errors'][] = 'Invalid JSON: ' . $e->getMessage();

            return $result;
        }

        if (!is_array($data) || array_is_list($data)) {
            $result['errors'][] = 'The top level must be an object with "characters" and/or "monsters" lists.';

            return $result;
        }

        foreach (array_diff(array_keys($data), self::SECTIONS) as $key) {
            $result['errors'][] = sprintf('Unknown top-level key "%s" (expected "characters" and/or "monsters").', $key);
        }
        if ($result['errors'] === [] && !array_key_exists('characters', $data) && !array_key_exists('monsters', $data)) {
            $result['errors'][] = 'Nothing to import: add a "characters" and/or "monsters" list.';
        }

        $valid = ['characters' => [], 'monsters' => []];

        foreach (self::SECTIONS as $section) {
            if (!array_key_exists($section, $data)) {
                continue;
            }

            $entries = $data[$section];
            if (!is_array($entries) || !array_is_list($entries)) {
                $result['errors'][] = sprintf('"%s" must be a list of objects.', $section);
                continue;
            }
            if (count($entries) > self::MAX_ENTRIES) {
                $result['errors'][] = sprintf('"%s" has more than %d entries.', $section, self::MAX_ENTRIES);
                continue;
            }

            foreach ($entries as $index => $entry) {
                $label = sprintf('%s[%d]', $section, $index);

                if (!is_array($entry) || array_is_list($entry)) {
                    $result['errors'][] = "$label: must be an object.";
                    continue;
                }

                $validator = $section === 'characters'
                    ? Rules::character($this->prepare($entry, Rules::CHARACTER_DEFAULTS))
                    : Rules::monster($this->prepare($entry, Rules::MONSTER_DEFAULTS));

                if ($validator->fails()) {
                    $name = is_string($entry['name'] ?? null) ? ' ("' . mb_substr($entry['name'], 0, 50) . '")' : '';
                    foreach ($validator->errors() as $message) {
                        $result['errors'][] = $label . $name . ': ' . $message;
                    }
                    continue;
                }

                $valid[$section][] = $validator->data();
            }
        }

        if ($result['errors'] !== []) {
            return $result;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->apply($this->characters, $valid['characters'], $updateExisting, $result['characters']);
            $this->apply($this->monsters, $valid['monsters'], $updateExisting, $result['monsters']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * Fill in defaults for absent keys and flatten structured values into the
     * text the forms use: "stats" objects become JSON text, "actions" lists
     * become one action per line.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    private function prepare(array $entry, array $defaults): array
    {
        $entry += $defaults;

        if (isset($entry['stats']) && is_array($entry['stats'])) {
            $entry['stats'] = json_encode($entry['stats'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (isset($entry['actions']) && is_array($entry['actions'])) {
            $entry['actions'] = implode("\n", array_filter($entry['actions'], 'is_scalar'));
        }

        return $entry;
    }

    /**
     * @param list<array<string,mixed>>                    $rows
     * @param array{created:int,updated:int,skipped:int}   $counts
     */
    private function apply(Repository $repo, array $rows, bool $updateExisting, array &$counts): void
    {
        foreach ($rows as $data) {
            $existing = $repo->findByName((string) $data['name']);

            if ($existing === null) {
                $repo->create($data);
                $counts['created']++;
            } elseif ($updateExisting) {
                $repo->update((int) $existing['id'], $data);
                $counts['updated']++;
            } else {
                $counts['skipped']++;
            }
        }
    }
}
