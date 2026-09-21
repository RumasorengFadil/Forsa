<?php

declare(strict_types=1);

function render_topbar(array $user, string $active = 'dashboard'): void
{
    ?>
    <div class="topbar">
        <div class="topbar-brand">
            <span class="badge">F</span>
            <span>FORSA</span>
        </div>
        <nav class="topbar-nav">
            <a href="/dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="/users.php" class="<?= $active === 'users' ? 'active' : '' ?>">Manajemen User</a>
        </nav>
        <div class="topbar-user">
            <span><?= e($user['name']) ?></span>
            <a href="/logout.php" class="btn btn-secondary btn-sm">Keluar</a>
        </div>
    </div>
    <?php
}
