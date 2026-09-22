<?php

declare(strict_types=1);

/**
 * Resolve the PLN brand logo to a published /assets/img/... URL if the asset
 * exists on disk, so dropping the real file in place activates it with no
 * further code change. Falls back to the plain "F" badge when no asset has
 * been provided yet.
 */
function forsa_brand_logo_url(): ?string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved ?: null;
    }

    // The square "mark" crop is preferred for the compact navbar badge; the
    // plain "pln-logo.*" files (full lockup, uncropped) are a fallback so any
    // asset dropped in under that name still shows up.
    $candidates = ['pln-logo-mark.svg', 'pln-logo-mark.png', 'pln-logo-mark.webp', 'pln-logo.svg', 'pln-logo.png', 'pln-logo.webp'];
    foreach ($candidates as $file) {
        if (is_file(dirname(__DIR__) . '/public/assets/img/' . $file)) {
            $resolved = 'assets/img/' . $file;
            return $resolved;
        }
    }

    $resolved = false;
    return null;
}

function render_topbar(array $user, string $active = 'dashboard'): void
{
    $logoUrl = forsa_brand_logo_url();
?>
    <div class="topbar">
        <div class="topbar-brand">
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="Logo PLN" class="badge badge-logo">
            <?php else: ?>
                <span class="badge">F</span>
            <?php endif; ?>
            <span>FORSA PLN</span>
        </div>
        <nav class="topbar-nav">
            <a href="dashboard" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="users" class="<?= $active === 'users' ? 'active' : '' ?>">Manajemen User</a>
        </nav>
        <div class="topbar-user">
            <span><?= e($user['name']) ?></span>
            <a href="logout.php" class="btn btn-secondary btn-sm">Keluar</a>
        </div>
    </div>
<?php
}
