<?php

declare(strict_types=1);

namespace Forsa\Import;

use Forsa\Database;
use PDO;

final class SnapshotWriter
{
    /**
     * Persist parsed rows as a new immutable snapshot, deactivate any previous
     * active snapshot for the same SH/AP + period, and mark this one active.
     * All within a single transaction (PRD section 34).
     */
    public function write(int $shapId, string $periodMonth, string $importJobId, array $rows, int $createdBy): string
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $revStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(revision_no), 0) + 1 AS next_rev
                 FROM forsa_ftk_snapshots WHERE shap_id = :shap_id AND period_month = :period_month'
            );
            $revStmt->execute(['shap_id' => $shapId, 'period_month' => $periodMonth]);
            $revisionNo = (int) $revStmt->fetch()['next_rev'];

            $totals = [
                'ftk' => 0,
                'organik' => 0,
                'tugas_karya' => 0,
                'pihak_ketiga' => 0,
                'realisasi' => 0,
            ];
            foreach ($rows as $row) {
                $totals['ftk'] += $row['ftk'];
                $totals['organik'] += $row['realisasi_organik'];
                $totals['tugas_karya'] += $row['realisasi_tugas_karya'];
                $totals['pihak_ketiga'] += $row['realisasi_pihak_ketiga'];
                $totals['realisasi'] += $row['total_realisasi'];
            }

            $deactivate = $pdo->prepare(
                'UPDATE forsa_ftk_snapshots SET is_active = FALSE
                 WHERE shap_id = :shap_id AND period_month = :period_month AND is_active = TRUE'
            );
            $deactivate->execute(['shap_id' => $shapId, 'period_month' => $periodMonth]);

            $insertSnapshot = $pdo->prepare(
                'INSERT INTO forsa_ftk_snapshots
                    (shap_id, import_job_id, period_month, revision_no, is_active,
                     total_ftk, total_realisasi_organik, total_realisasi_tugas_karya,
                     total_realisasi_pihak_ketiga, total_realisasi, created_by)
                 VALUES
                    (:shap_id, :import_job_id, :period_month, :revision_no, TRUE,
                     :total_ftk, :total_organik, :total_tugas_karya,
                     :total_pihak_ketiga, :total_realisasi, :created_by)
                 RETURNING id'
            );
            $insertSnapshot->execute([
                'shap_id' => $shapId,
                'import_job_id' => $importJobId,
                'period_month' => $periodMonth,
                'revision_no' => $revisionNo,
                'total_ftk' => $totals['ftk'],
                'total_organik' => $totals['organik'],
                'total_tugas_karya' => $totals['tugas_karya'],
                'total_pihak_ketiga' => $totals['pihak_ketiga'],
                'total_realisasi' => $totals['realisasi'],
                'created_by' => $createdBy,
            ]);
            $snapshotId = $insertSnapshot->fetch()['id'];

            $insertRow = $pdo->prepare(
                'INSERT INTO forsa_ftk_snapshot_rows
                    (snapshot_id, source_row_no, position_name, job_level_raw, job_level_group,
                     organization_level_2, organization_level_3, organization_level_4, position_grade,
                     ftk, realisasi_organik, realisasi_tugas_karya, realisasi_pihak_ketiga,
                     total_realisasi, sisa_delta, rencana_pemenuhan)
                 VALUES
                    (:snapshot_id, :source_row_no, :position_name, :job_level_raw, :job_level_group,
                     :l2, :l3, :l4, :position_grade,
                     :ftk, :organik, :tugas_karya, :pihak_ketiga,
                     :total_realisasi, :sisa_delta, :rencana_pemenuhan)'
            );

            foreach ($rows as $row) {
                $insertRow->execute([
                    'snapshot_id' => $snapshotId,
                    'source_row_no' => $row['source_row_no'],
                    'position_name' => $row['position_name'],
                    'job_level_raw' => $row['job_level_raw'],
                    'job_level_group' => $row['job_level_group'],
                    'l2' => $row['organization_level_2'],
                    'l3' => $row['organization_level_3'],
                    'l4' => $row['organization_level_4'],
                    'position_grade' => $row['position_grade'],
                    'ftk' => $row['ftk'],
                    'organik' => $row['realisasi_organik'],
                    'tugas_karya' => $row['realisasi_tugas_karya'],
                    'pihak_ketiga' => $row['realisasi_pihak_ketiga'],
                    'total_realisasi' => $row['total_realisasi'],
                    'sisa_delta' => $row['sisa_delta'],
                    'rencana_pemenuhan' => $row['rencana_pemenuhan'],
                ]);
            }

            $pdo->commit();

            return $snapshotId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
