# Implementasi Awal FORSA — MVP Tahap 1–4

## Summary

Implementasi awal aplikasi FORSA (Formasi & Realisasi SH/AP) sesuai PRD: login, upload & snapshot histori, dashboard mengikuti mockup, dan drill-down FTK tree. Sesuai instruksi eksplisit, dashboard, upload, dan histori upload digabung dalam satu halaman (`dashboard.php`) menggunakan tab + modal, alih-alih tiga halaman terpisah seperti pada struktur navigasi PRD §4 (tetap dapat dipisah di iterasi berikutnya tanpa mengubah backend).

## Files Changed

Proyek baru — seluruh struktur dibuat dari kosong mengikuti `docs/product/PRD.md` §24 (struktur folder), termasuk `config/`, `database/migrations|seeds`, `modules/{auth,dashboard,import,ftk,administrasi}`, `shared/`, `public/assets/{css,js}`, `router.php`, `route_map.php`, `.htaccess`, `tools/seed_admin.php`.

## Database Changes

5 migration + 2 seed dijalankan (lihat `docs/database/migrations.md`). Schema `forsa` dibuat di database Postgres lokal `forsa`.

## API Changes

Endpoint JSON baru: `dashboard_api.php`, `history_api.php`, `ftk_tree_api.php`, `upload_submit.php` (preview/confirm), `upload_history_api.php`, `upload_errors_api.php`, `upload_detail_api.php`, `user_create.php`, `user_edit.php`, `user_toggle_status.php`, `user_reset_password.php`.

## Architecture Changes

Front controller `router.php` + `route_map.php` (kompatibel PHP built-in server & Apache mod_rewrite). Service layer: `DashboardService`, `TreeService`, `FtkParser`, `SnapshotWriter`.

## Documentation Updated

`docs/README.md`, `docs/architecture/overview.md`, `docs/database/{schema,migrations}.md`, `docs/security/{authentication,authorization}.md`, `docs/features/{dashboard,import,ftk-tree,user-management}/README.md`, `docs/deployment/installation.md`.

## Tests Performed

- `php -l` pada seluruh file PHP (tanpa error).
- Manual test end-to-end via `curl` (session cookie jar): login → dashboard kosong (empty state) → upload preview → upload confirm → dashboard KPI terisi → tree drill-down (root → L2 → L3, termasuk kasus L4 kosong `UI → KP` yang membuktikan node kosong dilewati) → histori upload tab.
- Verifikasi visual di Browser pane: login, dashboard (KPI, sebaran SH/AP, status prioritas, gap terbesar, pemenuhan per jenjang, management insight, tabel tree dengan expand/collapse), tab Histori Upload, modal Upload (step 1), halaman Manajemen User.

## Manual Test

Menggunakan file asli `Masking data FTK (1) - PoG fixed.xlsx` (3863 baris data) diupload sebagai PT PLN Indonesia Power periode Agustus 2026:

- Preview: 3863 valid rows, 0 error, 0 warning, Total FTK 5.652, Total Realisasi 4.702 (cocok dengan baris "Total" pada file sumber: Organik 3.506 + Tugas Karya 1.196 = 4.702).
- Confirm: snapshot berhasil dibuat, revisi 1, aktif.
- Dashboard: Pemenuhan 83,2%, Gap -950; tree drill-down PLN IP → UI/UL/UP dengan total yang menjumlah benar (821+674+4.157=5.652).

## Known Limitations

- Fitur "Rencana Pemenuhan" tersimpan tapi belum ditampilkan di UI drill-down (kolom tersedia di skema & parser).
- Search pada tree tree memfilter baris secara langsung (bukan pencarian bertingkat yang mempertahankan agregat penuh cabang) — agregat pada mode pencarian merefleksikan hanya baris yang cocok.
- Belum ada test otomatis (`tests/*_test.php` pada PRD §24 belum diimplementasikan); verifikasi dilakukan manual pada tahap ini.
- Halaman terpisah untuk Upload & Histori Upload (sesuai §4 PRD) belum dibuat sebagai halaman mandiri karena instruksi eksplisit menggabungkannya ke satu halaman dashboard.
