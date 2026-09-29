<?php

declare(strict_types=1);

/**
 * Applies any unapplied migrations/*.sql files in filename order.
 * Usage: php bin/migrate.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Database;

$config = require dirname(__DIR__) . '/config/config.php';
Database::configure($config['db']);

$pdo = Database::make(emulatePrepares: true);

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(dirname(__DIR__) . '/migrations/*.sql');
sort($files);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Applying $name ... ";
    try {
        // Note: MariaDB DDL auto-commits, so this is not transactional.
        $pdo->exec(file_get_contents($file));
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $stmt->execute([$name]);
        echo "done\n";
        $ran++;
    } catch (Throwable $e) {
        echo "FAILED\n" . $e->getMessage() . "\n";
        exit(1);
    }
}

echo $ran === 0 ? "Nothing to migrate.\n" : "Applied $ran migration(s).\n";
