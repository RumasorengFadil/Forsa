<?php

declare(strict_types=1);

// Keys are the clean request path (no leading slash). Browser-navigable pages
// use clean URLs (no ".php"); POST/JSON action & API endpoints keep their
// ".php" suffix since they are never typed/bookmarked as a page — see
// router.php's $legacyPageRedirects for the old-URL -> clean-URL 301s.
return [
    'login' => '/modules/auth/login.php',
    'login_submit.php' => '/modules/auth/login_submit.php',
    'logout.php' => '/modules/auth/logout.php',

    'dashboard' => '/modules/dashboard/dashboard.php',
    'dashboard_api.php' => '/modules/dashboard/dashboard_api.php',
    'history_api.php' => '/modules/dashboard/history_api.php',
    'insight_api.php' => '/modules/dashboard/insight_api.php',

    'upload_submit.php' => '/modules/import/upload_submit.php',
    'upload_history_api.php' => '/modules/import/history_api.php',
    'upload_detail_api.php' => '/modules/import/detail_api.php',
    'upload_errors_api.php' => '/modules/import/errors_api.php',

    'ftk_tree_api.php' => '/modules/ftk/ftk_tree_api.php',
    'ftk_search_api.php' => '/modules/ftk/ftk_search_api.php',

    'users' => '/modules/administrasi/users.php',
    'user_create.php' => '/modules/administrasi/user_create.php',
    'user_edit.php' => '/modules/administrasi/user_edit.php',
    'user_toggle_status.php' => '/modules/administrasi/user_toggle_status.php',
    'user_reset_password.php' => '/modules/administrasi/user_reset_password.php',
];
