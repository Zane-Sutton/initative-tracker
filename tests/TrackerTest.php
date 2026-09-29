<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\DiceRoller;
use App\Services\HpCalculator;
use App\Services\TurnOrderEngine;

function assertTrue(bool $condition, string $msg): void
{
    if (!$condition) {
        throw new \Exception("Assertion failed: $msg");
    }
    echo "  ✓ $msg\n";
}

function assertEquals(mixed $expected, mixed $actual, string $msg): void
{
    if ($expected !== $actual) {
        throw new \Exception("Assertion failed: $msg (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")");
    }
    echo "  ✓ $msg\n";
}

echo "=== Running Initiative Tracker Tests ===\n\n";

// 1. Test DiceRoller
echo "1. Testing DiceRoller...\n";
$roller = new DiceRoller();

// Empty roll
$emptyRoll = $roller->roll('');
assertEquals(0, $emptyRoll['total'], "Empty dice roll returns 0");

// Flat number
$flatRoll = $roller->roll('5');
assertEquals(5, $flatRoll['total'], "Flat roll '5' returns 5");

$negativeFlat = $roller->roll('-3');
assertEquals(-3, $negativeFlat['total'], "Negative flat roll '-3' returns -3");

// Standard dice ranges
for ($i = 0; $i < 50; $i++) {
    $d20 = $roller->roll('1d20');
    assertTrue($d20['total'] >= 1 && $d20['total'] <= 20, "1d20 roll is between 1 and 20 (got {$d20['total']})");
    assertEquals(1, count($d20['rolls']), "1d20 has 1 roll");

    $twoD6plus3 = $roller->roll('2d6+3');
    assertTrue($twoD6plus3['total'] >= 5 && $twoD6plus3['total'] <= 15, "2d6+3 is between 5 and 15 (got {$twoD6plus3['total']})");
    assertEquals(3, $twoD6plus3['modifier'], "2d6+3 modifier is 3");
    assertEquals(2, count($twoD6plus3['rolls']), "2d6+3 has 2 rolls");

    $initRoll = $roller->rollInitiative(3);
    assertTrue($initRoll['total'] >= 4 && $initRoll['total'] <= 23, "Initiative with +3 bonus is between 4 and 23");
    assertEquals(3, $initRoll['bonus'], "Initiative bonus preserved");

    $advRoll = $roller->rollAdvantage(2);
    assertTrue($advRoll['total'] >= 3 && $advRoll['total'] <= 22, "Advantage roll is in range");
    assertEquals(2, count($advRoll['rolls']), "Advantage rolls 2 dice");
    assertTrue($advRoll['natural'] === max($advRoll['rolls']), "Advantage keeps highest die");

    $disRoll = $roller->rollDisadvantage(2);
    assertTrue($disRoll['natural'] === min($disRoll['rolls']), "Disadvantage keeps lowest die");
}

// Invalid expressions
try {
    $roller->roll('invalid_dice');
    throw new \Exception("Should have thrown InvalidArgumentException for invalid expression");
} catch (\InvalidArgumentException) {
    echo "  ✓ Invalid dice expression throws InvalidArgumentException\n";
}

// Roll HP
assertEquals(10, $roller->rollHp(null, 10), "RollHp with null formula falls back to average");
assertEquals(12, $roller->rollHp('', 12), "RollHp with empty formula falls back to average");
assertTrue($roller->rollHp('2d8+2', 11) >= 4, "RollHp with formula returns rolled HP >= 4");


// 2. Test HpCalculator
echo "\n2. Testing HpCalculator...\n";
$hp = new HpCalculator();

// Standard damage without temp HP
$dmg1 = $hp->applyDamage(20, 0, 8);
assertEquals(12, $dmg1['current_hp'], "20 HP taking 8 damage leaves 12 HP");
assertEquals(0, $dmg1['temp_hp'], "Temp HP remains 0");
assertEquals(8, $dmg1['hp_lost'], "8 HP lost");
assertEquals(false, $dmg1['is_down'], "Not down");

// Damage exceeding current HP
$dmg2 = $hp->applyDamage(5, 0, 10);
assertEquals(0, $dmg2['current_hp'], "5 HP taking 10 damage drops to 0 (cannot drop below 0)");
assertEquals(5, $dmg2['hp_lost'], "5 actual HP lost");
assertEquals(true, $dmg2['is_down'], "is_down is true");

