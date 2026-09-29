<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ConditionRepository extends Repository
{
    protected function table(): string
    {
        return 'conditions';
    }

    protected function columns(): array
    {
        return ['name', 'description'];
    }

    /**
     * @return list<array{id: int, name: string, description: ?string}>
     */
    public function allOrdered(): array
    {
        $stmt = $this->pdo()->query('SELECT * FROM conditions ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
