<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'name' => env('APP_NAME', 'FORSA'),
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'session_name' => env('SESSION_NAME', 'forsa_session'),
    'timezone' => 'Asia/Jakarta',
];
