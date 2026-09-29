<?php

use function App\e;

/** @var bool $dbOk */
/** @var array<string,int> $counts */
/** @var string|null $error */
?>
<?php if ($dbOk): ?>
    <p>Database connection OK.</p>
    <ul>
        <?php foreach ($counts as $table => $count): ?>
            <li><a href="/<?= e($table) ?>"><strong><?= e(ucfirst($table)) ?>:</strong> <?= e($count) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="error">Database connection failed. Have you run <code>php bin/migrate.php</code>?</p>
    <?php if ($error): ?><pre><?= e($error) ?></pre><?php endif; ?>
<?php endif; ?>
