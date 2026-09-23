<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    // Never logged, never sent to the client, never embedded in a prompt —
    // read only by providers/OpenAiProvider.php's HTTP call (PRD §4.2/§15.1
    // "no DB credential in prompt" applies equally to the model credential
    // itself).
    'api_key' => env('MODEL_API_KEY', ''),
    'model' => env('MODEL_NAME', 'gpt-4o-mini'),
    'base_url' => env('MODEL_BASE_URL', 'https://api.openai.com/v1'),
    'timeout_seconds' => 20,

    // Dedicated read-only DB role for QueryExecutor (PRD §4.3/§15.1) — see
    // database/migrations/009_create_ai_reader_role.sql. Host/port/database
    // are shared with config/database.php (same Postgres instance, just a
    // different, far more restricted role).
    'db_username' => env('AI_DB_USERNAME', 'forsa_ai_reader'),
    'db_password' => env('AI_DB_PASSWORD', ''),
];
