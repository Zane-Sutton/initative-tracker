<?php

use function App\csrf_field;
use function App\e;
use function App\signed;

/** @var list<array<string,mixed>> $monsters */
/** @var string $q */
?>
<div class="toolbar">
    <h2>Monsters</h2>
    <a class="button" href="/monsters/new">New monster</a>
</div>

<form method="get" action="/monsters" class="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a href="/monsters">Clear</a><?php endif; ?>
</form>

<?php if ($monsters === []): ?>
    <p class="empty">No monsters found.</p>
<?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Type</th><th>Size</th><th>CR</th>
                <th>AC</th><th>HP</th><th>Init</th><th>Source</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($monsters as $m): ?>
            <tr>
                <td><a href="/monsters/<?= e($m['id']) ?>"><?= e($m['name']) ?></a></td>
                <td><?= e($m['creature_type']) ?></td>
                <td><?= e($m['size']) ?></td>
                <td><?= e($m['challenge_rating']) ?></td>
                <td><?= e($m['armor_class']) ?></td>
                <td><?= e($m['hp_average']) ?></td>
                <td><?= e(signed($m['initiative_bonus'])) ?></td>
                <td><?= e($m['source']) ?></td>
                <td class="row-actions">
                    <a href="/monsters/<?= e($m['id']) ?>/edit">Edit</a>
                    <form method="post" action="/monsters/<?= e($m['id']) ?>/delete" class="inline"
                          onsubmit="return confirm('Delete this monster?')">
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
