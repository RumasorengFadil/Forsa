<?php

declare(strict_types=1);

// Front controller used with the PHP built-in server:
//   php -S 0.0.0.0:8080 router.php
// and also compatible with Apache's mod_rewrite via .htaccess.

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Serve static assets directly.
if (str_starts_with($uri, '/assets/')) {
    $filePath = __DIR__ . '/public' . $uri;
    if (is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($filePath);
        return true;
    }
    http_response_code(404);
    return true;
}

$routeMap = require __DIR__ . '/route_map.php';

$scriptName = ltrim($uri, '/');
if ($scriptName === '') {
    $scriptName = 'index.php';
}

if (isset($routeMap[$scriptName])) {
    require __DIR__ . $routeMap[$scriptName];
    return true;
}

http_response_code(404);
echo '404 — Halaman tidak ditemukan.';
return true;
