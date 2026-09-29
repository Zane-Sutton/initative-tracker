<?php

declare(strict_types=1);

/**
 * Loads .env (simple KEY=VALUE lines) and returns the app config array.
 */
$envFile = dirname(__DIR__) . '/.env';
$env = [];

if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

return [
    'debug' => filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($env['DB_PORT'] ?? 3306),
        'name' => $env['DB_NAME'] ?? 'dnd_tracker',
        'user' => $env['DB_USER'] ?? 'root',
        'pass' => $env['DB_PASS'] ?? '',
    ],
];
