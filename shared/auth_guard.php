<?php

declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        // Use REQUEST_URI (the real requested path) rather than SCRIPT_NAME —
        // under the router.php front controller, SCRIPT_NAME is always
        // "/router.php" regardless of which page/endpoint was requested.
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_ends_with($requestPath, '_api.php')) {
            json_error('Sesi berakhir, silakan login kembali.', 401);
        }
        redirect('login');
    }
    return $user;
}

function login_user(array $userRow): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $userRow['id'],
        'name' => $userRow['name'],
        'email' => $userRow['email'],
        'roles' => $userRow['roles'] ?? ['SUPER_ADMIN'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
