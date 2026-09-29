<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Minimal table repository. Subclasses declare the table name and the list of
 * writable columns; both are code constants, never user input, so they are
 * safe to interpolate into SQL. All values go through prepared statements.
 */
abstract class Repository
{
    abstract protected function table(): string;

    /** @return list<string> */
    abstract protected function columns(): array;

    /** @return list<array<string,mixed>> */
    public function all(?string $search = null): array
    {
        $sql = sprintf('SELECT * FROM `%s`', $this->table());
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= ' WHERE name LIKE :q';
            $params['q'] = '%' . addcslashes($search, '%_\\') . '%';
        }

        $sql .= ' ORDER BY name ASC, id ASC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(sprintf('SELECT * FROM `%s` WHERE id = :id', $this->table()));
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $columns = $this->columns();

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table(),
            implode(', ', array_map(static fn (string $c): string => "`$c`", $columns)),
            implode(', ', array_map(static fn (string $c): string => ":$c", $columns))
        );

        $this->pdo()->prepare($sql)->execute($this->params($data));

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn (string $c): string => "`$c` = :$c", $this->columns()));
        $sql = sprintf('UPDATE `%s` SET %s WHERE id = :row_id', $this->table(), $sets);

        $params = $this->params($data);
        $params['row_id'] = $id;

        $this->pdo()->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo()->prepare(sprintf('DELETE FROM `%s` WHERE id = :id', $this->table()));
        $stmt->execute(['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo()->prepare(
            sprintf('SELECT * FROM `%s` WHERE name = :name ORDER BY id ASC LIMIT 1', $this->table())
        );
        $stmt->execute(['name' => $name]);

        return $stmt->fetch() ?: null;
    }

    /** Connect lazily so constructing a repository never touches the database. */
    protected function pdo(): PDO
    {
        return Database::connection();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function params(array $data): array
    {
        $params = [];
        foreach ($this->columns() as $column) {
            $params[$column] = $data[$column] ?? null;
        }

        return $params;
    }
}
