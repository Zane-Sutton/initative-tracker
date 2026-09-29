<?php

use function App\csrf_field;
use function App\e;
use function App\signed;

/** @var list<array<string,mixed>> $characters */
/** @var string $q */
?>
<div class="toolbar">
    <h2>Characters</h2>
    <a class="button" href="/characters/new">New character</a>
</div>

<form method="get" action="/characters" class="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a href="/characters">Clear</a><?php endif; ?>
</form>

<?php if ($characters === []): ?>
    <p class="empty">No characters found.</p>
<?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Player</th><th>Class</th><th>Lvl</th>
                <th>AC</th><th>Max HP</th><th>Init</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($characters as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['player_name']) ?></td>
                <td><?= e($c['class']) ?></td>
                <td><?= e($c['level']) ?></td>
                <td><?= e($c['armor_class']) ?></td>
                <td><?= e($c['max_hp']) ?></td>
                <td><?= e(signed($c['initiative_bonus'])) ?></td>
                <td class="row-actions">
                    <a href="/characters/<?= e($c['id']) ?>/edit">Edit</a>
                    <form method="post" action="/characters/<?= e($c['id']) ?>/delete" class="inline"
                          onsubmit="return confirm('Delete this character?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="link danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
