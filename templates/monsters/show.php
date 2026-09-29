<?php

use function App\ability_modifier;
use function App\csrf_field;
use function App\e;
use function App\signed;

/** @var array<string,mixed> $monster */
$subtitle = trim(implode(' ', array_filter([$monster['size'], $monster['creature_type']])));
$hp = (string) $monster['hp_average'] . ($monster['hp_formula'] ? ' (' . $monster['hp_formula'] . ')' : '');

$stats = [];
if (!empty($monster['stats'])) {
    $decoded = json_decode((string) $monster['stats'], true);
    if (is_array($decoded)) {
        $stats = $decoded;
    }
}

$abilityNames = ['str' => 'Strength', 'dex' => 'Dexterity', 'con' => 'Constitution', 'int' => 'Intelligence', 'wis' => 'Wisdom', 'cha' => 'Charisma'];
$saveOverrides = is_array($stats['saves'] ?? null) ? $stats['saves'] : [];
$skills = is_array($stats['skills'] ?? null) ? $stats['skills'] : [];

/** Render a clickable d20 roll trigger, e.g. "+2" that rolls "1d20+2" in the main tracker window. */
$rollTrigger = static function (int $bonus, string $label): string {
    $expr = '1d20' . ($bonus >= 0 ? '+' . $bonus : $bonus);

    return sprintf(
        '<button type="button" class="roll-trigger" data-roll="%s" data-roll-label="%s" title="Roll %s">%s</button>',
        e($expr),
        e($label),
        e($label),
        e(signed($bonus))
    );
};
?>
<div class="toolbar">
    <h2><?= e($monster['name']) ?></h2>
    <div>
        <a class="button" href="/monsters/<?= e($monster['id']) ?>/edit">Edit</a>
        <a href="/monsters">Back to list</a>
    </div>
</div>

<?php if ($subtitle !== ''): ?><p class="subtitle"><?= e($subtitle) ?><?= $monster['challenge_rating'] ? ', Challenge ' . e($monster['challenge_rating']) : '' ?></p><?php endif; ?>

<div class="statblock">
    <div class="statblock-top">
        <dl class="statblock-vitals">
            <dt>Armor Class</dt><dd><?= e($monster['armor_class']) ?><?= !empty($stats['armor']) ? ' (' . e($stats['armor']) . ')' : '' ?></dd>
            <dt>Hit Points</dt><dd><?= e($hp) ?></dd>
            <?php if ($monster['speed']): ?><dt>Speed</dt><dd><?= e($monster['speed']) ?></dd><?php endif; ?>
            <dt>Initiative</dt><dd><?= $rollTrigger((int) $monster['initiative_bonus'], $monster['name'] . ' Initiative') ?></dd>
        </dl>
    </div>

    <?php if ($stats !== [] && array_intersect_key($stats, $abilityNames) !== []): ?>
    <div class="ability-grid">
        <?php foreach ($abilityNames as $key => $label): ?>
            <?php if (!array_key_exists($key, $stats)) continue; ?>
            <?php
                $score = (int) $stats[$key];
                $mod = ability_modifier($score);
                $saveBonus = array_key_exists($key, $saveOverrides) ? (int) $saveOverrides[$key] : $mod;
            ?>
            <div class="ability-box">
                <div class="ability-name"><?= e(strtoupper($key)) ?></div>
                <div class="ability-score"><?= e($score) ?></div>
                <div class="ability-mod"><?= $rollTrigger($mod, $monster['name'] . ' ' . $label . ' Check') ?></div>
                <div class="ability-save">
                    <span class="ability-save-label">Save</span>
                    <?= $rollTrigger($saveBonus, $monster['name'] . ' ' . $label . ' Save') ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <dl class="statblock-details">
        <?php if ($skills !== []): ?>
            <dt>Skills</dt>
            <dd class="skills-list">
                <?php $skillParts = []; foreach ($skills as $skillName => $skillBonus):
                    $skillParts[] = '<span class="skill-entry">' . e(ucfirst((string) $skillName)) . ' ' . $rollTrigger((int) $skillBonus, $monster['name'] . ' ' . ucfirst((string) $skillName) . ' Check') . '</span>';
                endforeach; ?>
                <?= implode(', ', $skillParts) ?>
            </dd>
        <?php endif; ?>

        <?php if (!empty($stats['damage_vulnerabilities'])): ?>
            <dt>Damage Vulnerabilities</dt><dd><?= e(implode(', ', (array) $stats['damage_vulnerabilities'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($stats['damage_resistances'])): ?>
            <dt>Damage Resistances</dt><dd><?= e(implode(', ', (array) $stats['damage_resistances'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($stats['damage_immunities'])): ?>
            <dt>Damage Immunities</dt><dd><?= e(implode(', ', (array) $stats['damage_immunities'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($stats['condition_immunities'])): ?>
            <dt>Condition Immunities</dt><dd><?= e(implode(', ', (array) $stats['condition_immunities'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($stats['senses'])): ?>
            <dt>Senses</dt><dd><?= e($stats['senses']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($stats['languages'])): ?>
            <dt>Languages</dt><dd><?= e($stats['languages']) ?></dd>
        <?php endif; ?>
        <?php if ($monster['source']): ?>
            <dt>Source</dt><dd><?= e($monster['source']) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<?php if (!empty($stats['traits']) && is_array($stats['traits'])): ?>
    <h3>Traits</h3>
    <?php foreach ($stats['traits'] as $trait): ?>
        <p class="statblock-entry"><?= nl2br(e((string) $trait)) ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($monster['actions']): ?>
    <h3>Actions</h3>
    <?php foreach (preg_split('/\R/', trim((string) $monster['actions'])) as $action): ?>
        <?php if (trim($action) === '') continue; ?>
        <p class="statblock-entry"><?= nl2br(e($action)) ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<div id="standalone-roll-result" class="dice-result standalone-roll-result" hidden></div>

<form method="post" action="/monsters/<?= e($monster['id']) ?>/delete"
      onsubmit="return confirm('Delete this monster?')">
    <?= csrf_field() ?>
    <button type="submit" class="link danger">Delete monster</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const standaloneResult = document.getElementById('standalone-roll-result');

    document.querySelectorAll('.roll-trigger').forEach((el) => {
        el.addEventListener('click', () => {
            const expr = el.getAttribute('data-roll');
            const label = el.getAttribute('data-roll-label') || '';

            if (window.parent && window.parent !== window && typeof window.parent.trackerRollExpression === 'function') {
                window.parent.trackerRollExpression(expr, label);
                return;
            }

            // Standalone fallback (monster page opened directly, outside the encounter panel).
            fetch(`/api/dice?dice=${encodeURIComponent(expr)}`)
                .then((res) => res.json())
                .then((data) => {
                    if (!standaloneResult) return;
                    standaloneResult.hidden = false;
                    if (data.success && data.result) {
                        standaloneResult.innerHTML = `<strong>${label}:</strong> ${data.result.total} <span class="breakdown">(${data.result.breakdown})</span>`;
                    } else {
                        standaloneResult.textContent = data.error || 'Invalid roll';
                    }
                })
                .catch(() => {
                    if (standaloneResult) {
                        standaloneResult.hidden = false;
                        standaloneResult.textContent = 'Roll failed.';
                    }
                });
        });
    });
});
</script>
