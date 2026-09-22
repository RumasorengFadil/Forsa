<?php

declare(strict_types=1);

return [
    // Mapping jenjang jabatan (raw, case-insensitive-trimmed) => job_level_group
    'job_level_map' => [
        'generalist 1' => 'gen 1-3',
        'generalist 2' => 'gen 1-3',
        'generalist 3' => 'gen 1-3',
        'gen 1-3' => 'gen 1-3',
        'manajemen dasar' => 'MD',
        'md' => 'MD',
        'manajemen menengah' => 'MM',
        'mm' => 'MM',
        'manajemen atas' => 'MA',
        'ma' => 'MA',
        'specialist' => 'spesialist',
        'spesialist' => 'spesialist',
        'senior specialist' => 'Senior Specialist',
        'expert' => 'Expert',
        'senior expert' => 'Senior Expert',
        'junior expert' => 'Junior Expert',
    ],

    // Canonical column order for the FTK drilldown tree (Tahap 4):
    // Total → MA → MM → MD → SR. SPECIALIST → SPECIALIST → GEN 1-3 → SR. EXPERT → EXPERT → JR. EXPERT
    // ("Total" is added separately by the frontend, not listed here).
    // This does NOT affect the "Pemenuhan FTK per Jenjang Jabatan" dashboard
    // bar chart, which is independently sorted by FTK size for readability
    // (see DashboardService::buildDashboard()).
    'job_level_order' => [
        'MA',
        'MM',
        'MD',
        'Senior Specialist',
        'spesialist',
        'gen 1-3',
        'Senior Expert',
        'Expert',
        'Junior Expert',
    ],

    'job_level_labels' => [
        'gen 1-3' => 'Generalist 1-3',
        'MD' => 'Manajemen Dasar',
        'MM' => 'Manajemen Menengah',
        'MA' => 'Manajemen Atas',
        'spesialist' => 'Specialist',
        'Senior Specialist' => 'Senior Specialist',
        'Junior Expert' => 'Junior Expert',
        'Expert' => 'Expert',
        'Senior Expert' => 'Senior Expert',
    ],

    // Status prioritas pemenuhan thresholds (percentage of pemenuhan)
    'fulfillment_thresholds' => [
        ['min' => 100, 'max' => null, 'code' => 'fulfilled', 'label' => 'Terpenuhi/lebih', 'color' => '#1f8a4c'],
        ['min' => 90, 'max' => 99.9999, 'code' => 'monitor', 'label' => 'Perlu monitoring', 'color' => '#c98a1f'],
        ['min' => null, 'max' => 89.9999, 'code' => 'priority', 'label' => 'Prioritas pemenuhan', 'color' => '#c0392b'],
    ],

    'empty_org_values' => ['-', '', null],

    'required_headers' => [
        'SEBUTAN JABATAN',
        'JENJANG JABATAN',
        'UI/UP/UL',
        'UNIT PELAKSANA/KANTOR PUSAT',
        'UNIT LAYANAN',
        'POSITION GRADE(PoG)',
        'FTK',
        'Organik',
        'Tugas Karya',
        'Pihak Ketiga',
        'Total Realisasi',
        'Sisa',
    ],
];
