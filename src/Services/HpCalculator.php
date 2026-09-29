<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 5e-compliant HP Calculator.
 *
 * Rules:
 * - Damage reduces temporary HP first, remaining damage reduces current HP.
 * - Current HP cannot drop below 0.
 * - Healing increases current HP up to max HP. Healing does not restore temporary HP.
 * - Temporary HP does not stack; by default taking new temp HP keeps the higher value.
 */
final class HpCalculator
{
    /**
     * Apply damage to current HP and temporary HP.
     *
     * @return array{
     *     current_hp: int,
     *     temp_hp: int,
     *     damage_taken: int,
     *     temp_absorbed: int,
     *     hp_lost: int,
     *     is_down: bool
     * }
     */
    public function applyDamage(int $currentHp, int $tempHp, int $damage): array
    {
        $damage = max(0, $damage);
        $tempAbsorbed = min($tempHp, $damage);
        $newTempHp = $tempHp - $tempAbsorbed;

        $remainingDamage = $damage - $tempAbsorbed;
        $actualHpLost = min($currentHp, $remainingDamage);
        $newCurrentHp = max(0, $currentHp - $remainingDamage);

        return [
            'current_hp' => $newCurrentHp,
            'temp_hp' => $newTempHp,
            'damage_taken' => $damage,
            'temp_absorbed' => $tempAbsorbed,
            'hp_lost' => $actualHpLost,
            'is_down' => ($newCurrentHp === 0),
        ];
    }

    /**
     * Apply healing to current HP (capped at max HP).
     *
     * @return array{
     *     current_hp: int,
     *     healed_amount: int,
     *     is_revived: bool
     * }
     */
    public function applyHealing(int $currentHp, int $maxHp, int $healing): array
    {
        $healing = max(0, $healing);
        $wasDown = ($currentHp === 0);
        $newCurrentHp = min($maxHp, $currentHp + $healing);
        $actualHealed = $newCurrentHp - $currentHp;

        return [
            'current_hp' => $newCurrentHp,
            'healed_amount' => $actualHealed,
            'is_revived' => ($wasDown && $newCurrentHp > 0),
        ];
    }

    /**
     * Set or update temporary hit points.
     * By default in 5e, temporary HP does not stack and you take the higher amount.
     *
     * @return array{
     *     temp_hp: int,
     *     changed: bool
     * }
     */
    public function applyTempHp(int $currentTempHp, int $newTempHp, bool $force = false): array
    {
        $newTempHp = max(0, $newTempHp);
        $finalTempHp = $force ? $newTempHp : max($currentTempHp, $newTempHp);

        return [
            'temp_hp' => $finalTempHp,
            'changed' => ($finalTempHp !== $currentTempHp),
        ];
    }

    /**
     * Calculate starting HP from average or rolled formula.
     */
    public function calculateStartingHp(
        int $hpAverage,
        ?string $hpFormula = null,
        bool $rollFormula = false,
        ?DiceRoller $diceRoller = null
    ): int {
        if ($rollFormula && $hpFormula !== null && trim($hpFormula) !== '') {
            $roller = $diceRoller ?? new DiceRoller();
            return $roller->rollHp($hpFormula, $hpAverage);
        }

        return max(1, $hpAverage);
    }
}
