<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Dice rolling engine supporting standard D&D notation:
 * - Simple dice: "d20", "1d20", "3d6", "2d8+3", "1d4-1"
 * - Multi-dice combinations: "2d6 + 1d4 + 2"
 * - Flat numbers: "5", "+2", "-1"
 * - Advantage / Disadvantage helpers for d20 rolls
 */
final class DiceRoller
{
    /**
     * Roll a dice expression and return a structured result.
     *
     * @return array{
     *     total: int,
     *     expression: string,
     *     rolls: list<int>,
     *     modifier: int,
     *     breakdown: string
     * }
     */
    public function roll(string $expression): array
    {
        $clean = trim($expression);
        if ($clean === '') {
            return [
                'total' => 0,
                'expression' => '',
                'rolls' => [],
                'modifier' => 0,
                'breakdown' => '0',
            ];
        }

        // Tokenize expression (e.g. "2d6 + 1d4 - 2")
        // Remove spaces for easier parsing
        $normalized = str_replace(' ', '', strtolower($clean));

        // Match tokens: each token is an optional sign (+ or -), followed by either XdY or a flat number
        $pattern = '/([+-]?)(?:(\d*)d(\d+)|(\d+))/i';
        if (!preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER)) {
            throw new \InvalidArgumentException("Invalid dice expression: '$expression'");
        }

        // Verify that the matched tokens cover the entire normalized string
        $reconstructed = '';
        foreach ($matches as $m) {
            $reconstructed .= $m[0];
        }
        if ($reconstructed !== $normalized && '+' . $reconstructed !== $normalized) {
            throw new \InvalidArgumentException("Invalid dice expression: '$expression'");
        }

        $allRolls = [];
        $totalModifier = 0;
        $breakdownParts = [];
        $grandTotal = 0;

        foreach ($matches as $match) {
            $sign = ($match[1] === '-') ? -1 : 1;

            if (isset($match[3]) && $match[3] !== '') {
                // It's a dice roll: [count]d[sides]
                $count = $match[2] === '' ? 1 : (int) $match[2];
                $sides = (int) $match[3];

                if ($count <= 0 || $sides <= 0 || $count > 100 || $sides > 1000) {
                    throw new \InvalidArgumentException("Dice count ($count) or sides ($sides) out of bounds.");
                }

                $diceRolls = [];
                $diceSum = 0;
                for ($i = 0; $i < $count; $i++) {
                    $r = random_int(1, $sides);
                    $diceRolls[] = $r;
                    $diceSum += $r;
                }

                $signedSum = $sign * $diceSum;
                $grandTotal += $signedSum;
                $allRolls = array_merge($allRolls, $diceRolls);

                $rollsStr = implode('+', $diceRolls);
                if ($count > 1) {
                    $rollsStr = "($rollsStr)";
                }
                $breakdownParts[] = ($sign === -1 ? '- ' : (empty($breakdownParts) ? '' : '+ ')) . $rollsStr;
            } elseif (isset($match[4]) && $match[4] !== '') {
                // Flat number
                $val = (int) $match[4];
                $signedVal = $sign * $val;
                $totalModifier += $signedVal;
                $grandTotal += $signedVal;

                $breakdownParts[] = ($sign === -1 ? '- ' : (empty($breakdownParts) ? '' : '+ ')) . $val;
            }
        }

        $breakdown = implode(' ', $breakdownParts) . ' = ' . $grandTotal;

        return [
            'total' => $grandTotal,
            'expression' => $clean,
            'rolls' => $allRolls,
            'modifier' => $totalModifier,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Roll a standard d20 initiative check with a bonus.
     *
     * @return array{
     *     total: int,
     *     natural: int,
     *     bonus: int,
     *     breakdown: string
     * }
     */
    public function rollInitiative(int $bonus): array
    {
        $d20 = random_int(1, 20);
        $total = $d20 + $bonus;
        $sign = $bonus >= 0 ? '+' : '';
        $breakdown = sprintf('1d20 (%d) %s%d = %d', $d20, $sign, $bonus, $total);

        return [
            'total' => $total,
            'natural' => $d20,
            'bonus' => $bonus,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Roll starting HP from formula (e.g. "2d6+2") or fall back to average.
     */
    public function rollHp(?string $formula, int $fallbackAverage = 1): int
    {
        if ($formula === null || trim($formula) === '') {
            return max(1, $fallbackAverage);
        }

        try {
            $result = $this->roll($formula);
            return max(1, $result['total']);
        } catch (\InvalidArgumentException) {
            return max(1, $fallbackAverage);
        }
    }

    /**
     * Roll a d20 with advantage (roll 2, keep highest).
     */
    public function rollAdvantage(int $bonus = 0): array
    {
        $r1 = random_int(1, 20);
        $r2 = random_int(1, 20);
        $kept = max($r1, $r2);
        $total = $kept + $bonus;
        $sign = $bonus >= 0 ? '+' : '';

        return [
            'total' => $total,
            'natural' => $kept,
            'rolls' => [$r1, $r2],
            'bonus' => $bonus,
            'breakdown' => sprintf('Advantage [%d, %d] -> %d %s%d = %d', $r1, $r2, $kept, $sign, $bonus, $total),
        ];
    }

    /**
     * Roll a d20 with disadvantage (roll 2, keep lowest).
     */
    public function rollDisadvantage(int $bonus = 0): array
    {
        $r1 = random_int(1, 20);
        $r2 = random_int(1, 20);
        $kept = min($r1, $r2);
        $total = $kept + $bonus;
        $sign = $bonus >= 0 ? '+' : '';

        return [
            'total' => $total,
            'natural' => $kept,
            'rolls' => [$r1, $r2],
            'bonus' => $bonus,
            'breakdown' => sprintf('Disadvantage [%d, %d] -> %d %s%d = %d', $r1, $r2, $kept, $sign, $bonus, $total),
        ];
    }
}
