<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
$currentUser = require_login();
csrf_require();

use Forsa\Database;

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($name === '' || $email === '' || strlen($password) < 6) {
    json_error('Nama, email wajib diisi dan password minimal 6 karakter.');
}

$pdo = Database::connection();
$exists = $pdo->prepare('SELECT id FROM forsa_users WHERE email = :email');
$exists->execute(['email' => $email]);
if ($exists->fetch()) {
    json_error('Email sudah digunakan.');
}

$stmt = $pdo->prepare(
    'INSERT INTO forsa_users (name, email, password_hash, is_active) VALUES (:name, :email, :hash, true) RETURNING id'
);
$stmt->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
$newId = $stmt->fetch()['id'];

$roleId = $pdo->query("SELECT id FROM forsa_roles WHERE code = 'SUPER_ADMIN'")->fetch()['id'];
$pdo->prepare('INSERT INTO forsa_user_roles (user_id, role_id) VALUES (:u, :r)')->execute(['u' => $newId, 'r' => $roleId]);

audit_log((int) $currentUser['id'], 'USER_CREATE', 'forsa_users', (string) $newId, null, ['name' => $name, 'email' => $email]);

json_success(['id' => $newId], 'User berhasil ditambahkan.');
