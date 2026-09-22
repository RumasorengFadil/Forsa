# Dashboard Tahap 4 — Urutan Header Jenjang Jabatan

## Summary

Mengubah urutan kolom jenjang jabatan pada tabel drill-down FTK menjadi:
`TOTAL → MA → MM → MD → SR. SPECIALIST → SPECIALIST → GEN 1-3 → SR. EXPERT → EXPERT → JR. EXPERT` (dikonfirmasi ke user setelah permintaan awal menyebut "JR. SPECIALIST" yang ternyata bukan grup jenjang yang ada — lihat klarifikasi di percakapan). Urutan ini **hanya** memengaruhi kolom tabel tree (dan turunannya: dropdown filter jenjang, tabel per-jenjang pada modal detail SH/AP dari Tahap 3); grafik "Pemenuhan FTK per Jenjang Jabatan" di dashboard sengaja **tidak** ikut berubah karena itu tetap diurutkan berdasarkan besar FTK untuk keterbacaan chart, bukan urutan organisasi.

## Files Changed

- [config/ftk_rules.php](../../../../config/ftk_rules.php) — `job_level_order` diubah ke urutan baru; komentar diperbarui untuk menjelaskan bahwa ini adalah urutan kolom tree (Tahap 4), terpisah dari sorting bar chart.
- [modules/dashboard/DashboardService.php](../../../../modules/dashboard/DashboardService.php) — payload `dashboard_api.php` ditambah field `job_level_order` (langsung dari `JobLevelMapper::orderedGroups()`), agar frontend punya sumber urutan kolom yang eksplisit tanpa menduplikasi konfigurasi di JS.
- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js) — `renderTreeHeader(data.per_jenjang.map(j => j.group))` (ikut ter-sort FTK, salah untuk kolom tabel) diganti menjadi `renderTreeHeader(data.job_level_order)` (urutan tetap sesuai konfigurasi).

## Database Changes

None.

## API Changes

`dashboard_api.php` — respons bertambah satu field baru: `data.job_level_order` (array string, urutan kanonis grup jenjang untuk kolom tree). Field lain tidak berubah; tidak breaking untuk konsumen lama yang mengabaikan field ini.

## Architecture Changes

Tidak ada; ini murni perubahan urutan tampilan yang di-drive dari konfigurasi terpusat (`config/ftk_rules.php`) sesuai konvensi proyek ("business rules config-driven, not hardcoded" — lihat `CLAUDE.md`).

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check public/assets/js/dashboard.js` — tanpa error.
- `curl` ke `dashboard_api.php` — mengonfirmasi `job_level_order` berisi `["MA","MM","MD","Senior Specialist","spesialist","gen 1-3","Senior Expert","Expert","Junior Expert"]`.

## Manual Test

Di Browser pane (snapshot PLN IP Agustus 2026 aktif):

1. Header grup tabel tree dibaca via JS (`document.querySelectorAll('.tree-table thead tr.group-row th')`) menghasilkan urutan persis: `UNIT/ORGANISASI/JABATAN, TOTAL, MA, MM, MD, SR. SPECIALIST, SPECIALIST, GEN 1-3, SR. EXPERT, EXPERT, JR. EXPERT`.
2. Dropdown filter "Semua Jenjang" pada toolbar tree ikut menampilkan opsi dalam urutan yang sama.
3. Grafik "Pemenuhan FTK per Jenjang Jabatan" **tetap** dalam urutan FTK terbesar→terkecil (Generalist 1-3, Manajemen Dasar, Specialist, Manajemen Menengah, Manajemen Atas, Senior Specialist, Junior Expert, Senior Expert, Expert) — tidak berubah, dikonfirmasi tidak ikut ter-reorder oleh perubahan ini.
4. Ekspansi baris "PLN IP" pada tree lalu dibaca seluruh sel numerik per kolom via JS — setiap grup (MA, MM, MD, SR. SPECIALIST, SPECIALIST, GEN 1-3, SR. EXPERT, EXPERT, JR. EXPERT) menampilkan FTK/Organik/Tugas Karya/Pihak Ketiga/Total Realisasi/Sisa yang identik dengan angka yang sebelumnya sudah diverifikasi benar pada Tahap 3 (mis. GEN 1-3 tetap FTK 5.220 / Sisa 899) — memastikan hanya urutan yang berubah, data tidak bergeser/salah kolom.
5. Modal detail SH/AP (fitur Tahap 3) dicek ulang: tabel "Jenjang" di dalamnya otomatis ikut memakai urutan baru (karena kode Tahap 3 sengaja diprogram membaca `treeGroups` yang sama dengan header tree) — tidak perlu perubahan kode tambahan di modal.

## Known Limitations

- Permintaan awal Tahap 4 menyebut "JR. SPECIALIST" yang tidak sesuai grup manapun di `config/ftk_rules.php`; setelah dua putaran klarifikasi, user mengonfirmasi urutan final tanpa duplikasi "Senior Specialist". Bila ke depannya bisnis benar-benar butuh pemisahan "Junior Specialist" sebagai grup baru, itu perubahan mapping (`job_level_map`) yang terpisah dari Tahap 4 ini dan harus melalui perubahan skema mapping, bukan sekadar reorder.
