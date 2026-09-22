<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';

$user = current_user();
if ($user) {
    audit_log((int) $user['id'], 'LOGOUT', 'forsa_users', (string) $user['id']);
}

logout_user();
redirect('/login');
