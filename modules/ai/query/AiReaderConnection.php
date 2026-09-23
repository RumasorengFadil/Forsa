<?php

declare(strict_types=1);

namespace Forsa\Ai\Query;

use PDO;

/**
 * Separate PDO singleton from Forsa\Database — connects as the dedicated
 * `forsa_ai_reader` Postgres role (SELECT-only on exactly the 3 approved
 * tables, see database/migrations/009_create_ai_reader_role.sql), never the
 * app's own DB_USERNAME. This is the actual enforcement of PRD §4.3 "AI
 * Analytics Service menggunakan database role tersendiri" — QueryBuilder's
 * whitelisting is defense layer one, this connection is defense layer two:
 * even a bug that let an unapproved table name through would still fail at
 * the database with "permission denied", not silently succeed.
 */
final class AiReaderConnection
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dbConfig = require dirname(__DIR__, 3) . '/config/database.php';
        $aiConfig = require dirname(__DIR__, 3) . '/config/ai.php';

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);

        self::$instance = new PDO($dsn, $aiConfig['db_username'], $aiConfig['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $schema = (string) $dbConfig['schema'];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schema)) {
            throw new \RuntimeException("DB_SCHEMA tidak valid: \"{$schema}\".");
        }
        self::$instance->exec("SET search_path TO {$schema}, public");

        return self::$instance;
    }
}
