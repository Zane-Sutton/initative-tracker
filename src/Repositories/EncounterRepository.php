<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EncounterRepository extends Repository
{
    protected function table(): string
    {
        return 'encounters';
    }

    protected function columns(): array
    {
        return [
            'campaign_id',
            'name',
            'status',
            'current_round',
            'current_turn_order',
            'notes',
        ];
    }

    /**
     * Get all encounters with their participant counts and active status.
     *
     * @return list<array<string,mixed>>
     */
    public function allWithCounts(?string $search = null): array
    {
        $sql = 'SELECT e.*, 
                       COUNT(p.id) as participant_count,
                       SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END) as active_count
                FROM encounters e
                LEFT JOIN encounter_participants p ON p.encounter_id = e.id';

        $params = [];
        if ($search !== null && $search !== '') {
            $sql .= ' WHERE e.name LIKE :q';
            $params['q'] = '%' . addcslashes($search, '%_\\') . '%';
        }

        $sql .= ' GROUP BY e.id ORDER BY (e.status = "active") DESC, e.updated_at DESC, e.id DESC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Update encounter round, turn order, and status.
     */
    public function updateProgress(int $id, string $status, int $currentRound, ?int $currentTurnOrder): void
    {
        $sql = 'UPDATE encounters 
                SET status = :status, current_round = :round, current_turn_order = :turn_order 
                WHERE id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'round' => $currentRound,
            'turn_order' => $currentTurnOrder,
        ]);
    }
}
