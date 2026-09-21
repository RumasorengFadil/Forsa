<?php

declare(strict_types=1);

namespace Forsa\Import;

use Forsa\JobLevelMapper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

final class FtkParser
{
    private const COLUMN_MAP = [
        'A' => 'row_no',
        'B' => 'position_name',
        'C' => 'job_level_raw',
        'D' => 'organization_level_2',
        'E' => 'organization_level_3',
        'F' => 'organization_level_4',
        'G' => 'position_grade',
        'H' => 'ftk',
        'I' => 'realisasi_organik',
        'J' => 'realisasi_tugas_karya',
        'K' => 'realisasi_pihak_ketiga',
        'L' => 'total_realisasi',
        'M' => 'sisa_delta',
        'N' => 'rencana_pemenuhan',
    ];

    private const HEADER_LABELS = [
        'B' => 'SEBUTAN JABATAN',
        'C' => 'JENJANG JABATAN',
        'D' => 'UI/UP/UL',
        'E' => 'UNIT PELAKSANA/KANTOR PUSAT',
        'F' => 'UNIT LAYANAN',
        'G' => 'POSITION GRADE(PoG)',
        'H' => 'FTK',
        'I' => 'ORGANIK',
        'J' => 'TUGAS KARYA',
        'K' => 'PIHAK KETIGA',
        'L' => 'TOTAL REALISASI',
        'M' => 'SISA',
    ];

    /**
     * @return array{rows: array<int, array>, errors: array<int, array>, summary: array}
     */
    public function parse(string $filePath): array
    {
        /** @var Xlsx $reader */
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertHeader($sheet);

        $rows = [];
        $errors = [];
        $highestRow = $sheet->getHighestDataRow();

        $validCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        for ($excelRow = 3; $excelRow <= $highestRow; $excelRow++) {
            $positionName = trim((string) $sheet->getCell('B' . $excelRow)->getValue());

            if ($positionName === '') {
                // Skip blank / footer rows (e.g. "Total", "% Realisasi ...") gracefully.
                continue;
            }
            if (mb_strtolower($positionName) === 'total' || str_starts_with(mb_strtolower($positionName), '% realisasi')) {
                continue;
            }

            $rawRow = [];
            foreach (self::COLUMN_MAP as $col => $field) {
                $rawRow[$field] = $sheet->getCell($col . $excelRow)->getValue();
            }

            [$row, $rowErrors] = $this->normalizeRow($rawRow, $excelRow);
            $errors = array_merge($errors, $rowErrors);

            $hasError = false;
            foreach ($rowErrors as $err) {
                if ($err['severity'] === 'ERROR') {
                    $hasError = true;
                } else {
                    $warningCount++;
                }
            }

            if ($hasError) {
                $errorCount++;
                continue;
            }

            $validCount++;
            $rows[] = $row;
        }

        unset($spreadsheet);

        return [
            'rows' => $rows,
            'errors' => $errors,
            'summary' => [
                'total_rows' => $validCount + $errorCount,
                'valid_rows' => $validCount,
                'warning_rows' => $warningCount,
                'error_rows' => $errorCount,
                'total_ftk' => array_sum(array_column($rows, 'ftk')),
                'total_realisasi' => array_sum(array_column($rows, 'total_realisasi')),
            ],
        ];
    }

    private function assertHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $rules = require dirname(__DIR__, 2) . '/config/ftk_rules.php';
        $missing = [];

        foreach (self::HEADER_LABELS as $col => $expectedLabel) {
            $row = $col === 'I' || $col === 'J' || $col === 'K' ? 2 : 1;
            $actual = trim((string) $sheet->getCell($col . $row)->getValue());
            $actualNormalized = mb_strtoupper(preg_replace('/\s+/', ' ', $actual));
            $expectedNormalized = mb_strtoupper($expectedLabel);
            if ($actualNormalized !== $expectedNormalized) {
                $missing[] = "{$expectedLabel} (kolom {$col})";
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException('Template tidak sesuai. Header berikut tidak ditemukan/berbeda: ' . implode(', ', $missing));
        }
    }

