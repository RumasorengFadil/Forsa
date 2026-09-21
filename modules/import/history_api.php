<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
require_login();

use Forsa\Database;

$pdo = Database::connection();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$shapFilter = trim((string) ($_GET['shap_id'] ?? ''));
$where = [];
$params = [];
if ($shapFilter !== '') {
    $where[] = 'j.shap_id = :shap_id';
    $params['shap_id'] = (int) $shapFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM forsa_import_jobs j {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$stmt = $pdo->prepare(
    "SELECT j.id, j.period_month, j.original_filename, j.status, j.total_rows, j.valid_rows,
            j.warning_rows, j.error_rows, j.note, j.created_at,
            e.short_name AS shap_short_name, e.name AS shap_name,
            u.name AS uploaded_by_name,
            s.id AS snapshot_id, s.revision_no, s.is_active, s.total_ftk, s.total_realisasi
     FROM forsa_import_jobs j
     JOIN forsa_shap_entities e ON e.id = j.shap_id
     JOIN forsa_users u ON u.id = j.uploaded_by
     LEFT JOIN forsa_ftk_snapshots s ON s.import_job_id = j.id
     {$whereSql}
     ORDER BY j.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

json_success([
    'rows' => $rows,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => (int) ceil($total / $perPage),
]);
