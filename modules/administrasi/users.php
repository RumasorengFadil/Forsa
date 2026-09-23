<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
$currentUser = require_login();
require __DIR__ . '/../../shared/menu.php';

use Forsa\Database;

$pdo = Database::connection();
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE u.name ILIKE :q OR u.email ILIKE :q';
    $params['q'] = '%' . $search . '%';
}

$total = (int) (function () use ($pdo, $where, $params) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM forsa_users u {$where}");
    $stmt->execute($params);
    return $stmt->fetch()['c'];
})();

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.is_active, u.last_login_at, u.created_at,
            COALESCE(string_agg(r.name, ', '), '-') AS roles
     FROM forsa_users u
     LEFT JOIN forsa_user_roles ur ON ur.user_id = u.id
     LEFT JOIN forsa_roles r ON r.id = ur.role_id
     {$where}
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$users = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));

$messages = flash_all();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen User — FORSA</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/forsa.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php render_topbar($currentUser, 'users'); ?>
    <div class="page-wrap">
        <div class="dash-header">
            <div class="dash-title"><h2>Manajemen User</h2></div>
            <div class="dash-controls">
                <button class="btn btn-primary" id="btn-new-user">+ Tambah User</button>
            </div>
        </div>

        <?php foreach ($messages as $m): ?>
            <div class="alert alert-<?= e($m['type']) ?>"><?= e($m['message']) ?></div>
        <?php endforeach; ?>

        <div class="card">
            <form method="get" style="margin-bottom:14px; display:flex; gap:10px;">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama / email..." style="flex:1; max-width:320px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font:inherit;">
                <button class="btn btn-secondary" type="submit">Cari</button>
            </form>

            <table class="history-table">
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['roles']) ?></td>
                        <td>
                            <span class="status-pill <?= $u['is_active'] ? 'status-IMPORTED' : 'status-ARCHIVED' ?>">
                                <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td><?= $u['last_login_at'] ? e(date('d M Y H:i', strtotime($u['last_login_at']))) : '-' ?></td>
                        <td style="display:flex; gap:6px;">
                            <button class="btn btn-secondary btn-sm btn-edit-user"
                                data-id="<?= (int) $u['id'] ?>" data-name="<?= e($u['name']) ?>" data-email="<?= e($u['email']) ?>">Edit</button>
                            <button class="btn btn-secondary btn-sm btn-toggle-user" data-id="<?= (int) $u['id'] ?>" data-active="<?= $u['is_active'] ? 1 : 0 ?>">
                                <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                            <button class="btn btn-danger btn-sm btn-reset-user" data-id="<?= (int) $u['id'] ?>">Reset Password</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--ink-soft); padding:24px;">Tidak ada data user.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>"><button class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></button></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modal-user">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 id="user-modal-title">Tambah User</h3>
            <button class="modal-close" data-close>&times;</button>
        </div>
        <div class="modal-body">
            <form id="form-user">
                <input type="hidden" name="id" id="user-id">
                <div class="form-field" style="margin-bottom:12px;">
                    <label>Nama <span class="req">*</span></label>
                    <input type="text" name="name" id="user-name" required>
                </div>
                <div class="form-field" style="margin-bottom:12px;">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" id="user-email" required>
                </div>
                <div class="form-field" id="user-password-field" style="margin-bottom:12px;">
                    <label>Password <span class="req">*</span></label>
                    <input type="password" name="password" id="user-password" minlength="6">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-close>Batal</button>
            <button class="btn btn-primary" id="btn-save-user">Simpan</button>
        </div>
    </div>
</div>

<script>const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(asset_url('assets/js/users.js')) ?>"></script>
</body>
</html>
