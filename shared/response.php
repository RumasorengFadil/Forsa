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
