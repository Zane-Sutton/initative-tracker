<?php

use function App\csrf_field;
use function App\e;

/** @var list<array<string,mixed>> $encounters */
/** @var string $q */
?>
<div class="toolbar">
    <h2>Encounters</h2>
    <a class="button" href="/encounters/new">New encounter</a>
</div>

<form method="get" action="/encounters" class="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name">
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?><a href="/encounters">Clear</a><?php endif; ?>
</form>

<?php if ($encounters === []): ?>
    <p class="empty">No encounters found.</p>
<?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Round</th>
                <th>Combatants</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($encounters as $enc): ?>
            <tr>
                <td>
                    <strong><a href="/encounters/<?= e($enc['id']) ?>"><?= e($enc['name']) ?></a></strong>
                    <?php if (!empty($enc['notes'])): ?>
                        <div class="subtitle" style="font-size: 0.85rem;"><?= e(mb_strimwidth((string)$enc['notes'], 0, 60, '...')) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= e($enc['status']) ?>">
                        <?= e(ucfirst((string)$enc['status'])) ?>
                    </span>
                </td>
                <td><?= (int)$enc['current_round'] > 0 ? 'Round ' . e($enc['current_round']) : '—' ?></td>
                <td>
                    <?= (int)($enc['participant_count'] ?? 0) ?> total
                    <?php if ((int)($enc['active_count'] ?? 0) > 0 && $enc['status'] === 'active'): ?>
                        (<?= (int)$enc['active_count'] ?> active)
                    <?php endif; ?>
                </td>
                <td class="row-actions">
                    <a class="button button-small" href="/encounters/<?= e($enc['id']) ?>">Open tracker</a>
                    <a href="/encounters/<?= e($enc['id']) ?>/edit">Edit</a>
                    <form method="post" action="/encounters/<?= e($enc['id']) ?>/delete" class="inline"
                          onsubmit="return confirm('Delete this encounter?')">
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
