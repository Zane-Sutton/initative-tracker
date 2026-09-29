<?php

use function App\csrf_field;
use function App\e;
use function App\signed;

/** @var array<string,mixed> $encounter */
/** @var list<array<string,mixed>> $participants */
/** @var list<array<string,mixed>> $characters */
/** @var list<array<string,mixed>> $monsters */
/** @var list<array<string,mixed>> $conditions */

$isPlanned = ($encounter['status'] === 'planned');
$isActive = ($encounter['status'] === 'active');
$isFinished = ($encounter['status'] === 'finished');
$currentTurnOrder = $encounter['current_turn_order'] !== null ? (int)$encounter['current_turn_order'] : null;
$currentRound = (int)$encounter['current_round'];
?>

<div class="encounter-header" data-encounter-id="<?= e($encounter['id']) ?>">
    <div class="toolbar">
        <div>
            <h2><?= e($encounter['name']) ?></h2>
            <div class="encounter-meta">
                <span class="badge badge-<?= e($encounter['status']) ?>"><?= e(ucfirst((string)$encounter['status'])) ?></span>
                <?php if ($currentRound > 0): ?>
                    <span class="round-indicator"><strong>Round <?= e($currentRound) ?></strong></span>
                <?php endif; ?>
                <a href="/encounters/<?= e($encounter['id']) ?>/edit" style="margin-left: 0.5rem; font-size: 0.9rem;">Edit Details</a>
            </div>
        </div>

        <div class="turn-controls">
            <?php if ($isPlanned): ?>
                <form method="post" action="/encounters/<?= e($encounter['id']) ?>/start" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-success">▶ Start Combat</button>
                </form>
            <?php elseif ($isActive): ?>
                <form method="post" action="/encounters/<?= e($encounter['id']) ?>/prev" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">◀ Prev Turn</button>
                </form>
                <form method="post" action="/encounters/<?= e($encounter['id']) ?>/next" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-primary">Next Turn ▶</button>
                </form>
                <form method="post" action="/encounters/<?= e($encounter['id']) ?>/finish" class="inline"
                      onsubmit="return confirm('Finish this encounter?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">Finish</button>
                </form>
                <form method="post" action="/encounters/<?= e($encounter['id']) ?>/reset" class="inline"
                      onsubmit="return confirm('Reset encounter back to planned?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="button link danger">Reset</button>
                </form>
            <?php else: ?>
                <form method="post" action="/encounters/<?= e($encounter['id']) ?>/start" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-secondary">Restart Combat</button>
                </form>
            <?php endif; ?>

            <form method="post" action="/encounters/<?= e($encounter['id']) ?>/roll-initiative" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="monsters_only" value="1">
                <button type="submit" class="button button-secondary" title="Roll initiative for all monsters without an initiative score">Roll Monster Init</button>
            </form>
            <form method="post" action="/encounters/<?= e($encounter['id']) ?>/sort" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="button button-secondary" title="Sort turn order by initiative scores and tie-breaking">Sort Order</button>
            </form>
        </div>
    </div>

    <?php if (!empty($encounter['notes'])): ?>
        <div class="encounter-notes-box">
            <strong>Notes:</strong> <?= nl2br(e($encounter['notes'])) ?>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Dice Roller Bar -->
<div class="dice-roller-bar">
    <div class="dice-presets">
        <span class="dice-label">🎲 Quick Roll:</span>
        <button type="button" class="btn-quick-die" data-die="1d20">d20</button>
        <button type="button" class="btn-quick-die" data-die="1d12">d12</button>
        <button type="button" class="btn-quick-die" data-die="1d10">d10</button>
        <button type="button" class="btn-quick-die" data-die="1d8">d8</button>
        <button type="button" class="btn-quick-die" data-die="1d6">d6</button>
        <button type="button" class="btn-quick-die" data-die="1d4">d4</button>
        <button type="button" class="btn-quick-die" data-die="1d100">d100</button>
    </div>
    <form id="quick-dice-form" class="inline dice-custom-form">
        <input type="text" id="quick-dice-input" placeholder="e.g. 2d6+3" value="1d20" style="max-width: 7rem;">
        <button type="submit" class="button button-small">Roll</button>
    </form>
    <div id="dice-result-display" class="dice-result">Ready</div>
