<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
$currentUser = require_login();
csrf_require();

use Forsa\Database;

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    json_error('User tidak valid.');
}

$pdo = Database::connection();
$check = $pdo->prepare('SELECT id FROM forsa_users WHERE id = :id');
$check->execute(['id' => $id]);
if (!$check->fetch()) {
    json_error('User tidak ditemukan.', 404);
}

$newPassword = substr(bin2hex(random_bytes(6)), 0, 10);
$pdo->prepare('UPDATE forsa_users SET password_hash = :hash, updated_at = now() WHERE id = :id')
    ->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $id]);

audit_log((int) $currentUser['id'], 'USER_RESET_PASSWORD', 'forsa_users', (string) $id);

json_success(['new_password' => $newPassword], 'Password berhasil direset.');
