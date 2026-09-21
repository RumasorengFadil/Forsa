<?php

declare(strict_types=1);

namespace Forsa\Ftk;

use Forsa\Database;
use Forsa\JobLevelMapper;
use PDO;

final class TreeService
{
    private const METRIC_COLS = ['ftk', 'realisasi_organik', 'realisasi_tugas_karya', 'realisasi_pihak_ketiga', 'total_realisasi', 'sisa_delta'];

    /**
     * Root level: one node per SH/AP snapshot in the current selection.
     */
    public function rootNodes(array $snapshotRows, array $filters): array
    {
        $nodes = [];
        foreach ($snapshotRows as $s) {
            $agg = $this->aggregateSnapshot($s['id'], [], $filters);
            $nodes[] = [
                'key' => 'shap:' . $s['shap_code'],
                'label' => $s['shap_short_name'],
                'level' => 1,
                'type' => 'shap',
                'has_children' => $agg['total']['row_count'] > 0,
                'snapshot_id' => $s['id'],
                'path' => [],
                'metrics' => $agg,
            ];
        }
        return $nodes;
    }

    /**
     * Expand children under a given snapshot + partial org path.
     * $path is an ordered list of concrete values already chosen for level2/level3/level4.
     */
    public function children(string $snapshotId, array $path, array $filters): array
    {
        $depth = count($path);
        $orgFields = ['organization_level_2', 'organization_level_3', 'organization_level_4'];

        if ($depth >= 3) {
            return $this->leafNodes($snapshotId, $path, $filters);
        }

        $field = $orgFields[$depth];

        $pdo = Database::connection();
        [$where, $params] = $this->buildWhere($snapshotId, $path, $filters);

        $sql = "SELECT {$field} AS grp, job_level_group,
                    SUM(ftk) AS ftk, SUM(realisasi_organik) AS realisasi_organik,
                    SUM(realisasi_tugas_karya) AS realisasi_tugas_karya,
                    SUM(realisasi_pihak_ketiga) AS realisasi_pihak_ketiga,
                    SUM(total_realisasi) AS total_realisasi, SUM(sisa_delta) AS sisa_delta,
                    COUNT(*) AS row_count
                FROM forsa_ftk_snapshot_rows
                WHERE {$where}
                GROUP BY {$field}, job_level_group";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $orgBuckets = [];
        $nullFieldRows = [];
        foreach ($rows as $r) {
            if ($r['grp'] === null) {
                $nullFieldRows[] = $r;
            } else {
                $orgBuckets[$r['grp']][] = $r;
            }
        }

        $nodes = [];
        foreach ($orgBuckets as $label => $bucketRows) {
            $nodes[] = [
                'key' => 'org' . ($depth + 2) . ':' . $label,
                'label' => $label,
                'level' => $depth + 2,
                'type' => 'org',
                'has_children' => true,
                'snapshot_id' => $snapshotId,
                'path' => [...$path, $label],
                'metrics' => $this->combineGroupRows($bucketRows),
            ];
        }
        usort($nodes, fn($a, $b) => strcmp((string) $a['label'], (string) $b['label']));

        // Rows whose org field at this depth is null: their jabatan bubbles up directly here.
        if (!empty($nullFieldRows)) {
            $leafNodes = $this->leafNodes($snapshotId, $path, $filters, true);
            $nodes = array_merge($nodes, $leafNodes);
        }

        return $nodes;
    }