</div>

<!-- Combatants Section -->
<div class="combatants-section">
    <h3>Combatants (<?= count($participants) ?>)</h3>

    <?php if ($participants === []): ?>
        <p class="empty">No combatants in this encounter yet. Add characters or monsters below.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="combatants-table">
            <thead>
                <tr>
                    <th style="width: 3.5rem;">Order</th>
                    <th style="width: 2rem;">Act</th>
                    <th>Combatant</th>
                    <th style="width: 7.5rem;">Initiative</th>
                    <th style="width: 4rem;">AC</th>
                    <th style="width: 17rem;">HP (Current / Max + Temp)</th>
                    <th>Conditions &amp; Notes</th>
                    <th style="width: 3rem;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($participants as $p): ?>
                <?php
                    $isCurrent = ($isActive && $currentTurnOrder !== null && (int)$p['turn_order'] === $currentTurnOrder);
                    $isDown = ((int)$p['current_hp'] === 0);
                    $isInactive = empty($p['is_active']);
                    $curHp = (int)$p['current_hp'];
                    $maxHp = max(1, (int)$p['max_hp']);
                    $tempHp = (int)$p['temp_hp'];
                    $hpPercent = max(0, min(100, ($curHp / $maxHp) * 100));
                    $tempPercent = max(0, min(100, ($tempHp / $maxHp) * 100));

                    $rowClasses = [];
                    if ($isCurrent) $rowClasses[] = 'is-current-turn';
                    if ($isDown) $rowClasses[] = 'is-down';
                    if ($isInactive) $rowClasses[] = 'is-inactive';
                ?>
                <tr class="combatant-row <?= implode(' ', $rowClasses) ?>" data-participant-id="<?= e($p['id']) ?>">
                    <!-- Turn Order -->
                    <td class="cell-order">
                        <?php if ($isCurrent): ?>
                            <span class="current-turn-arrow" title="Currently acting">▶</span>
                        <?php endif; ?>
                        <span class="order-number"><?= $p['turn_order'] !== null ? '#' . e($p['turn_order']) : '—' ?></span>
                    </td>

                    <!-- Active Toggle -->
                    <td class="cell-active">
                        <label title="Toggle active in turn order">
                            <input type="checkbox" class="toggle-active-checkbox" <?= !empty($p['is_active']) ? 'checked' : '' ?>>
                        </label>
                    </td>

                    <!-- Display Name (Inline editable) -->
                    <td class="cell-name">
                        <input type="text" class="inline-edit name-input" data-field="display_name" value="<?= e($p['display_name']) ?>" title="Click to rename snapshot">
                        <div class="combatant-subtext">
                            <?php if (!empty($p['character_id'])): ?>
                                <span class="badge-type badge-character">PC</span>
                                <?php if (!empty($p['character_player_name'])): ?><?= e($p['character_player_name']) ?> &bull; <?php endif; ?>
                                <?= e($p['character_class'] ?? '') ?>
                            <?php elseif (!empty($p['monster_id'])): ?>
                                <span class="badge-type badge-monster">Monster</span>
                                <a href="/monsters/<?= e($p['monster_id']) ?>" class="statblock-link" data-monster-id="<?= e($p['monster_id']) ?>">CR <?= e($p['monster_cr'] ?? '?') ?> stat block ↗</a>
                            <?php else: ?>
                                <span class="badge-type badge-custom">Custom</span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Initiative (Inline editable & Roll button) -->
                    <td class="cell-initiative">
                        <div class="init-controls">
                            <input type="number" class="inline-edit init-input" data-field="initiative" value="<?= $p['initiative'] !== null ? e($p['initiative']) : '' ?>" placeholder="—">
                            <span class="init-bonus" title="Initiative modifier">(<?= e(signed($p['initiative_bonus'])) ?>)</span>
                            <button type="button" class="btn-roll-single-init button-mini" title="Roll 1d20 + <?= e($p['initiative_bonus']) ?>">🎲</button>
                        </div>
                    </td>

                    <!-- AC (Inline editable) -->
                    <td class="cell-ac">
                        <input type="number" class="inline-edit ac-input" data-field="armor_class" value="<?= e($p['armor_class']) ?>" min="0" max="40">
                    </td>

                    <!-- HP Box & Quick Adjustments -->
                    <td class="cell-hp">
                        <div class="hp-box">
                            <div class="hp-bar-container">
                                <div class="hp-bar-fill <?= $hpPercent <= 25 ? 'hp-critical' : ($hpPercent <= 50 ? 'hp-low' : 'hp-healthy') ?>" style="width: <?= $hpPercent ?>%;"></div>
                                <div class="hp-bar-temp" style="width: <?= $tempPercent ?>%;"></div>
                            </div>
                            <div class="hp-inputs-row">
                                <input type="number" class="inline-edit hp-val" data-field="current_hp" value="<?= e($curHp) ?>" min="0" title="Current HP">
                                <span>/</span>
                                <input type="number" class="inline-edit hp-val hp-max" data-field="max_hp" value="<?= e($maxHp) ?>" min="1" title="Max HP">
                                <?php if ($tempHp > 0 || true): ?>
                                    <span class="temp-label">+</span>
                                    <input type="number" class="inline-edit hp-val hp-temp" data-field="temp_hp" value="<?= e($tempHp) ?>" min="0" title="Temporary HP">
                                <?php endif; ?>
                            </div>
                            <div class="hp-quick-adjust">
                                <input type="number" class="hp-adjust-input" placeholder="Amount" min="1">
                                <button type="button" class="btn-damage button-mini danger" title="Apply Damage">- Dmg</button>
                                <button type="button" class="btn-heal button-mini success" title="Apply Healing">+ Heal</button>
                                <button type="button" class="btn-temp-hp button-mini" title="Apply Temp HP">Temp</button>
                            </div>
                        </div>
                    </td>

                    <!-- Conditions & Notes -->
                    <td class="cell-conditions">
                        <div class="conditions-list">
                            <?php foreach ($p['conditions'] as $cond): ?>
                                <span class="condition-badge" title="Applied round: <?= e($cond['applied_round'] ?? '—') ?>">
                                    <?= e($cond['name']) ?>
                                    <button type="button" class="btn-remove-condition" data-condition-id="<?= e($cond['id']) ?>">&times;</button>
                                </span>
                            <?php endforeach; ?>

                            <select class="condition-select button-mini">
                                <option value="">+ Condition</option>
                                <?php foreach ($conditions as $cOption): ?>
                                    <option value="<?= e($cOption['id']) ?>"><?= e($cOption['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="text" class="inline-edit notes-input" data-field="notes" value="<?= e($p['notes'] ?? '') ?>" placeholder="Add combat notes...">
                    </td>

                    <!-- Remove -->
                    <td class="cell-actions">
                        <form method="post" action="/encounters/<?= e($encounter['id']) ?>/participants/<?= e($p['id']) ?>/delete"
                              onsubmit="return confirm('Remove <?= e($p['display_name']) ?> from encounter?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="link danger button-mini" title="Remove combatant">&times;</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Combatants Section -->
<div class="add-combatants-panel">
    <h3>Add Combatants to Encounter</h3>
    <div class="tabs-header">
        <button type="button" class="tab-btn active" data-tab="tab-add-monster">Add Monster</button>
        <button type="button" class="tab-btn" data-tab="tab-add-character">Add Player Character</button>
        <button type="button" class="tab-btn" data-tab="tab-add-custom">Add Custom</button>
    </div>

    <!-- Tab 1: Monster -->
    <div id="tab-add-monster" class="tab-content active">
        <form method="post" action="/encounters/<?= e($encounter['id']) ?>/participants/monster" class="add-form">
            <?= csrf_field() ?>
            <div class="add-form-grid">
                <div class="field">
                    <label for="f_monster_id">Select Monster</label>
                    <select id="f_monster_id" name="monster_id" required>
                        <option value="">-- Choose Monster --</option>
                        <?php foreach ($monsters as $m): ?>
                            <option value="<?= e($m['id']) ?>">
                                <?= e($m['name']) ?> (CR <?= e($m['challenge_rating'] ?? '-') ?>, HP <?= e($m['hp_average']) ?>, AC <?= e($m['armor_class']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field" style="max-width: 6rem;">
                    <label for="f_monster_qty">Qty</label>
                    <input type="number" id="f_monster_qty" name="quantity" value="1" min="1" max="50">
                </div>

                <div class="field">
                    <label for="f_custom_name">Custom Label (optional)</label>
                    <input type="text" id="f_custom_name" name="custom_name" placeholder="e.g. Goblin Archer">
                </div>
            </div>

            <div class="add-form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="roll_hp" value="1"> Roll HP formula
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="roll_initiative" value="1" checked> Roll Initiative on add
                </label>
                <button type="submit" class="button">Add Monster(s)</button>
            </div>
        </form>
    </div>

    <!-- Tab 2: Player Character -->
    <div id="tab-add-character" class="tab-content">
        <form method="post" action="/encounters/<?= e($encounter['id']) ?>/participants/character" class="add-form">
            <?= csrf_field() ?>
            <div class="add-form-grid">
                <div class="field">
                    <label for="f_character_id">Select Player Character</label>
                    <select id="f_character_id" name="character_id" required>
                        <option value="">-- Choose Character --</option>
                        <?php foreach ($characters as $char): ?>
                            <option value="<?= e($char['id']) ?>">
                                <?= e($char['name']) ?> (<?= e($char['class'] ?? 'Level ' . $char['level']) ?>, HP <?= e($char['max_hp']) ?>, AC <?= e($char['armor_class']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="align-self: flex-end;">
                    <button type="submit" class="button">Add Character</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tab 3: Custom Combatant -->
    <div id="tab-add-custom" class="tab-content">
        <form method="post" action="/encounters/<?= e($encounter['id']) ?>/participants/custom" class="add-form">
            <?= csrf_field() ?>
            <div class="grid">
                <div class="field">
                    <label for="f_custom_dname">Name</label>
                    <input type="text" id="f_custom_dname" name="display_name" required placeholder="e.g. Bandit Captain">
                </div>
                <div class="field">
                    <label for="f_custom_ac">AC</label>
                    <input type="number" id="f_custom_ac" name="armor_class" value="10" min="0" max="40">
                </div>
                <div class="field">
                    <label for="f_custom_hp">Max HP</label>
                    <input type="number" id="f_custom_hp" name="max_hp" value="15" min="1" max="9999">
                </div>
                <div class="field">
                    <label for="f_custom_init_bonus">Init Bonus</label>
                    <input type="number" id="f_custom_init_bonus" name="initiative_bonus" value="0" min="-20" max="30">
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="button">Add Custom Combatant</button>
            </div>
        </form>
    </div>
</div>

<!-- Statblock Side Panel -->
<div id="statblock-panel" class="statblock-side-panel" aria-hidden="true">
    <div class="statblock-panel-header">
        <span>Monster Stat Block</span>
        <div>
            <a id="statblock-panel-open-tab" href="#" target="_blank" class="statblock-panel-newtab" title="Open in new tab">↗</a>
            <button type="button" id="statblock-panel-close" class="statblock-panel-close" title="Close">&times;</button>
        </div>
    </div>
    <div class="statblock-panel-body">
        <iframe id="statblock-panel-frame" src="about:blank" title="Monster Stat Block"></iframe>
    </div>
</div>

<script src="/js/tracker.js"></script>
