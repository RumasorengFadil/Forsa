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

    // Display order for job level groups across dashboard & tree
    'job_level_order' => [
        'gen 1-3',
        'MD',
        'MM',
        'MA',
        'spesialist',
        'Senior Specialist',
        'Junior Expert',
        'Expert',
        'Senior Expert',
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
