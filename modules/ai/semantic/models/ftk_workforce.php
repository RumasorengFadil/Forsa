<?php

declare(strict_types=1);

/**
 * Semantic model `ftk_workforce` — approved proposal, see
 * docs/ai-assistant/semantic-layer.md. Pure data (SQL fragments as strings),
 * no query execution here. `sql` fragments reference the fixed join alias
 * used by QueryBuilder: r = forsa_ftk_snapshot_rows, s = forsa_ftk_snapshots,
 * e = forsa_shap_entities.
 */
return [
    'name' => 'ftk_workforce',
    'source' => 'forsa_ftk_snapshot_rows',

    'metrics' => [
        'total_ftk' => ['sql' => 'SUM(r.ftk)'],
        'total_realisasi_organik' => ['sql' => 'SUM(r.realisasi_organik)'],
        'total_realisasi_tugas_karya' => ['sql' => 'SUM(r.realisasi_tugas_karya)'],
        'total_realisasi_pihak_ketiga' => ['sql' => 'SUM(r.realisasi_pihak_ketiga)'],
        'total_realisasi' => ['sql' => 'SUM(r.total_realisasi)'],
        // Dashboard DISPLAY convention (Total Realisasi - FTK), not the
        // stored sisa_delta convention (FTK - Total Realisasi) — see
        // CLAUDE.md's "Sisa/Delta has one stored/backend convention and one
        // dashboard-display convention" note. Kept identical here so the AI
        // never disagrees with what the user sees on screen.
        'gap' => ['sql' => '(SUM(r.total_realisasi) - SUM(r.ftk))'],
        'fulfillment_rate' => ['sql' => '(CASE WHEN SUM(r.ftk) = 0 THEN NULL ELSE SUM(r.total_realisasi)::numeric / SUM(r.ftk) * 100 END)'],
    ],

    // 'organization' intentionally maps to organization_level_2 only for
    // this phase (the top of the UI/UP/UL hierarchy) — PRD's own example
    // questions (§11-13) never drill past company/organization, and going
    // to level_3/4 needs a path-aware grouping the tool doesn't ask for yet.
    // Documented as a known limitation in the Tahap 4 report.
    'dimensions' => [
        'company' => ['sql' => 'e.short_name'],
        'period' => ['sql' => 's.period_month'],
        'job_level' => ['sql' => 'r.job_level_group'],
        'position_grade' => ['sql' => 'r.position_grade'],
        'organization' => ['sql' => 'r.organization_level_2'],
    ],
];
