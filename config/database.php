<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forsa'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    // Schema PostgreSQL tempat seluruh tabel forsa_* berada (lihat
    // "CREATE SCHEMA IF NOT EXISTS forsa" di database/migrations). Falls
    // back ke "forsa" kalau DB_SCHEMA tidak diisi di .env, atau diisi
    // string kosong.
    'schema' => env('DB_SCHEMA') ?: 'forsa',
];
