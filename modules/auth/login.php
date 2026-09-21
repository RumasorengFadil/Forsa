<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$messages = flash_all();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — FORSA</title>
<link rel="stylesheet" href="/assets/css/forsa.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">F</div>
            <div>
                <h1>FORSA</h1>
                <p>Formasi &amp; Realisasi SH/AP</p>
            </div>
        </div>

        <?php foreach ($messages as $m): ?>
            <div class="alert alert-<?= e($m['type']) ?>"><?= e($m['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/login_submit.php" class="login-form">
            <?= csrf_field() ?>
            <label>Email
                <input type="email" name="email" required autofocus placeholder="admin@forsa.local">
            </label>
            <label>Password
                <input type="password" name="password" required placeholder="••••••••">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>
        <p class="login-hint">Hubungi Super Admin jika Anda belum memiliki akun.</p>
    </div>
</div>
</body>
</html>
