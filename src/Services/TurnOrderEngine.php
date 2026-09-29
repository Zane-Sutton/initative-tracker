<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Turn-order engine with 5e tie-breaking.
 *
 * Tie-breaking rules:
 * 1. Initiative rolled total (higher wins)
 * 2. Initiative modifier/bonus (higher wins)
 * 3. Player character prioritized over monster/NPC
 * 4. Stable tie-break by ID
 */
final class TurnOrderEngine
{
    /**
     * Sort participants by D&D 5e initiative rules and assign sequential turn_order (1, 2, 3...).
     *
     * @param list<array<string,mixed>> $participants
     * @return list<array<string,mixed>> Sorted participants with updated 'turn_order' key.
     */
    public function sortParticipants(array $participants): array
    {
        $sorted = $participants;

        usort($sorted, function (array $a, array $b): int {
            // Null initiative gets placed at the end
            $initA = $a['initiative'] ?? null;
            $initB = $b['initiative'] ?? null;

            if ($initA === null && $initB === null) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            if ($initA === null) {
                return 1;
            }
            if ($initB === null) {
                return -1;
            }

            // 1. Initiative total (DESC)
            $initComp = ((int) $initB) <=> ((int) $initA);
            if ($initComp !== 0) {
                return $initComp;
            }

            // 2. Initiative bonus / modifier (DESC)
            $bonusA = (int) ($a['initiative_bonus'] ?? 0);
            $bonusB = (int) ($b['initiative_bonus'] ?? 0);
            $bonusComp = $bonusB <=> $bonusA;
            if ($bonusComp !== 0) {
                return $bonusComp;
            }

            // 3. Player Character priority (is player character?)
            $isPlayerA = !empty($a['character_id']) ? 1 : 0;
            $isPlayerB = !empty($b['character_id']) ? 1 : 0;
            $playerComp = $isPlayerB <=> $isPlayerA;
            if ($playerComp !== 0) {
                return $playerComp;
            }

            // 4. Stable tie-break by ID (ASC)
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        // Assign turn_order 1..N
        $order = 1;
        foreach ($sorted as &$p) {
            $p['turn_order'] = $order++;
        }
        unset($p);

        return $sorted;
    }

    /**
     * Advance to the next active turn.
     * If the round has ended, increments the round counter and loops to the first active combatant.
     *
     * @param list<array<string,mixed>> $participants
     * @return array{
     *     round: int,
     *     turn_order: ?int,
     *     participant_id: ?int
     * }
     */
    public function nextTurn(int $currentRound, ?int $currentTurnOrder, array $participants): array
    {
        $active = $this->getActiveOrderedParticipants($participants);
        if ($active === []) {
            return [
                'round' => max(1, $currentRound),
                'turn_order' => null,
                'participant_id' => null,
            ];
        }

        // If not started yet, start at round 1 and first combatant
        if ($currentRound < 1 || $currentTurnOrder === null) {
            return [
                'round' => 1,
                'turn_order' => (int) $active[0]['turn_order'],
                'participant_id' => (int) $active[0]['id'],
            ];
        }

        // Look for next active participant with turn_order > currentTurnOrder
        foreach ($active as $p) {
            if ((int) $p['turn_order'] > $currentTurnOrder) {
                return [
                    'round' => $currentRound,
                    'turn_order' => (int) $p['turn_order'],
                    'participant_id' => (int) $p['id'],
                ];
            }
        }

        // Loop back to the first active participant in a new round
        return [
            'round' => $currentRound + 1,
            'turn_order' => (int) $active[0]['turn_order'],
            'participant_id' => (int) $active[0]['id'],
        ];
    }

    /**
     * Move back to previous active turn.
     *
     * @param list<array<string,mixed>> $participants
     * @return array{
     *     round: int,
     *     turn_order: ?int,
     *     participant_id: ?int
     * }
     */
    public function prevTurn(int $currentRound, ?int $currentTurnOrder, array $participants): array
    {
        $active = $this->getActiveOrderedParticipants($participants);
        if ($active === []) {
            return [
                'round' => max(1, $currentRound),
                'turn_order' => null,
                'participant_id' => null,
            ];
        }

        if ($currentRound <= 1 && ($currentTurnOrder === null || $currentTurnOrder <= (int) $active[0]['turn_order'])) {
            // Already at the first turn of round 1
            return [
                'round' => 1,
                'turn_order' => (int) $active[0]['turn_order'],
                'participant_id' => (int) $active[0]['id'],
            ];
        }

        // Find active participants with turn_order < currentTurnOrder
        $prev = null;
        foreach ($active as $p) {
            if ($currentTurnOrder === null || (int) $p['turn_order'] < $currentTurnOrder) {
                $prev = $p;
            } else {
                break;
            }
        }

        if ($prev !== null) {
            return [
                'round' => max(1, $currentRound),
                'turn_order' => (int) $prev['turn_order'],
                'participant_id' => (int) $prev['id'],
            ];
        }

        // If no prior combatant in this round, go to the last combatant of the previous round
        $last = end($active);
        return [
            'round' => max(1, $currentRound - 1),
            'turn_order' => (int) $last['turn_order'],
            'participant_id' => (int) $last['id'],
        ];
    }

    /**
     * Start combat: sets round 1 and first active combatant.
     *
     * @param list<array<string,mixed>> $participants
     * @return array{
     *     round: int,
     *     turn_order: ?int,
     *     participant_id: ?int
     * }
     */
    public function startCombat(array $participants): array
    {
        $active = $this->getActiveOrderedParticipants($participants);
        if ($active === []) {
            return [
                'round' => 1,
                'turn_order' => null,
                'participant_id' => null,
            ];
        }

        return [
            'round' => 1,
            'turn_order' => (int) $active[0]['turn_order'],
            'participant_id' => (int) $active[0]['id'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $participants
     * @return list<array<string,mixed>>
     */
    private function getActiveOrderedParticipants(array $participants): array
    {
        $active = array_values(array_filter($participants, static function (array $p): bool {
            return !empty($p['is_active']) && ($p['turn_order'] ?? null) !== null;
        }));

        usort($active, static fn (array $a, array $b): int => ((int) $a['turn_order']) <=> ((int) $b['turn_order']));

        return $active;
    }
}