    private function normalizeRow(array $raw, int $excelRow): array
    {
        $errors = [];

        $positionName = trim((string) $raw['position_name']);
        if ($positionName === '') {
            $errors[] = $this->err($excelRow, 'SEBUTAN JABATAN', 'ERROR', 'REQUIRED', 'Sebutan jabatan wajib diisi.', $raw['position_name']);
        }

        $jobLevelRaw = normalize_blank((string) ($raw['job_level_raw'] ?? ''));
        $jobLevelGroup = $jobLevelRaw !== null ? JobLevelMapper::map($jobLevelRaw) : null;
        if ($jobLevelRaw !== null && $jobLevelGroup === null) {
            $errors[] = $this->err($excelRow, 'JENJANG JABATAN', 'WARNING', 'UNMAPPED_LEVEL', "Jenjang '{$jobLevelRaw}' tidak dikenali mapping, masuk kategori tanpa grup.", $jobLevelRaw);
        }
        if ($jobLevelRaw === null) {
            $errors[] = $this->err($excelRow, 'JENJANG JABATAN', 'WARNING', 'EMPTY_LEVEL', 'Jenjang jabatan kosong.', null);
        }

        $ftk = $this->numericOrError($raw['ftk'], $excelRow, 'FTK', $errors);
        $organik = $this->numericOrError($raw['realisasi_organik'], $excelRow, 'Organik', $errors);
        $tugasKarya = $this->numericOrError($raw['realisasi_tugas_karya'], $excelRow, 'Tugas Karya', $errors);
        $pihakKetiga = $this->numericOrError($raw['realisasi_pihak_ketiga'], $excelRow, 'Pihak Ketiga', $errors);

        $computedTotalRealisasi = $organik + $tugasKarya + $pihakKetiga;
        $fileTotalRealisasi = to_int_or_zero($raw['total_realisasi']);
        if ($raw['total_realisasi'] !== null && $raw['total_realisasi'] !== '' && $fileTotalRealisasi !== $computedTotalRealisasi) {
            $errors[] = $this->err($excelRow, 'Total Realisasi', 'WARNING', 'FORMULA_MISMATCH', "Total Realisasi pada file ({$fileTotalRealisasi}) berbeda dari hasil formula ({$computedTotalRealisasi}). Sistem memakai hasil formula.", (string) $raw['total_realisasi']);
        }

        $computedSisa = $ftk - $computedTotalRealisasi;
        $fileSisa = to_int_or_zero($raw['sisa_delta']);
        if ($raw['sisa_delta'] !== null && $raw['sisa_delta'] !== '' && $fileSisa !== $computedSisa) {
            $errors[] = $this->err($excelRow, 'Sisa', 'WARNING', 'FORMULA_MISMATCH', "Sisa pada file ({$fileSisa}) berbeda dari hasil formula ({$computedSisa}). Sistem memakai hasil formula.", (string) $raw['sisa_delta']);
        }

        $row = [
            'source_row_no' => is_numeric($raw['row_no']) ? (int) $raw['row_no'] : null,
            'position_name' => $positionName,
            'job_level_raw' => $jobLevelRaw,
            'job_level_group' => $jobLevelGroup,
            'organization_level_2' => normalize_blank((string) ($raw['organization_level_2'] ?? '')),
            'organization_level_3' => normalize_blank((string) ($raw['organization_level_3'] ?? '')),
            'organization_level_4' => normalize_blank((string) ($raw['organization_level_4'] ?? '')),
            'position_grade' => normalize_blank((string) ($raw['position_grade'] ?? '')),
            'ftk' => $ftk,
            'realisasi_organik' => $organik,
            'realisasi_tugas_karya' => $tugasKarya,
            'realisasi_pihak_ketiga' => $pihakKetiga,
            'total_realisasi' => $computedTotalRealisasi,
            'sisa_delta' => $computedSisa,
            'rencana_pemenuhan' => normalize_blank((string) ($raw['rencana_pemenuhan'] ?? '')),
            'excel_row' => $excelRow,
        ];

        return [$row, $errors];
    }

    private function numericOrError(mixed $value, int $excelRow, string $column, array &$errors): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (!is_numeric($value)) {
            $errors[] = $this->err($excelRow, $column, 'ERROR', 'NOT_NUMERIC', "Nilai '{$column}' harus berupa angka.", (string) $value);
            return 0;
        }
        $num = (float) $value;
        if ($num < 0) {
            $errors[] = $this->err($excelRow, $column, 'ERROR', 'NEGATIVE_VALUE', "Nilai '{$column}' tidak boleh negatif.", (string) $value);
            return 0;
        }
        return (int) round($num);
    }

    private function err(int $row, string $column, string $severity, string $code, string $message, ?string $rawValue): array
    {
        return [
            'row_number' => $row,
            'column_name' => $column,
            'severity' => $severity,
            'error_code' => $code,
            'message' => $message,
            'raw_value' => $rawValue,
        ];
    }
}
