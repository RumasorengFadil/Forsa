<?php

declare(strict_types=1);

// Front controller used with the PHP built-in server:
//   php -S 0.0.0.0:8080 router.php
// and also compatible with Apache's mod_rewrite via .htaccess.

// Apache (.htaccess) hands us the route relative to the app's own directory
// via _forsa_route, already stripped of any subfolder prefix (e.g. /Forsa)
// by mod_rewrite's per-directory matching. The PHP built-in server (php -S)
// never sets this -- it always serves this project as the document root, so
// REQUEST_URI is already app-relative there.
$hasApacheRoute = isset($_GET['_forsa_route']);
$uri = $hasApacheRoute
    ? '/' . ltrim((string) $_GET['_forsa_route'], '/')
    : (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Serve static assets directly (php -S only -- under Apache, .htaccess maps
// /assets/* straight to public/assets/* before router.php is ever reached).
if (!$hasApacheRoute && str_starts_with($uri, '/assets/')) {
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
// Targets are relative (no leading slash) so the 301 lands on the right page
// whether the app is served from the web root or a subfolder (e.g. /Forsa) —
// the browser resolves a relative Location against the request's own
// directory, which is the app root either way since these are all one-level
// clean URLs.
$legacyPageRedirects = [
    'index.php' => 'login',
    'login.php' => 'login',
    'dashboard.php' => 'dashboard',
    'users.php' => 'users',
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
