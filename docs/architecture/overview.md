# Arsitektur FORSA

FORSA menggunakan PHP Native modular berbasis domain bisnis (bukan MVC framework penuh), sesuai PRD §23.

```
request → router.php (route_map.php) → module page → auth guard → query/service → PostgreSQL → render HTML/JSON
```

## Lapisan

- **shared/** — bootstrap (session, DB, CSRF), helper lintas modul (`Database`, `JobLevelMapper`, auth guard, audit log, response helper).
- **modules/auth** — login/logout.
- **modules/dashboard** — `DashboardService` melakukan seluruh agregasi KPI/SH-AP/jenjang/insight dari `forsa_ftk_snapshots` + `forsa_ftk_snapshot_rows`. `dashboard.php` adalah halaman tunggal yang menggabungkan dashboard, tombol upload (modal), dan tab Histori Upload — sesuai permintaan agar dashboard, upload, dan histori berada dalam satu halaman.
- **modules/import** — `FtkParser` (parsing + validasi + normalisasi Excel via PhpSpreadsheet), `SnapshotWriter` (transaksi penulisan snapshot + aktivasi), `upload_submit.php` (endpoint preview & confirm dua tahap).
- **modules/ftk** — `TreeService` melakukan agregasi lazy per-level (SH/AP → L2 → L3 → L4 → Jabatan) dengan aturan "node kosong dilewati" (PRD §17.2).
- **modules/administrasi** — CRUD user sederhana.

## Alur Upload → Snapshot → Dashboard

```
Excel (.xlsx)
  → FtkParser::parse()      (validasi header, baris, formula, mapping jenjang)
  → preview (AJAX, token sesi mengacu file temp)
  → confirm (AJAX)
  → forsa_import_jobs (audit proses)
  → SnapshotWriter::write() (transaksi: nonaktifkan snapshot lama, insert snapshot+rows baru, aktifkan)
  → forsa_ftk_snapshots (is_active) + forsa_ftk_snapshot_rows
  → DashboardService/TreeService membaca snapshot (bukan Excel) via SQL aggregate
```

Dashboard **tidak pernah** membaca file Excel langsung — hanya snapshot database (PRD §27, §40).

## Single-Page Dashboard

`dashboard.php` + `assets/js/dashboard.js` mengimplementasikan:

1. **Dashboard tab** — KPI, sebaran SH/AP, status prioritas, gap terbesar, pemenuhan per jenjang, management insight, dan tabel tree drill-down lazy-load (semua via `dashboard_api.php`, `history_api.php`, `ftk_tree_api.php`).
2. **Upload modal** — 3 langkah (form → preview/validasi → konfirmasi) memanggil `upload_submit.php?action=preview|confirm`.
3. **Histori Upload tab** — daftar seluruh `forsa_import_jobs` (paginated) via `upload_history_api.php`.

Ketiganya berada pada satu halaman (`dashboard.php`) tanpa reload penuh, sesuai instruksi implementasi.
