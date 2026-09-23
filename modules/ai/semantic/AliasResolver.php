<?php

declare(strict_types=1);

namespace Forsa\Ai\Semantic;

use Forsa\Ai\Query\AiReaderConnection;
use Forsa\JobLevelMapper;

/**
 * PRD §10 — Alias & Business Vocabulary. Turns the words a user/LLM might
 * use into the canonical metric/dimension/value identifiers the rest of the
 * semantic layer understands. Metric/dimension aliases are a small static
 * table (PRD gives the exact list); job_level reuses config/ftk_rules.php's
 * job_level_labels (reverse lookup) instead of redefining labels; company
 * name resolution is the one alias that needs a DB lookup (fuzzy match
 * against forsa_shap_entities), so it lives here too rather than being split
 * into a separate resolver for a single query.
 */
final class AliasResolver
{
    private const METRIC_ALIASES = [
        'ftk' => 'total_ftk',
        'formasi' => 'total_ftk',
        'kebutuhan pegawai' => 'total_ftk',
        'realisasi' => 'total_realisasi',
        'jumlah pegawai' => 'total_realisasi',
        'selisih' => 'gap',
        'kekurangan' => 'gap',
        'surplus' => 'gap',
        'gap' => 'gap',
        'pemenuhan' => 'fulfillment_rate',
        'fulfillment' => 'fulfillment_rate',
    ];

    private const DIMENSION_ALIASES = [
        'anak perusahaan' => 'company',
        'subholding' => 'company',
        'sh/ap' => 'company',
        'shap' => 'company',
        'company' => 'company',
        'periode' => 'period',
        'bulan' => 'period',
        'jenjang' => 'job_level',
        'jenjang jabatan' => 'job_level',
        'position grade' => 'position_grade',
        'grade' => 'position_grade',
        'organisasi' => 'organization',
        'unit' => 'organization',
    ];

    public function metricAlias(string $text): ?string
    {
        return self::METRIC_ALIASES[$this->normalize($text)] ?? null;
    }

    public function dimensionAlias(string $text): ?string
    {
        return self::DIMENSION_ALIASES[$this->normalize($text)] ?? null;
    }

    public function jobLevelLabelToGroup(string $text): ?string
    {
        static $reverse = null;
        if ($reverse === null) {
            $reverse = [];
            foreach (JobLevelMapper::orderedGroups() as $group) {
                $reverse[$this->normalize(JobLevelMapper::label($group))] = $group;
                $reverse[$this->normalize($group)] = $group;
            }
        }
        return $reverse[$this->normalize($text)] ?? null;
    }

    /**
     * Fuzzy-matches free text (e.g. "Indonesia Power") against
     * forsa_shap_entities.name/short_name/code. Returns matching shap ids —
     * empty array means "no such company", never guessed/invented.
     *
     * @return array<int, array{id: int, short_name: string}>
     */
    public function resolveCompany(string $text): array
    {
        $stmt = AiReaderConnection::connection()->prepare(
            'SELECT id, short_name FROM forsa_shap_entities
             WHERE is_active = TRUE
               AND (name ILIKE :q OR short_name ILIKE :q OR code ILIKE :q_exact)
             ORDER BY sort_order'
        );
        $stmt->execute(['q' => '%' . $text . '%', 'q_exact' => $text]);
        return $stmt->fetchAll();
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
    }
}
