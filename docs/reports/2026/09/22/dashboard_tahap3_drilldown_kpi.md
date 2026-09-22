# Dashboard Tahap 3 — Drilldown KPI

## Summary

Membuat 3 bagian dashboard clickable — kartu "Sebaran & Pemenuhan per SH/AP", baris "Gap Terbesar per SH/AP", dan baris "Pemenuhan FTK per Jenjang Jabatan" — dan membuka modal detail saat diklik. Seluruh angka pada modal berasal dari respons `dashboard_api.php` dan `ftk_tree_api.php` (root level) yang **sudah** ter-fetch untuk merender dashboard/tree yang sedang tampil; tidak ada endpoint baru maupun kalkulasi baru. Sekaligus menemukan dan memperbaiki bug lama pada filter jenjang tabel tree yang terekspos saat memverifikasi fitur ini.

## Files Changed

- [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — markup modal baru `#modal-detail` (title/body/footer dinamis via JS).
- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — affordance `.clickable` untuk `.shap-card`/`.bar-row`/`.stack-bar-row` (cursor, hover, focus-visible untuk `[role="button"]`), animasi one-shot `.row-flash` untuk highlight baris tree saat dituju dari modal.
- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js):
  - `lastDashboardData` & `rootTreeNodes` — cache di memori dari respons API yang sudah di-fetch, sumber tunggal data modal.
  - Markup `shapCards`/`gapRows`/`jenjangRows` ditambah `data-shap`/`data-group`, `role="button"`, `tabindex="0"`.
  - `openShapDetail()`, `openJenjangDetail()`, `openDetailModal()`/`closeDetailModal()`, `detailStatRow()`, `jenjangLabel()`.
  - `scrollToTreeAndExpandShap()` dan `applyJenjangFilterAndScroll()` — tombol footer modal memicu ulang mekanisme scroll-into-view/expand tree dan filter tree yang **sudah ada**, bukan implementasi baru.
  - `bindDashboardDrilldownClicks()` — satu delegated listener pada `#dashboard-content` (klik + keyboard Enter/Space), dipasang sekali (pola sama dengan fix delegated-listener tree sebelumnya) agar re-render dashboard antar periode tidak menumpuk listener.
  - **Bug fix**: `renderTreeHeader()` sebelumnya memanggil `document.getElementById('tree-filter-level')` dari dalam template string `renderDashboard()` — yaitu **sebelum** HTML itu di-assign ke DOM, sehingga selalu mengenai elemen `<select>` versi lama/belum ada, dan dropdown "Semua Jenjang" tidak pernah benar-benar terisi opsi jenjang. Dipecah menjadi `populateTreeLevelFilterOptions()` yang dipanggil setelah `content.innerHTML` di-assign.

## Database Changes

None.

## API Changes

None — tidak ada endpoint baru; `openShapDetail`/`openJenjangDetail` murni membaca ulang data yang sudah ada di memori JS.

## Architecture Changes

Tidak ada perubahan arsitektur backend. Pola baru di frontend: kedua modal memakai **derived view** dari dua cache klien yang sudah ada (`lastDashboardData`, `rootTreeNodes`) alih-alih memanggil API baru — memastikan angka pada modal selalu identik dengan angka yang sedang tampil di dashboard/tree utama (tidak mungkin "berbeda perhitungan").

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check public/assets/js/dashboard.js` — tanpa error.

## Manual Test

Di Browser pane (login sebagai Super Admin, snapshot PLN IP Agustus 2026 aktif):

1. Klik kartu "PLN IP" pada Sebaran & Pemenuhan per SH/AP → modal terbuka menampilkan FTK 5.652, Realisasi 4.702, Pemenuhan 83,2%, Gap -950 (identik dengan kartu), rincian realisasi (Organik 3.506 / Tugas Karya 1.196 / Pihak Ketiga 0), dan tabel per jenjang — semua cocok dengan tree/dashboard.
2. Klik footer "Lihat di Drill-down Tree" → modal tertutup, halaman scroll-smooth ke tabel tree, baris "PLN IP" otomatis expand (UI/UL/UP muncul) via mekanisme expand tree yang sudah ada, lalu row di-highlight sesaat (`.row-flash`, sekali saja, tidak berulang).
3. Klik baris "PLN IP -950" pada Gap Terbesar per SH/AP → modal detail yang sama terbuka (shared modal, sumber data sama).
4. Klik baris "Generalist 1-3" pada Pemenuhan FTK per Jenjang Jabatan → modal menampilkan FTK 5.220, Realisasi 4.321, Belum Dipenuhi 899 (cocok persis dengan angka pada chart), Kelebihan 0, dan tabel per-SH/AP untuk jenjang tersebut.
5. Klik footer "Filter Drill-down Berdasarkan Jenjang Ini" → modal tertutup, tree ter-filter ulang lewat mekanisme filter yang sudah ada (`treeFilters.job_level_group`), dropdown "Semua Jenjang" pada toolbar tree berubah otomatis menjadi "GEN 1-3", dan baris PLN IP pada tree menampilkan FTK 5.220 / Sisa 899 — cocok persis dengan angka di modal.
6. Aksesibilitas: elemen clickable diverifikasi menerima fokus keyboard (outline biru tampak), dan `keydown` Enter memicu modal yang sama seperti klik mouse (diverifikasi via dispatch event nyata setelah automation tool ternyata tidak mengirim keycode "Enter" yang presisi — bukan bug aplikasi). Escape menutup modal detail.
7. Regresi: memuat ulang dashboard (ganti periode via dropdown histori) tidak menumpuk event listener ganda (diverifikasi tidak ada baris dobel saat expand/collapse berulang, konsisten dengan pola delegated-listener yang sudah diverifikasi di Tahap sebelumnya).

## Known Limitations

- Modal "Sebaran/Gap per SH/AP" bergantung pada `rootTreeNodes` untuk rincian Organik/Tugas Karya/Pihak Ketiga/per-jenjang. Bila tree root belum selesai di-fetch (jaringan sangat lambat) tepat saat modal dibuka, bagian rincian tersebut sederhana ditiadakan (KPI ringkas dari `dashboard_api` tetap tampil) — bukan error, hanya degradasi bertahap; belum ada loading-state khusus untuk kondisi ini karena di praktiknya tree root selesai lebih dulu (fetch root berjalan paralel saat halaman baru dimuat, dan selesai dalam hitungan ratus milidetik pada data uji).
- Baris jenjang dengan FTK dan Realisasi sama-sama 0 sengaja tidak dibuat clickable ("jika datanya jelas dan memungkinkan") — tidak ada indikator visual terpisah yang menjelaskan *mengapa* baris itu tidak bisa diklik (hanya tidak memiliki cursor pointer/hover). Bisa ditambah tooltip di iterasi berikutnya bila dianggap perlu.
