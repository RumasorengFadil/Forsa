<?php

declare(strict_types=1);

// CLI helper to import a FTK template without going through the web UI.
// Usage: php tools/import_ftk_cli.php <shap_code> <YYYY-MM> <path/to/file.xlsx> [uploaded_by_user_id]

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../shared/Database.php';
require __DIR__ . '/../shared/validation.php';
require __DIR__ . '/../shared/JobLevelMapper.php';
require __DIR__ . '/../modules/import/FtkParser.php';
require __DIR__ . '/../modules/import/SnapshotWriter.php';

use Forsa\Database;
use Forsa\Import\FtkParser;
use Forsa\Import\SnapshotWriter;

[$script, $shapCode, $period, $filePath, $userId] = array_pad($argv, 5, null);

if (!$shapCode || !$period || !$filePath) {
    fwrite(STDERR, "Usage: php tools/import_ftk_cli.php <shap_code> <YYYY-MM> <file.xlsx> [uploaded_by_user_id]\n");
    exit(1);
}
if (!is_file($filePath)) {
    fwrite(STDERR, "File tidak ditemukan: {$filePath}\n");
    exit(1);
}

$pdo = Database::connection();
$shap = $pdo->prepare('SELECT id FROM forsa_shap_entities WHERE code = :code');
$shap->execute(['code' => $shapCode]);
$shapRow = $shap->fetch();
if (!$shapRow) {
    fwrite(STDERR, "SH/AP dengan code '{$shapCode}' tidak ditemukan.\n");
    exit(1);
}

$userId = $userId ? (int) $userId : (int) $pdo->query('SELECT id FROM forsa_users ORDER BY id LIMIT 1')->fetch()['id'];

$parser = new FtkParser();
$result = $parser->parse($filePath);

$criticalErrors = array_filter($result['errors'], fn($e) => $e['severity'] === 'ERROR');
if (!empty($criticalErrors)) {
    fwrite(STDERR, 'Ditemukan ' . count($criticalErrors) . " error kritis, import dibatalkan.\n");
    foreach (array_slice($criticalErrors, 0, 20) as $e) {
        fwrite(STDERR, "  Baris {$e['row_number']} [{$e['column_name']}]: {$e['message']}\n");
    }
    exit(1);
}

$periodMonth = $period . '-01';

$insertJob = $pdo->prepare(
    'INSERT INTO forsa_import_jobs
        (shap_id, period_month, original_filename, stored_filename, stored_path, file_hash,
         status, total_rows, valid_rows, warning_rows, error_rows, note, uploaded_by, started_at, completed_at)
     VALUES
        (:shap_id, :period_month, :original_filename, :stored_filename, :stored_path, :file_hash,
         \'IMPORTED\', :total_rows, :valid_rows, :warning_rows, :error_rows, :note, :uploaded_by, now(), now())
     RETURNING id'
);
$insertJob->execute([
    'shap_id' => $shapRow['id'],
    'period_month' => $periodMonth,
    'original_filename' => basename($filePath),
    'stored_filename' => basename($filePath),
    'stored_path' => realpath($filePath),
    'file_hash' => hash_file('sha256', $filePath),
    'total_rows' => $result['summary']['total_rows'],
    'valid_rows' => $result['summary']['valid_rows'],
    'warning_rows' => $result['summary']['warning_rows'],
    'error_rows' => $result['summary']['error_rows'],
    'note' => 'Imported via CLI',
    'uploaded_by' => $userId,
]);
$jobId = $insertJob->fetch()['id'];

$writer = new SnapshotWriter();
$snapshotId = $writer->write((int) $shapRow['id'], $periodMonth, $jobId, $result['rows'], $userId);

echo "OK: snapshot {$snapshotId} dibuat untuk {$shapCode} periode {$period} ({$result['summary']['valid_rows']} baris).\n";
