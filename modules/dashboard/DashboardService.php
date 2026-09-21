<?php

declare(strict_types=1);

namespace Forsa\Dashboard;

use Forsa\Database;
use Forsa\JobLevelMapper;
use PDO;

final class DashboardService
{
    /**
     * Resolve a selection string into a list of snapshot rows to aggregate.
     * "period:YYYY-MM-DD" -> all active snapshots for that period (merge mode).
     * "snapshot:<uuid>"   -> a single historical snapshot (inspection mode).
     * null                -> latest available period.
     */
    public function resolveSnapshots(?string $selection): array
    {
        $pdo = Database::connection();

        if ($selection && str_starts_with($selection, 'snapshot:')) {
            $id = substr($selection, 9);
            $stmt = $pdo->prepare(
                'SELECT s.*, e.code AS shap_code, e.short_name AS shap_short_name, e.name AS shap_name, e.sort_order
                 FROM forsa_ftk_snapshots s
                 JOIN forsa_shap_entities e ON e.id = s.shap_id
                 WHERE s.id = :id'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ? ['mode' => 'inspection', 'period' => $row['period_month'], 'rows' => [$row]] : ['mode' => 'inspection', 'period' => null, 'rows' => []];
        }

        $period = null;
        if ($selection && str_starts_with($selection, 'period:')) {
            $period = substr($selection, 7);
        } else {
            $latest = $pdo->query('SELECT MAX(period_month) AS m FROM forsa_ftk_snapshots WHERE is_active = TRUE')->fetch();
            $period = $latest['m'] ?? null;
        }

        if (!$period) {
            return ['mode' => 'period', 'period' => null, 'rows' => []];
        }

        $stmt = $pdo->prepare(
            'SELECT s.*, e.code AS shap_code, e.short_name AS shap_short_name, e.name AS shap_name, e.sort_order
             FROM forsa_ftk_snapshots s
             JOIN forsa_shap_entities e ON e.id = s.shap_id
             WHERE s.period_month = :period AND s.is_active = TRUE
             ORDER BY e.sort_order'
        );
        $stmt->execute(['period' => $period]);

        return ['mode' => 'period', 'period' => $period, 'rows' => $stmt->fetchAll()];
    }

    public function historyOptions(): array
    {
        $pdo = Database::connection();

        $periods = $pdo->query(
            "SELECT period_month, COUNT(*) AS shap_count, SUM(total_ftk) AS total_ftk
             FROM forsa_ftk_snapshots WHERE is_active = TRUE
             GROUP BY period_month ORDER BY period_month DESC"
        )->fetchAll();

        $totalShap = (int) $pdo->query('SELECT COUNT(*) AS c FROM forsa_shap_entities WHERE is_active = TRUE')->fetch()['c'];

        $periodOptions = [];
        foreach ($periods as $i => $p) {
            $periodOptions[] = [
                'value' => 'period:' . $p['period_month'],
                'label' => self::formatPeriod($p['period_month']) . ($i === 0 ? ' — Terbaru' : ''),
                'is_latest' => $i === 0,
                'coverage' => (int) $p['shap_count'],
                'coverage_total' => $totalShap,
            ];
        }

        $history = $pdo->query(
            "SELECT s.id, s.period_month, s.revision_no, s.is_active, s.created_at, e.short_name
             FROM forsa_ftk_snapshots s
             JOIN forsa_shap_entities e ON e.id = s.shap_id
             ORDER BY s.created_at DESC LIMIT 60"
        )->fetchAll();

        $historyOptions = [];
        foreach ($history as $h) {
            $historyOptions[] = [
                'value' => 'snapshot:' . $h['id'],
                'label' => sprintf(
                    '%s — %s — Rev %d%s',
                    $h['short_name'],
                    self::formatPeriod($h['period_month']),
                    $h['revision_no'],
                    $h['is_active'] ? ' (aktif)' : ''
                ),
            ];
        }

        return [
            'periods' => $periodOptions,
            'history' => $historyOptions,
            'default' => $periodOptions[0]['value'] ?? null,
        ];
    }

    public static function formatPeriod(?string $ymd): string
    {
        if (!$ymd) {
            return '-';
        }
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        [$y, $m] = explode('-', $ymd);
        return $months[(int) $m] . ' ' . $y;
    }

    public function buildDashboard(?string $selection): array
    {
        $resolved = $this->resolveSnapshots($selection);
        $snapshotRows = $resolved['rows'];

        if (empty($snapshotRows)) {
            return [
                'has_data' => false,
                'mode' => $resolved['mode'],
                'period' => $resolved['period'],
                'period_label' => self::formatPeriod($resolved['period']),
            ];
        }

        $snapshotIds = array_column($snapshotRows, 'id');
        $pdo = Database::connection();
        $placeholders = implode(',', array_fill(0, count($snapshotIds), '?'));

        // KPI totals directly from cached snapshot summary (fast path).
        $totalFtk = (int) array_sum(array_column($snapshotRows, 'total_ftk'));
        $totalRealisasi = (int) array_sum(array_column($snapshotRows, 'total_realisasi'));
        $pemenuhan = $totalFtk > 0 ? round($totalRealisasi / $totalFtk * 100, 1) : 0.0;
        $gapDashboard = $totalRealisasi - $totalFtk;

        // Per SH/AP cards.
        $perShap = [];
        foreach ($snapshotRows as $s) {
            $ftk = (int) $s['total_ftk'];
            $real = (int) $s['total_realisasi'];
            $pct = $ftk > 0 ? round($real / $ftk * 100, 1) : 0.0;
            $gap = $real - $ftk;
            $status = JobLevelMapper::statusFor($pct);
            $perShap[] = [
                'shap_code' => $s['shap_code'],
                'shap_name' => $s['shap_short_name'],
                'pct' => $pct,
                'gap' => $gap,
                'ftk' => $ftk,
                'realisasi' => $real,
                'status_code' => $status['code'],
            ];
        }

        // Status prioritas pemenuhan.
        $buckets = ['fulfilled' => [], 'monitor' => [], 'priority' => []];
        foreach ($perShap as $s) {
            $buckets[$s['status_code']][] = $s['shap_name'];
        }

        // Gap terbesar (sorted ascending, most negative first) top 7.
        $gapSorted = $perShap;
        usort($gapSorted, fn($a, $b) => $a['gap'] <=> $b['gap']);
        $gapTerbesar = array_slice($gapSorted, 0, 7);
        $maxAbsGap = 1;
        foreach ($gapTerbesar as $g) {
            $maxAbsGap = max($maxAbsGap, abs($g['gap']));
        }
        foreach ($gapTerbesar as &$g) {
            $g['bar_pct'] = $g['gap'] < 0 ? round(abs($g['gap']) / $maxAbsGap * 100, 1) : 0;
        }
        unset($g);

        // Per jenjang jabatan aggregation (needs row-level data).
        $stmt = $pdo->prepare(
            "SELECT job_level_group,
                    SUM(ftk) AS ftk,
                    SUM(total_realisasi) AS realisasi
             FROM forsa_ftk_snapshot_rows
             WHERE snapshot_id IN ({$placeholders})
             GROUP BY job_level_group"
        );
        $stmt->execute($snapshotIds);
        $jenjangRaw = $stmt->fetchAll();

        $jenjangMap = [];
        foreach ($jenjangRaw as $j) {
            $group = $j['job_level_group'] ?? '__unmapped__';
            $jenjangMap[$group] = [
                'ftk' => (int) $j['ftk'],
                'realisasi' => (int) $j['realisasi'],
            ];
        }

        $perJenjang = [];
        foreach (JobLevelMapper::orderedGroups() as $group) {
            $data = $jenjangMap[$group] ?? ['ftk' => 0, 'realisasi' => 0];
            $belum = max($data['ftk'] - $data['realisasi'], 0);
            $lebih = max($data['realisasi'] - $data['ftk'], 0);
            $terpenuhi = min($data['ftk'], $data['realisasi']);
            $perJenjang[] = [
                'group' => $group,
                'label' => JobLevelMapper::label($group),
                'ftk' => $data['ftk'],
                'realisasi' => $data['realisasi'],
                'terpenuhi' => $terpenuhi,
                'belum_dipenuhi' => $belum,
                'lebih' => $lebih,
            ];
        }
        // sort descending by ftk for the horizontal bar visual, matching mockup ordering
        usort($perJenjang, fn($a, $b) => $b['ftk'] <=> $a['ftk']);
        $maxFtkJenjang = 1;
        foreach ($perJenjang as $j) {
            $maxFtkJenjang = max($maxFtkJenjang, $j['ftk']);
        }
        foreach ($perJenjang as &$j) {
            $j['bar_total_pct'] = round($j['ftk'] / $maxFtkJenjang * 100, 1);
            $j['bar_ok_pct'] = $j['ftk'] > 0 ? round($j['terpenuhi'] / $j['ftk'] * $j['bar_total_pct'], 1) : 0;
            $j['bar_gap_pct'] = max($j['bar_total_pct'] - $j['bar_ok_pct'], 0);
        }
        unset($j);

        $insight = $this->buildInsight($perJenjang, $gapSorted);

        return [
            'has_data' => true,
            'mode' => $resolved['mode'],
            'period' => $resolved['period'],
            'period_label' => self::formatPeriod($resolved['period']),
            'kpi' => [
                'total_ftk' => $totalFtk,
                'total_realisasi' => $totalRealisasi,
                'pemenuhan_pct' => $pemenuhan,
                'gap_dashboard' => $gapDashboard,
            ],
            'per_shap' => $perShap,
            'priority' => [
                'fulfilled' => $buckets['fulfilled'],
                'monitor' => $buckets['monitor'],
                'priority' => $buckets['priority'],
            ],
            'gap_terbesar' => $gapTerbesar,
            'per_jenjang' => $perJenjang,
            'insight' => $insight,
            'coverage' => [
                'available' => count($snapshotRows),
            ],
        ];
    }

    private function buildInsight(array $perJenjang, array $gapSortedShap): array
    {
        $byGapDesc = $perJenjang;
        usort($byGapDesc, fn($a, $b) => $b['belum_dipenuhi'] <=> $a['belum_dipenuhi']);
        $topJenjang = array_slice(array_values(array_filter($byGapDesc, fn($j) => $j['belum_dipenuhi'] > 0)), 0, 3);

        $lines = [];
        $labels = ['01', '02', '03'];
        foreach ($topJenjang as $i => $j) {
            $lines[] = [
                'no' => $labels[$i] ?? (string) ($i + 1),
                'title' => $j['label'],
                'text' => $i === 0
                    ? "Gap jenjang terbesar: {$j['belum_dipenuhi']} posisi."
                    : "Gap {$j['belum_dipenuhi']} posisi" . ($i === 1 ? '; prioritas kedua.' : '.'),
            ];
        }

        $worstShap = $gapSortedShap[0] ?? null;
        $footer = null;
        if ($worstShap) {
            $lines[] = [
                'no' => (string) (count($lines) + 1),
                'title' => 'SH/AP',
                'text' => "{$worstShap['shap_name']} memiliki gap absolut terbesar ({$worstShap['gap']}).",
            ];
            $prioNames = array_map(fn($j) => $j['label'] . ' (' . $j['belum_dipenuhi'] . ')', $topJenjang);
            $footer = 'Prioritas jenjang: ' . implode(' → ', $prioNames) . ' belum dipenuhi.';
        }

        return ['lines' => $lines, 'footer' => $footer];
    }
}