// Damage with Temp HP absorption
$dmg3 = $hp->applyDamage(25, 10, 6);
assertEquals(25, $dmg3['current_hp'], "Temp HP absorbs all 6 damage, current HP untouched");
assertEquals(4, $dmg3['temp_hp'], "10 Temp HP minus 6 leaves 4 Temp HP");
assertEquals(6, $dmg3['temp_absorbed'], "6 damage absorbed by temp HP");
assertEquals(0, $dmg3['hp_lost'], "0 current HP lost");

// Damage exceeding Temp HP
$dmg4 = $hp->applyDamage(25, 10, 15);
assertEquals(20, $dmg4['current_hp'], "15 damage: 10 absorbed by temp, 5 reduces current HP from 25 to 20");
assertEquals(0, $dmg4['temp_hp'], "Temp HP reduced to 0");
assertEquals(10, $dmg4['temp_absorbed'], "10 absorbed by temp");
assertEquals(5, $dmg4['hp_lost'], "5 HP lost from main HP");

// Healing
$heal1 = $hp->applyHealing(15, 30, 10);
assertEquals(25, $heal1['current_hp'], "15 HP healed for 10 reaches 25 HP");
assertEquals(10, $heal1['healed_amount'], "10 healed");
assertEquals(false, $heal1['is_revived'], "Was not down");

// Healing capped at max HP
$heal2 = $hp->applyHealing(28, 30, 10);
assertEquals(30, $heal2['current_hp'], "28 HP healed for 10 capped at max HP 30");
assertEquals(2, $heal2['healed_amount'], "Actual healed amount is 2");

// Healing from 0 (revive)
$heal3 = $hp->applyHealing(0, 30, 5);
assertEquals(5, $heal3['current_hp'], "0 HP healed for 5 restores 5 HP");
assertEquals(true, $heal3['is_revived'], "Revived from down");

// Temp HP non-stacking rules (keep higher)
$temp1 = $hp->applyTempHp(5, 8);
assertEquals(8, $temp1['temp_hp'], "5 temp HP receiving 8 temp HP becomes 8");
assertEquals(true, $temp1['changed'], "Temp HP changed");

$temp2 = $hp->applyTempHp(10, 4);
assertEquals(10, $temp2['temp_hp'], "10 temp HP receiving 4 temp HP keeps 10");
assertEquals(false, $temp2['changed'], "Temp HP unchanged");

$temp3 = $hp->applyTempHp(10, 4, force: true);
assertEquals(4, $temp3['temp_hp'], "Forced temp HP overwrites with 4");


// 3. Test TurnOrderEngine & 5e Tie-Breaking
echo "\n3. Testing TurnOrderEngine...\n";
$engine = new TurnOrderEngine();

$participants = [
    ['id' => 1, 'display_name' => 'Fighter', 'initiative' => 15, 'initiative_bonus' => 2, 'character_id' => 10, 'monster_id' => null, 'is_active' => 1],
    ['id' => 2, 'display_name' => 'Goblin 1', 'initiative' => 15, 'initiative_bonus' => 2, 'character_id' => null, 'monster_id' => 20, 'is_active' => 1],
    ['id' => 3, 'display_name' => 'Rogue', 'initiative' => 20, 'initiative_bonus' => 4, 'character_id' => 11, 'monster_id' => null, 'is_active' => 1],
    ['id' => 4, 'display_name' => 'Wizard', 'initiative' => 15, 'initiative_bonus' => 3, 'character_id' => 12, 'monster_id' => null, 'is_active' => 1],
    ['id' => 5, 'display_name' => 'Zombie', 'initiative' => 8, 'initiative_bonus' => -2, 'character_id' => null, 'monster_id' => 21, 'is_active' => 1],
    ['id' => 6, 'display_name' => 'Goblin 2', 'initiative' => 15, 'initiative_bonus' => 2, 'character_id' => null, 'monster_id' => 20, 'is_active' => 1],
    ['id' => 7, 'display_name' => 'Unrolled NPC', 'initiative' => null, 'initiative_bonus' => 0, 'character_id' => null, 'monster_id' => null, 'is_active' => 1],
];

$sorted = $engine->sortParticipants($participants);

// Expected order:
// 1. Rogue (Init 20)
// 2. Wizard (Init 15, bonus +3)
// 3. Fighter (Init 15, bonus +2, Player Character wins tie against Goblins)
// 4. Goblin 1 (Init 15, bonus +2, ID 2 wins tie against Goblin 2 ID 6)
// 5. Goblin 2 (Init 15, bonus +2, ID 6)
// 6. Zombie (Init 8)
// 7. Unrolled NPC (Init null)

