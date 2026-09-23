<?php

declare(strict_types=1);

// FORSA AI Assistant — Phase 9 evaluation suite (PRD_FORSA_AI_Assistant.md
// §52-54). Covers every PRD §53 category that is deterministic at the
// query_forsa/semantic-layer level (no LLM call, no API cost, repeatable).
//
// Categories that depend on the LLM's own reasoning over natural language
// (follow-up context resolution, recognizing an ambiguous question and
// asking for clarification) are NOT exercised here — they were verified
// manually against gpt-4o-mini in the Tahap 4/5 implementation reports, and
// re-checked once more after this script's own prompt change (see the
// report tied to this file). A real LLM call for every category would
// spend API credits on every run of this suite for no added determinism.
//
// Usage: php tools/ai_evaluation_cli.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/env.php';
require __DIR__ . '/../shared/Database.php';

use Forsa\Ai\Semantic\SemanticException;
use Forsa\Ai\Tools\QueryForsaTool;

$superAdmin = ['id' => 1, 'roles' => ['SUPER_ADMIN']];
$noRole = ['id' => 1, 'roles' => []];

$results = [];

function evaluate(string $category, string $label, callable $fn): void
{
    global $results;
    try {
        $fn();
        $results[] = [$category, $label, true, null];
    } catch (\Throwable $e) {
        $results[] = [$category, $label, false, $e->getMessage()];
    }
}

function assertTrue(bool $cond, string $message): void
{
    if (!$cond) {
        throw new \RuntimeException("Assertion failed: {$message}");
    }
}

$tool = new QueryForsaTool();

// ---- simple metric ----
evaluate('simple metric', 'total_ftk untuk periode terbaru global', function () use ($tool, $superAdmin) {
    $r = $tool->execute(['metrics' => ['total_ftk']], $superAdmin);
    assertTrue(count($r['rows']) === 1, 'harus 1 baris agregat');
    assertTrue(isset($r['rows'][0]['total_ftk']), 'harus ada kolom total_ftk');
});

// ---- filter ----
evaluate('filter', 'filter company=PLN NP', function () use ($tool, $superAdmin) {
    $r = $tool->execute(['metrics' => ['total_ftk'], 'filters' => [['dimension' => 'company', 'operator' => '=', 'value' => 'PLN NP']]], $superAdmin);
    assertTrue(count($r['rows']) === 1, 'harus 1 baris untuk 1 company');
});

// ---- multi-filter ----
evaluate('multi-filter', 'filter company + job_level (label manusia)', function () use ($tool, $superAdmin) {
    $r = $tool->execute([
        'metrics' => ['total_ftk', 'total_realisasi'],
        'filters' => [
            ['dimension' => 'company', 'operator' => '=', 'value' => 'PLN NP'],
            ['dimension' => 'job_level', 'operator' => '=', 'value' => 'Manajemen Atas'],
        ],
        'period' => '2026-09',
    ], $superAdmin);
    assertTrue(count($r['rows']) === 1, 'harus 1 baris');
    assertTrue($r['rows'][0]['total_ftk'] === 9, 'FTK Manajemen Atas PLN NP Sep 2026 harus 9 (dicocokkan manual ke DB sebelumnya)');
});

// ---- comparison / time comparison ----
evaluate('comparison', 'previous_period untuk PLN NP', function () use ($tool, $superAdmin) {
    $r = $tool->execute([
        'metrics' => ['total_ftk'],
        'filters' => [['dimension' => 'company', 'operator' => '=', 'value' => 'PLN NP']],
        'comparison' => 'previous_period',
    ], $superAdmin);
    assertTrue($r['previous_period'] !== null, 'previous_period harus terselesaikan (PLN NP punya 2 periode aktif)');
    assertTrue($r['previous_rows'] !== null, 'previous_rows harus terisi');
    assertTrue($r['resolved_period'] > $r['previous_period'], 'periode saat ini harus lebih baru dari periode sebelumnya');
});