    private function leafNodes(string $snapshotId, array $path, array $filters, bool $forceNullNextLevel = false): array
    {
        $pdo = Database::connection();
        [$where, $params] = $this->buildWhere($snapshotId, $path, $filters, $forceNullNextLevel ? count($path) : null);

        $sql = "SELECT position_name, position_grade, job_level_group,
                    SUM(ftk) AS ftk, SUM(realisasi_organik) AS realisasi_organik,
                    SUM(realisasi_tugas_karya) AS realisasi_tugas_karya,
                    SUM(realisasi_pihak_ketiga) AS realisasi_pihak_ketiga,
                    SUM(total_realisasi) AS total_realisasi, SUM(sisa_delta) AS sisa_delta,
                    COUNT(*) AS row_count
                FROM forsa_ftk_snapshot_rows
                WHERE {$where}
                GROUP BY position_name, position_grade, job_level_group";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $byPosition = [];
        foreach ($rows as $r) {
            $key = $r['position_name'] . '||' . ($r['position_grade'] ?? '');
            $byPosition[$key]['position_name'] = $r['position_name'];
            $byPosition[$key]['grades'][$r['position_grade'] ?? ''] = true;
            $byPosition[$key]['rows'][] = $r;
        }

        $nodes = [];
        foreach ($byPosition as $entry) {
            $grades = array_filter(array_keys($entry['grades']), fn($g) => $g !== '');
            $nodes[] = [
                'key' => 'leaf:' . md5($entry['position_name'] . implode(',', $grades)),
                'label' => $entry['position_name'],
                'position_grade' => implode(' / ', $grades),
                'level' => 5,
                'type' => 'leaf',
                'has_children' => false,
                'snapshot_id' => $snapshotId,
                'path' => $path,
                'metrics' => $this->combineGroupRows($entry['rows']),
            ];
        }
        usort($nodes, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $nodes;
    }

    private function combineGroupRows(array $groupRows): array
    {
        $total = array_fill_keys(self::METRIC_COLS, 0);
        $total['row_count'] = 0;
        $groups = [];

        foreach ($groupRows as $r) {
            foreach (self::METRIC_COLS as $col) {
                $total[$col] += (int) $r[$col];
            }
            $total['row_count'] += (int) $r['row_count'];

            $group = $r['job_level_group'] ?? '__unmapped__';
            if (!isset($groups[$group])) {
                $groups[$group] = array_fill_keys(self::METRIC_COLS, 0);
            }
            foreach (self::METRIC_COLS as $col) {
                $groups[$group][$col] += (int) $r[$col];
            }
        }

        $orderedGroups = [];
        foreach (JobLevelMapper::orderedGroups() as $g) {
            $orderedGroups[$g] = $groups[$g] ?? array_fill_keys(self::METRIC_COLS, 0);
        }

        return ['total' => $total, 'groups' => $orderedGroups];
    }

    public function aggregateSnapshot(string $snapshotId, array $path, array $filters): array
    {
        $pdo = Database::connection();
        [$where, $params] = $this->buildWhere($snapshotId, $path, $filters);

        $sql = "SELECT job_level_group,
                    SUM(ftk) AS ftk, SUM(realisasi_organik) AS realisasi_organik,
                    SUM(realisasi_tugas_karya) AS realisasi_tugas_karya,
                    SUM(realisasi_pihak_ketiga) AS realisasi_pihak_ketiga,
                    SUM(total_realisasi) AS total_realisasi, SUM(sisa_delta) AS sisa_delta,
                    COUNT(*) AS row_count
                FROM forsa_ftk_snapshot_rows
                WHERE {$where}
                GROUP BY job_level_group";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $this->combineGroupRows($stmt->fetchAll());
    }

    private function buildWhere(string $snapshotId, array $path, array $filters, ?int $forceNullAtDepth = null): array
    {
        $orgFields = ['organization_level_2', 'organization_level_3', 'organization_level_4'];
        $where = ['snapshot_id = :snapshot_id'];
        $params = ['snapshot_id' => $snapshotId];

        foreach ($path as $i => $value) {
            $where[] = "{$orgFields[$i]} = :path{$i}";
            $params["path{$i}"] = $value;
        }

        if ($forceNullAtDepth !== null && isset($orgFields[$forceNullAtDepth])) {
            $where[] = "{$orgFields[$forceNullAtDepth]} IS NULL";
        }

        if (!empty($filters['search'])) {
            $where[] = '(position_name ILIKE :search OR organization_level_2 ILIKE :search OR organization_level_3 ILIKE :search OR organization_level_4 ILIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['job_level_group'])) {
            $where[] = 'job_level_group = :job_level_group';
            $params['job_level_group'] = $filters['job_level_group'];
        }
        if (!empty($filters['position_grade'])) {
            $where[] = 'position_grade ILIKE :position_grade';
            $params['position_grade'] = '%' . $filters['position_grade'] . '%';
        }
        if (!empty($filters['gap_status'])) {
            $where[] = match ($filters['gap_status']) {
                'kurang' => 'sisa_delta > 0',
                'terpenuhi' => 'sisa_delta = 0',
                'lebih' => 'sisa_delta < 0',
                default => '1=1',
            };
        }

        return [implode(' AND ', $where), $params];
    }
}
