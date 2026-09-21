<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
require_login();

use Forsa\Database;

$jobId = (string) ($_GET['job_id'] ?? '');
if ($jobId === '') {
    json_error('job_id wajib diisi.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare(
    'SELECT j.*, e.short_name AS shap_short_name, u.name AS uploaded_by_name,
            s.id AS snapshot_id, s.revision_no, s.is_active
     FROM forsa_import_jobs j
     JOIN forsa_shap_entities e ON e.id = j.shap_id
     JOIN forsa_users u ON u.id = j.uploaded_by
     LEFT JOIN forsa_ftk_snapshots s ON s.import_job_id = j.id
     WHERE j.id = :id'
);
$stmt->execute(['id' => $jobId]);
$job = $stmt->fetch();

if (!$job) {
    json_error('Data upload tidak ditemukan.', 404);
}

json_success(['job' => $job]);
