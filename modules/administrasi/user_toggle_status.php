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
if ($id === (int) $currentUser['id']) {
    json_error('Anda tidak dapat menonaktifkan akun sendiri.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT is_active FROM forsa_users WHERE id = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('User tidak ditemukan.', 404);
}

$newStatus = !$row['is_active'];
$pdo->prepare('UPDATE forsa_users SET is_active = :s, updated_at = now() WHERE id = :id')
    ->execute(['s' => $newStatus, 'id' => $id]);

audit_log((int) $currentUser['id'], $newStatus ? 'USER_ACTIVATE' : 'USER_DEACTIVATE', 'forsa_users', (string) $id, ['is_active' => $row['is_active']], ['is_active' => $newStatus]);

json_success(['is_active' => $newStatus], 'Status user berhasil diperbarui.');
