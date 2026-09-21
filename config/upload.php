<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'max_size_mb' => (int) env('UPLOAD_MAX_SIZE_MB', 15),
    'allowed_extensions' => ['xlsx'],
    'allowed_mime_types' => [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ],
    'storage_dir' => dirname(__DIR__) . '/storage/uploads',
    'temp_dir' => dirname(__DIR__) . '/storage/temp',
];
