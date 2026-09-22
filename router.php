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

// Legacy ".php" page URLs (e.g. the old /dashboard.php) still work — they
// 301-redirect to the clean URL instead of 404ing, so old bookmarks/links
// keep navigating correctly. Only real *pages* are listed here; POST/JSON
// action endpoints never renamed their ".php" suffix, so they need no entry.
$legacyPageRedirects = [
    'index.php' => '/',
    'login.php' => '/login',
    'dashboard.php' => '/dashboard',
    'users.php' => '/users',
];

if (isset($legacyPageRedirects[$scriptName])) {
    $target = $legacyPageRedirects[$scriptName];
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $target . ($queryString !== '' ? '?' . $queryString : ''), true, 301);
    return true;
}

if ($scriptName === '') {
    $scriptName = 'login';
}

if (isset($routeMap[$scriptName])) {
    require __DIR__ . $routeMap[$scriptName];
    return true;
}

http_response_code(404);
echo '404 — Halaman tidak ditemukan.';
return true;
