<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
$currentUser = require_login();
csrf_require();

use Forsa\Database;

$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($id <= 0 || $name === '' || $email === '') {
    json_error('Data tidak lengkap.');
}

$pdo = Database::connection();
$exists = $pdo->prepare('SELECT id FROM forsa_users WHERE email = :email AND id != :id');
$exists->execute(['email' => $email, 'id' => $id]);
if ($exists->fetch()) {
    json_error('Email sudah digunakan user lain.');
}

$before = $pdo->prepare('SELECT name, email FROM forsa_users WHERE id = :id');
$before->execute(['id' => $id]);
$old = $before->fetch();
if (!$old) {
    json_error('User tidak ditemukan.', 404);
}

$pdo->prepare('UPDATE forsa_users SET name = :name, email = :email, updated_at = now() WHERE id = :id')
    ->execute(['name' => $name, 'email' => $email, 'id' => $id]);

audit_log((int) $currentUser['id'], 'USER_UPDATE', 'forsa_users', (string) $id, $old, ['name' => $name, 'email' => $email]);

json_success([], 'Data user berhasil diperbarui.');
