<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../shared/Database.php';

use Forsa\Database;

$name = $argv[1] ?? 'Super Admin';
$email = $argv[2] ?? 'admin@forsa.local';
$password = $argv[3] ?? 'forsa123';

$pdo = Database::connection();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "INSERT INTO forsa_users (name, email, password_hash, is_active)
     VALUES (:name, :email, :hash, true)
     ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash, is_active = true
     RETURNING id"
);
$stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash]);
$userId = $stmt->fetch()['id'];

$roleId = $pdo->query("SELECT id FROM forsa_roles WHERE code = 'SUPER_ADMIN'")->fetch()['id'];

$pdo->prepare('INSERT INTO forsa_user_roles (user_id, role_id) VALUES (:u, :r) ON CONFLICT DO NOTHING')
    ->execute(['u' => $userId, 'r' => $roleId]);

echo "Seeded user #{$userId} ({$email}) with role SUPER_ADMIN\n";
