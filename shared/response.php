<?php

declare(strict_types=1);

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success(array $data = [], string $message = ''): never
{
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Appends a cache-busting "?v=<mtime>" query string to a static asset path
 * (e.g. "assets/css/forsa.css"), so browsers fetch a fresh copy the moment
 * the file changes on disk instead of serving a stale cached version
 * indefinitely (Apache/MAMP doesn't send any cache-control headers for
 * static files here, so browsers are free to cache "forever" on their own
 * heuristics — this was confirmed to cause real, repeated confusion during
 * manual testing, where CSS edits didn't take effect even after a hard
 * refresh). Falls back to the bare path if the file can't be stat'd.
 */
function asset_url(string $relativePath): string
{
    $fullPath = dirname(__DIR__) . '/public/' . ltrim($relativePath, '/');
    $mtime = @filemtime($fullPath);
    return $relativePath . ($mtime !== false ? '?v=' . $mtime : '');
}
