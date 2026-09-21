<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
require_login();

use Forsa\Dashboard\DashboardService;
use Forsa\Ftk\TreeService;

$selection = isset($_GET['selection']) ? (string) $_GET['selection'] : null;
$shapCode = isset($_GET['shap']) ? (string) $_GET['shap'] : null;
$pathJson = isset($_GET['path_json']) ? (string) $_GET['path_json'] : '[]';
$path = json_decode($pathJson, true);
if (!is_array($path)) {
    $path = [];
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'job_level_group' => trim((string) ($_GET['job_level_group'] ?? '')),
    'position_grade' => trim((string) ($_GET['position_grade'] ?? '')),
    'gap_status' => trim((string) ($_GET['gap_status'] ?? '')),
];

$dashboardService = new DashboardService();
$resolved = $dashboardService->resolveSnapshots($selection);

$treeService = new TreeService();

if (!$shapCode) {
    json_success(['nodes' => $treeService->rootNodes($resolved['rows'], $filters)]);
}

$snapshot = null;
foreach ($resolved['rows'] as $s) {
    if ($s['shap_code'] === $shapCode) {
        $snapshot = $s;
        break;
    }
}

if (!$snapshot) {
    json_error('Snapshot SH/AP tidak ditemukan pada selection ini.', 404);
}

json_success(['nodes' => $treeService->children($snapshot['id'], $path, $filters)]);
