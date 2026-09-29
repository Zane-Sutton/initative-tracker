<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ParticipantRepository extends Repository
{
    protected function table(): string
    {
        return 'encounter_participants';
    }

    protected function columns(): array
    {
        return [
            'encounter_id',
            'character_id',
            'monster_id',
            'display_name',
            'initiative',
            'initiative_bonus',
            'turn_order',
            'armor_class',
            'max_hp',
            'current_hp',
            'temp_hp',
            'is_active',
            'notes',
        ];
    }

    /**
     * Get all participants in an encounter with their active conditions.
     *
     * @return list<array<string,mixed>>
     */
    public function findByEncounter(int $encounterId): array
    {
        $sql = 'SELECT p.*,
                       c.player_name as character_player_name,
                       c.class as character_class,
                       m.creature_type as monster_type,
                       m.challenge_rating as monster_cr,
                       m.hp_formula as monster_hp_formula
                FROM encounter_participants p
                LEFT JOIN characters c ON c.id = p.character_id
                LEFT JOIN monsters m ON m.id = p.monster_id
                WHERE p.encounter_id = :encounter_id
                ORDER BY p.turn_order IS NULL ASC, p.turn_order ASC, p.initiative DESC, p.id ASC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $participants = $stmt->fetchAll();

        if ($participants === []) {
            return [];
        }

        $conditionsMap = $this->getConditionsForEncounter($encounterId);
        foreach ($participants as &$p) {
            $p['conditions'] = $conditionsMap[(int) $p['id']] ?? [];
        }
        unset($p);

        return $participants;
    }

    /**
     * Load conditions for all participants in an encounter.
     *
     * @return array<int, list<array{id: int, name: string, applied_round: ?int}>>
     */
    public function getConditionsForEncounter(int $encounterId): array
    {
        $sql = 'SELECT pc.participant_id, c.id, c.name, pc.applied_round
                FROM participant_conditions pc
                JOIN conditions c ON c.id = pc.condition_id
                JOIN encounter_participants p ON p.id = pc.participant_id
                WHERE p.encounter_id = :encounter_id
                ORDER BY c.name ASC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $pId = (int) $row['participant_id'];
            $map[$pId][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'applied_round' => $row['applied_round'] !== null ? (int) $row['applied_round'] : null,
            ];
        }

        return $map;
    }

    /**
     * Add a condition to a participant.
     */
    public function addCondition(int $participantId, int $conditionId, ?int $appliedRound = null): void
    {
        $sql = 'INSERT IGNORE INTO participant_conditions (participant_id, condition_id, applied_round) 
                VALUES (:p_id, :c_id, :round)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            'p_id' => $participantId,
            'c_id' => $conditionId,
            'round' => $appliedRound,
        ]);
    }

    /**
     * Remove a condition from a participant.
     */
    public function removeCondition(int $participantId, int $conditionId): void
    {
        $sql = 'DELETE FROM participant_conditions 
                WHERE participant_id = :p_id AND condition_id = :c_id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            'p_id' => $participantId,
            'c_id' => $conditionId,
        ]);
    }

    /**
     * Toggle a condition on a participant.
     *
     * @return bool True if added, false if removed.
     */
    public function toggleCondition(int $participantId, int $conditionId, ?int $appliedRound = null): bool
    {
        $check = 'SELECT 1 FROM participant_conditions WHERE participant_id = :p_id AND condition_id = :c_id';
        $stmt = $this->pdo()->prepare($check);
        $stmt->execute(['p_id' => $participantId, 'c_id' => $conditionId]);
        if ($stmt->fetch()) {
            $this->removeCondition($participantId, $conditionId);
            return false;
        }

        $this->addCondition($participantId, $conditionId, $appliedRound);
        return true;
    }

    /**
     * Update turn orders for a list of participants [participantId => turnOrder].
     *
     * @param array<int, int> $orders
     */
    public function updateTurnOrders(array $orders): void
    {
        if ($orders === []) {
            return;
        }

        $stmt = $this->pdo()->prepare('UPDATE encounter_participants SET turn_order = :turn_order WHERE id = :id');
        foreach ($orders as $id => $order) {
            $stmt->execute([
                'id' => $id,
                'turn_order' => $order,
            ]);
        }
    }

    /**
     * Get existing display names starting with a base name in this encounter (to help with Goblin 1, Goblin 2 auto-naming).
     *
     * @return list<string>
     */
    public function getExistingNames(int $encounterId, string $baseName): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT display_name FROM encounter_participants 
             WHERE encounter_id = :encounter_id AND display_name LIKE :prefix'
        );
        $stmt->execute([
            'encounter_id' => $encounterId,
            'prefix' => addcslashes($baseName, '%_\\') . '%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
