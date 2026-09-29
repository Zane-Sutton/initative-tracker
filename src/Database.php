<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    /** @var array{host:string,port:int,name:string,user:string,pass:string}|null */
    private static ?array $config = null;

    /**
     * Configure the database connection settings.
     *
     * @param array $config An associative array containing the database configuration settings,
     *                      including 'host', 'port', 'name', 'user', and 'pass'.
     * @return void
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$connection = null;
    }

    /** Shared connection with real prepared statements. */
    public static function connection(): PDO
    {
        return self::$connection ??= self::make(emulatePrepares: false);
    }

    /**
     * Build a new PDO connection. Migrations use emulated prepares so a single
     * .sql file containing several statements can be run with exec().
     */
    public static function make(bool $emulatePrepares = false): PDO
    {
        if (self::$config === null) {
            throw new \LogicException('Database::configure() must be called first.');
        }

        $c = self::$config;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);

        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => $emulatePrepares,
        ]);
    }
}
