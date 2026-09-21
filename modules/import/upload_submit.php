<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
require __DIR__ . '/FtkParser.php';
require __DIR__ . '/SnapshotWriter.php';

use Forsa\Database;
use Forsa\Import\FtkParser;
use Forsa\Import\SnapshotWriter;

$user = require_login();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$uploadConfig = require __DIR__ . '/../../config/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method tidak diizinkan.', 405);
}

csrf_require();

if ($action === 'preview') {
    $shapId = (int) ($_POST['shap_id'] ?? 0);
    $period = (string) ($_POST['period'] ?? '');

    if ($shapId <= 0) {
        json_error('SH/AP wajib dipilih.');
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        json_error('Periode wajib diisi (bulan & tahun).');
    }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('File .xlsx wajib diunggah.');
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $uploadConfig['allowed_extensions'], true)) {
        json_error('Hanya file .xlsx yang diperbolehkan.');
    }
    $maxBytes = $uploadConfig['max_size_mb'] * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        json_error("Ukuran file melebihi batas {$uploadConfig['max_size_mb']} MB.");
    }

    @mkdir($uploadConfig['temp_dir'], 0775, true);
    $token = bin2hex(random_bytes(16));
    $tempPath = $uploadConfig['temp_dir'] . '/' . $token . '.xlsx';

    if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
        json_error('Gagal menyimpan file sementara.');
    }

    try {
        $parser = new FtkParser();
        $result = $parser->parse($tempPath);
    } catch (\Throwable $e) {
        @unlink($tempPath);
        json_error($e->getMessage());
    }

    $_SESSION['_upload_tokens'][$token] = [
        'shap_id' => $shapId,
        'period' => $period,
        'original_filename' => $file['name'],
        'temp_path' => $tempPath,
        'file_hash' => hash_file('sha256', $tempPath),
        'created_at' => time(),
    ];

    $criticalErrors = array_values(array_filter($result['errors'], fn($e) => $e['severity'] === 'ERROR'));
    $warnings = array_values(array_filter($result['errors'], fn($e) => $e['severity'] === 'WARNING'));

    json_success([
        'token' => $token,
        'summary' => $result['summary'],
        'can_confirm' => count($criticalErrors) === 0 && $result['summary']['valid_rows'] > 0,
        'sample_rows' => array_slice($result['rows'], 0, 20),
        'errors' => array_slice($criticalErrors, 0, 100),
        'warnings' => array_slice($warnings, 0, 100),
        'error_count' => count($criticalErrors),
        'warning_count' => count($warnings),
    ]);
}

if ($action === 'confirm') {
    $token = (string) ($_POST['token'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));

    $meta = $_SESSION['_upload_tokens'][$token] ?? null;
    if (!$meta || !is_file($meta['temp_path'])) {
        json_error('Sesi upload tidak ditemukan atau sudah kedaluwarsa. Silakan unggah ulang.', 410);
    }

    $pdo = Database::connection();
    $parser = new FtkParser();

    try {
        $result = $parser->parse($meta['temp_path']);
    } catch (\Throwable $e) {
        json_error($e->getMessage());
    }

    $criticalErrors = array_values(array_filter($result['errors'], fn($e) => $e['severity'] === 'ERROR'));
    if (!empty($criticalErrors) || $result['summary']['valid_rows'] === 0) {
        json_error('Upload memiliki error kritis dan tidak dapat dikonfirmasi.');
    }

    @mkdir($uploadConfig['storage_dir'], 0775, true);
    $storedFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.xlsx';
    $storedPath = $uploadConfig['storage_dir'] . '/' . $storedFilename;
    copy($meta['temp_path'], $storedPath);

    $periodMonth = $meta['period'] . '-01';

    $insertJob = $pdo->prepare(
        'INSERT INTO forsa_import_jobs
            (shap_id, period_month, original_filename, stored_filename, stored_path, file_hash,
             status, total_rows, valid_rows, warning_rows, error_rows, note, uploaded_by, started_at, completed_at)
         VALUES
            (:shap_id, :period_month, :original_filename, :stored_filename, :stored_path, :file_hash,
             :status, :total_rows, :valid_rows, :warning_rows, :error_rows, :note, :uploaded_by, now(), now())
         RETURNING id'
    );
    $insertJob->execute([
        'shap_id' => $meta['shap_id'],
        'period_month' => $periodMonth,
        'original_filename' => $meta['original_filename'],
        'stored_filename' => $storedFilename,
        'stored_path' => $storedPath,
        'file_hash' => $meta['file_hash'],
        'status' => 'VALIDATING',
        'total_rows' => $result['summary']['total_rows'],
        'valid_rows' => $result['summary']['valid_rows'],
        'warning_rows' => $result['summary']['warning_rows'],
        'error_rows' => $result['summary']['error_rows'],
        'note' => $note !== '' ? $note : null,
        'uploaded_by' => $user['id'],
    ]);
    $jobId = $insertJob->fetch()['id'];

    if (!empty($result['errors'])) {
        $insertErr = $pdo->prepare(
            'INSERT INTO forsa_import_errors (import_job_id, row_number, column_name, severity, error_code, message, raw_value)
             VALUES (:job_id, :row_number, :column_name, :severity, :error_code, :message, :raw_value)'
        );
        foreach ($result['errors'] as $err) {
            $insertErr->execute([
                'job_id' => $jobId,
                'row_number' => $err['row_number'],
                'column_name' => $err['column_name'],
                'severity' => $err['severity'],
                'error_code' => $err['error_code'],
                'message' => $err['message'],
                'raw_value' => $err['raw_value'],
            ]);
        }
    }

    try {
        $writer = new SnapshotWriter();
        $snapshotId = $writer->write((int) $meta['shap_id'], $periodMonth, $jobId, $result['rows'], (int) $user['id']);
        $pdo->prepare("UPDATE forsa_import_jobs SET status = 'IMPORTED' WHERE id = :id")->execute(['id' => $jobId]);
    } catch (\Throwable $e) {
        $pdo->prepare("UPDATE forsa_import_jobs SET status = 'FAILED' WHERE id = :id")->execute(['id' => $jobId]);
        json_error('Gagal menyimpan snapshot: ' . $e->getMessage());
    }

    @unlink($meta['temp_path']);
    unset($_SESSION['_upload_tokens'][$token]);

    audit_log((int) $user['id'], 'IMPORT_SNAPSHOT', 'forsa_ftk_snapshots', $snapshotId, null, [
        'shap_id' => $meta['shap_id'],
        'period_month' => $periodMonth,
        'valid_rows' => $result['summary']['valid_rows'],
    ]);

    json_success([
        'snapshot_id' => $snapshotId,
        'job_id' => $jobId,
        'period_month' => $periodMonth,
    ], 'Data berhasil diimpor sebagai snapshot baru.');
}

json_error('Aksi tidak dikenali.', 400);