// ---- ranking ----
evaluate('ranking', 'top gap per company, limit 5', function () use ($tool, $superAdmin) {
    $r = $tool->execute(['metrics' => ['gap'], 'dimensions' => ['company'], 'sort' => [['metric' => 'gap', 'direction' => 'asc']], 'limit' => 5], $superAdmin);
    assertTrue(count($r['rows']) <= 5, 'tidak boleh lebih dari limit');
});

// ---- unsupported metric ----
evaluate('unsupported metric', 'metric = nama tabel mentah harus ditolak', function () use ($tool, $superAdmin) {
    try {
        $tool->execute(['metrics' => ['forsa_users']], $superAdmin);
        throw new \RuntimeException('harusnya melempar SemanticException, tidak ada exception');
    } catch (SemanticException $e) {
        assertTrue($e->errorCode === SemanticException::METRIC_UNAVAILABLE, 'error code harus metric_unavailable, dapat: ' . $e->errorCode);
    }
});

// ---- empty result ----
evaluate('empty result', 'company yang tidak ada di database', function () use ($tool, $superAdmin) {
    try {
        $tool->execute(['metrics' => ['total_ftk'], 'filters' => [['dimension' => 'company', 'operator' => '=', 'value' => 'Perusahaan Fiktif XYZ']]], $superAdmin);
        throw new \RuntimeException('harusnya melempar SemanticException no_result');
    } catch (SemanticException $e) {
        assertTrue($e->errorCode === SemanticException::NO_RESULT, 'error code harus no_result, dapat: ' . $e->errorCode);
    }
});

// ---- unauthorized query ----
evaluate('unauthorized query', 'user tanpa role yang dikenal harus access_denied', function () use ($tool, $noRole) {
    try {
        $tool->execute(['metrics' => ['total_ftk']], $noRole);
        throw new \RuntimeException('harusnya melempar SemanticException access_denied');
    } catch (SemanticException $e) {
        assertTrue($e->errorCode === SemanticException::ACCESS_DENIED, 'error code harus access_denied, dapat: ' . $e->errorCode);
    }
});

// ---- large result (limit cap) ----
evaluate('large result', 'limit diminta 1000 harus di-cap ke 100', function () use ($tool, $superAdmin) {
    $r = $tool->execute(['metrics' => ['total_ftk'], 'dimensions' => ['job_level'], 'limit' => 1000], $superAdmin);
    assertTrue(str_contains($r['sql'], 'LIMIT 100'), 'SQL harus mengandung LIMIT 100 (capped), dapat: ' . $r['sql']);
});

// ---- security regression (bukan kategori PRD §53, tapi wajib tetap hijau) ----
evaluate('security', 'SQL injection di filter value tidak pernah mengeksekusi apa pun', function () use ($tool, $superAdmin) {
    try {
        $tool->execute(['metrics' => ['total_ftk'], 'filters' => [['dimension' => 'company', 'operator' => '=', 'value' => "x'; DROP TABLE forsa_users; --"]]], $superAdmin);
    } catch (SemanticException $e) {
        assertTrue($e->errorCode === SemanticException::NO_RESULT, 'harus diperlakukan sebagai pencarian biasa (no_result), bukan error lain');
    }
    $count = Forsa\Database::connection()->query('SELECT COUNT(*) c FROM forsa_users')->fetch()['c'];
    assertTrue((int) $count > 0, 'forsa_users harus tetap utuh');
});

// ---- output ----
$pass = 0;
$fail = 0;
foreach ($results as [$category, $label, $ok, $error]) {
    $status = $ok ? 'PASS' : 'FAIL';
    printf("[%s] %-20s %s\n", $status, $category, $label);
    if (!$ok) {
        echo "       -> {$error}\n";
    }
    $ok ? $pass++ : $fail++;
}

echo str_repeat('-', 60) . "\n";
echo "Total: " . count($results) . ", Pass: {$pass}, Fail: {$fail}\n";
echo "\nKategori PRD §53 yang butuh LLM (tidak diuji di sini, lihat laporan Tahap 4/5/6):\n";
echo "  - follow-up context (resolusi 'kalau bulan sebelumnya?' oleh model dari histori percakapan)\n";
echo "  - ambiguous query (model bertanya balik alih-alih menebak periode)\n";

exit($fail > 0 ? 1 : 0);