assertEquals('Rogue', $sorted[0]['display_name'], "1st in turn order is Rogue (Init 20)");
assertEquals(1, $sorted[0]['turn_order'], "Rogue turn_order is 1");

assertEquals('Wizard', $sorted[1]['display_name'], "2nd in turn order is Wizard (Init 15, bonus +3)");
assertEquals(2, $sorted[1]['turn_order'], "Wizard turn_order is 2");

assertEquals('Fighter', $sorted[2]['display_name'], "3rd in turn order is Fighter (Init 15, bonus +2, PC tie-breaker over monsters)");
assertEquals(3, $sorted[2]['turn_order'], "Fighter turn_order is 3");

assertEquals('Goblin 1', $sorted[3]['display_name'], "4th in turn order is Goblin 1 (ID 2 vs ID 6)");
assertEquals(4, $sorted[3]['turn_order'], "Goblin 1 turn_order is 4");

assertEquals('Goblin 2', $sorted[4]['display_name'], "5th in turn order is Goblin 2");
assertEquals(5, $sorted[4]['turn_order'], "Goblin 2 turn_order is 5");

assertEquals('Zombie', $sorted[5]['display_name'], "6th in turn order is Zombie (Init 8)");
assertEquals(6, $sorted[5]['turn_order'], "Zombie turn_order is 6");

assertEquals('Unrolled NPC', $sorted[6]['display_name'], "7th in turn order is Unrolled NPC (null initiative)");
assertEquals(7, $sorted[6]['turn_order'], "Unrolled NPC turn_order is 7");


// 4. Test Turn Progression (nextTurn, prevTurn, looping rounds)
echo "\n4. Testing Turn Progression...\n";

// Start combat
$start = $engine->startCombat($sorted);
assertEquals(1, $start['round'], "Combat starts at Round 1");
assertEquals(1, $start['turn_order'], "Combat starts at turn_order 1 (Rogue)");
assertEquals(3, $start['participant_id'], "Combat starts with Rogue (ID 3)");

// Advance through turns
$t2 = $engine->nextTurn(1, 1, $sorted);
assertEquals(1, $t2['round'], "Still Round 1");
assertEquals(2, $t2['turn_order'], "Turn order 2 (Wizard)");
assertEquals(4, $t2['participant_id'], "Participant is Wizard");

$t3 = $engine->nextTurn(1, 2, $sorted);
assertEquals(1, $t3['round'], "Still Round 1");
assertEquals(3, $t3['turn_order'], "Turn order 3 (Fighter)");

// Advance from last combatant (turn_order 7) -> loops to Round 2, turn_order 1
$tNextRound = $engine->nextTurn(1, 7, $sorted);
assertEquals(2, $tNextRound['round'], "Advanced to Round 2");
assertEquals(1, $tNextRound['turn_order'], "Looped to turn_order 1 (Rogue)");
assertEquals(3, $tNextRound['participant_id'], "Participant is Rogue");

// Previous turn within round
$prevInRound = $engine->prevTurn(2, 2, $sorted);
assertEquals(2, $prevInRound['round'], "Still Round 2");
assertEquals(1, $prevInRound['turn_order'], "Went back to turn_order 1");

// Previous turn crossing back into previous round (from Round 2, turn 1 -> Round 1, turn 7)
$prevCrossRound = $engine->prevTurn(2, 1, $sorted);
assertEquals(1, $prevCrossRound['round'], "Went back to Round 1");
assertEquals(7, $prevCrossRound['turn_order'], "Went to last turn (7)");

// Previous turn at Round 1, turn 1 cannot go below Round 1
$prevBound = $engine->prevTurn(1, 1, $sorted);
assertEquals(1, $prevBound['round'], "Round remains 1");
assertEquals(1, $prevBound['turn_order'], "Turn order remains 1");

// Test inactive combatant skipping
$withInactive = $sorted;
$withInactive[1]['is_active'] = 0; // Wizard is down/inactive (turn_order 2)
$skipTurn = $engine->nextTurn(1, 1, $withInactive); // from turn 1 -> should skip 2 and go to 3
assertEquals(3, $skipTurn['turn_order'], "Skips inactive Wizard and goes to Fighter (turn_order 3)");

echo "\nAll Tests Passed Successfully! 🎉\n";
