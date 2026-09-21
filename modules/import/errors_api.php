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
    'SELECT row_number, column_name, severity, error_code, message, raw_value
     FROM forsa_import_errors WHERE import_job_id = :id ORDER BY row_number LIMIT 500'
);
$stmt->execute(['id' => $jobId]);

json_success(['rows' => $stmt->fetchAll()]);
