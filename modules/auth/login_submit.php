<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';

use Forsa\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login');
}

if (!csrf_verify($_POST['_csrf'] ?? null)) {
    flash_set('error', 'Sesi form kedaluwarsa, silakan coba lagi.');
    redirect('/login');
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    flash_set('error', 'Email dan password wajib diisi.');
    redirect('/login');
}

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_active FROM forsa_users WHERE email = :email');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    audit_log(null, 'LOGIN_FAILED', 'forsa_users', $email);
    flash_set('error', 'Email atau password salah.');
    redirect('/login');
}

if (!$user['is_active']) {
    flash_set('error', 'Akun Anda dinonaktifkan. Hubungi Super Admin.');
    redirect('/login');
}

$roles = $pdo->prepare(
    'SELECT r.code FROM forsa_roles r
     JOIN forsa_user_roles ur ON ur.role_id = r.id
     WHERE ur.user_id = :id'
);
$roles->execute(['id' => $user['id']]);
$roleCodes = array_column($roles->fetchAll(), 'code');

login_user([
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'roles' => $roleCodes ?: ['SUPER_ADMIN'],
]);

$pdo->prepare('UPDATE forsa_users SET last_login_at = now() WHERE id = :id')->execute(['id' => $user['id']]);
audit_log((int) $user['id'], 'LOGIN', 'forsa_users', (string) $user['id']);

redirect('/dashboard');
