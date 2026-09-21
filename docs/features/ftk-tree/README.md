# Fitur: FTK Drill-down Tree

Implementasi: `modules/ftk/TreeService.php`, `ftk_tree_api.php`, bagian tree pada `public/assets/js/dashboard.js`.

## Level

```
Root  : SH/AP (per snapshot dalam selection)
L2    : organization_level_2  (UI/UP/UL)
L3    : organization_level_3  (Unit Pelaksana/Kantor Pusat)
L4    : organization_level_4  (Unit Layanan)
Leaf  : position_name (+ position_grade)
```

## Aturan Node Kosong (PRD §17.2)

`TreeService::children()` mengelompokkan baris berdasarkan field level berikutnya; baris yang field-nya `NULL` pada level tersebut **langsung** menjadi leaf jabatan pada node saat ini (tidak membuat node kosong). Ini diuji manual: cabang `UI → KP` pada data contoh tidak memiliki Unit Layanan sehingga jabatan muncul langsung di bawah `KP`.

## Lazy Loading

Setiap level di-load on-demand melalui `GET ftk_tree_api.php?selection=...&shap=<code>&path_json=[...]` — tidak pernah memuat seluruh tree sekaligus, sesuai target performa PRD §29.

## Grouped Metrics (PRD §18, AC-08)

Setiap node mengembalikan `metrics.total` dan `metrics.groups[<job_level_group>]`, masing-masing berisi `ftk, realisasi_organik, realisasi_tugas_karya, realisasi_pihak_ketiga, total_realisasi, sisa_delta`. `Sisa/Delta = FTK − Total Realisasi` (arah rumus konsisten dengan template upload, berbeda dari Gap KPI dashboard).

## Filter

`search`, `job_level_group`, `position_grade`, `gap_status` (kurang/terpenuhi/lebih) diteruskan sebagai kondisi `WHERE` SQL pada setiap query level sehingga filter berlaku konsisten di seluruh kedalaman tree.
