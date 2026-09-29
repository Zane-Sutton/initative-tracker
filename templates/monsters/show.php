<?php

use function App\csrf_field;
use function App\e;
use function App\pretty_json;
use function App\signed;

/** @var array<string,mixed> $monster */
$subtitle = trim(implode(' ', array_filter([$monster['size'], $monster['creature_type']])));
$hp = (string) $monster['hp_average'] . ($monster['hp_formula'] ? ' (' . $monster['hp_formula'] . ')' : '');
?>
<div class="toolbar">
    <h2><?= e($monster['name']) ?></h2>
    <div>
        <a class="button" href="/monsters/<?= e($monster['id']) ?>/edit">Edit</a>
        <a href="/monsters">Back to list</a>
    </div>
</div>

<?php if ($subtitle !== ''): ?><p class="subtitle"><?= e($subtitle) ?></p><?php endif; ?>

<dl class="statblock">
    <dt>Armor class</dt><dd><?= e($monster['armor_class']) ?></dd>
    <dt>Hit points</dt><dd><?= e($hp) ?></dd>
    <dt>Initiative</dt><dd><?= e(signed($monster['initiative_bonus'])) ?></dd>
    <?php if ($monster['speed']): ?><dt>Speed</dt><dd><?= e($monster['speed']) ?></dd><?php endif; ?>
    <?php if ($monster['challenge_rating']): ?><dt>Challenge</dt><dd><?= e($monster['challenge_rating']) ?></dd><?php endif; ?>
    <?php if ($monster['source']): ?><dt>Source</dt><dd><?= e($monster['source']) ?></dd><?php endif; ?>
</dl>

<?php if ($monster['actions']): ?>
    <h3>Actions</h3>
    <p><?= nl2br(e($monster['actions'])) ?></p>
<?php endif; ?>

<?php if ($monster['stats']): ?>
    <h3>Stats</h3>
    <pre><?= e(pretty_json($monster['stats'])) ?></pre>
<?php endif; ?>

<form method="post" action="/monsters/<?= e($monster['id']) ?>/delete"
      onsubmit="return confirm('Delete this monster?')">
    <?= csrf_field() ?>
    <button type="submit" class="link danger">Delete monster</button>
</form>
