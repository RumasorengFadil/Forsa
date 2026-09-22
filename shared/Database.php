<?php

declare(strict_types=1);

namespace Forsa;

use PDO;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $config = require dirname(__DIR__) . '/config/database.php';

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database']
        );

        self::$instance = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Schema name can't be bound as a PDO parameter in a SET command, so
        // it's validated against a strict identifier pattern instead
        // (config-only value, but a malformed DB_SCHEMA should fail loudly
        // rather than silently break every query or open a SQL injection
        // path).
        $schema = (string) $config['schema'];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
            throw new \RuntimeException("DB_SCHEMA tidak valid: \"{$schema}\". Hanya huruf, angka, dan underscore (tidak diawali angka) yang diperbolehkan.");
        }
        self::$instance->exec("SET search_path TO {$schema}, public");

        return self::$instance;
    }
}
