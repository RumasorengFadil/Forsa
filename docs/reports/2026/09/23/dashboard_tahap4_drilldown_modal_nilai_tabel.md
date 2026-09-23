# Dashboard Tahap 4 — Drill-down Modal Nilai Tabel

## Summary

Setiap nilai (angka) pada tabel drill-down FTK yang memang memiliki data detail sekarang **clickable**: klik membuka modal berisi rincian sesuai cell yang diklik (baris + grup jenjang), memakai `ftk_tree_api.php` yang sama persis dengan mekanisme expand/filter tree yang sudah ada — tidak ada endpoint atau perhitungan baru. Nilai `0` tidak pernah bisa diklik.

## Desain

Setiap cell nilai berada pada kombinasi (baris/node, grup jenjang, metrik). Ada dua jenis grup:

1. **Kolom grup TOTAL** — klik nilai apa pun di grup TOTAL untuk baris manapun membuka modal berisi **breakdown per jenjang** untuk baris itu (Manajemen Atas, Manajemen Menengah, ..., Generalist 1-3, dst.), diambil langsung dari `node.metrics.groups` yang **sudah ter-load di client** untuk baris tersebut (root maupun hasil expand) — tidak perlu fetch baru sama sekali.
2. **Kolom grup jenjang spesifik** (mis. "MA", "GEN 1-3") — klik nilai membuka modal berisi **breakdown anak organisasi di bawah baris ini, difilter ke jenjang tersebut** — memanggil `ftk_tree_api.php` dengan parameter `job_level_group` di-set ke grup yang diklik, persis mekanisme yang sama dipakai saat user meng-expand baris tree biasa atau menerapkan filter "Semua Jenjang" di toolbar — hanya override parameter filter untuk satu panggilan, tanpa mengubah filter global tabel.

## Aturan Clickable

- Nilai `0` **tidak pernah** clickable (dicek dari nilai mentah, termasuk untuk kolom Sisa yang formatnya `+`/`-`/netral dari Tahap 3).
- Kolom TOTAL selalu clickable selama nilainya bukan nol (breakdown per jenjang selalu bisa dihitung dari data yang sudah ada).
- Kolom grup jenjang spesifik hanya clickable jika baris punya anak (`node.has_children`) — baris leaf jabatan (paling bawah, sudah 1 jenjang tunggal) tidak clickable untuk grup spesifik karena tidak ada lagi yang bisa di-drill lebih dalam untuk satu jenjang tersebut.

## Files Changed

- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js):
  - `nodeCache` (Map) — menyimpan objek node lengkap (path, snapshot_id, has_children, metrics) untuk setiap baris yang pernah dirender (root load + setiap expand), dikunci oleh `node.key`, plus properti `__shap` (kode SH/AP yang di-resolve, dipakai untuk memanggil ulang API tanpa menebak ulang).
  - `fetchNodes(shapCode, path, filterOverrides)` — parameter ke-3 baru (opsional) untuk meng-override filter tree HANYA untuk satu panggilan (dipakai modal), tanpa mengubah `treeFilters` global yang dipakai tabel utama.
  - `metricCells()` — sekarang menerima `rowKey`/`group`/`canDrill`; membungkus nilai non-nol yang boleh di-drill dengan `<button class="tree-val-btn">`, sisanya tetap teks polos seperti sebelumnya.
  - `renderJenjangBreakdownTable(node)` — diekstrak dari `openShapDetail()` (Tahap 3) menjadi fungsi reusable, dipakai juga oleh modal TOTAL Tahap 4 ini (menghindari logic breakdown-per-jenjang terduplikasi dua kali).
  - `expandAndScrollToRow(rowKey)` — versi generik dari `scrollToTreeAndExpandShap()` (yang khusus baris root SH/AP), bekerja untuk baris manapun yang sedang dirender.
  - `openTreeValueDetail(rowKey, group, metric)` — handler utama modal Tahap 4, dengan tombol footer "Lihat di Drill-down Tree" yang menutup modal lalu expand+scroll ke baris terkait di tabel utama.
  - `attachTreeHandlers()` — listener klik yang sama (didelegasikan ke `document`, sudah ada dari perbaikan Tahap 1) diperluas untuk juga menangkap klik `.tree-val-btn` di samping `.tree-toggle`.
  - **Perbaikan sekalian**: `fmtSisa()` (Tahap 3) menormalkan `-0` menjadi `0` (`-(x) + 0`), karena beberapa cell dengan Sisa tepat nol sebelumnya sempat menampilkan teks `"-0"` alih-alih `"0"` akibat negative zero di JavaScript — ditemukan saat verifikasi modal TOTAL breakdown Tahap 4 ini.
- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — `.tree-val-btn` (reset tombol jadi terlihat seperti teks biasa, warna mengikuti kelas Sisa induknya, affordance hover/focus halus).

## Database Changes

None.

## API Changes

None — `ftk_tree_api.php` dipanggil dengan parameter `job_level_group` yang sudah ada, tidak ada endpoint atau parameter baru.

## Architecture Changes

None.

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`, cache-busting `asset_url()` memastikan versi terbaru):

1. Baris "PLN NP" grup TOTAL: 39 dari 60 cell metrik terverifikasi menjadi `<button class="tree-val-btn">` (sisanya nol, tetap teks polos) — dicek lewat `querySelectorAll`.
2. Klik nilai FTK (5.652) pada grup TOTAL baris "PLN NP" → modal "PLN NP — TOTAL" terbuka, subtitle "Rincian dipicu dari kolom FTK", KPI ringkas (5.652/4.702), dan tabel breakdown 8 jenjang dengan Sisa yang benar (termasuk `0` bukan lagi `-0` setelah perbaikan) — tanpa fetch baru (`renderJenjangBreakdownTable` dari data yang sudah ter-load).
3. Klik nilai FTK (9) pada grup "MA" baris "PLN NP" → modal "PLN NP — MA" menampilkan breakdown anak organisasi (UI: FTK 5, UP: FTK 4 — jumlah 9, cocok dengan cell yang diklik), hasil query `ftk_tree_api.php` sungguhan dengan `job_level_group=MA`.
4. Klik "Lihat di Drill-down Tree" pada modal MA → modal tertutup, baris "PLN NP" otomatis expand (`aria-expanded="true"`, anak UI/UL/UP muncul di tabel utama) dan di-highlight (`row-flash`).
5. Drill-down sampai baris leaf jabatan asli ("EXPERT — PoG 18/19/20"): hanya 2 cell (grup TOTAL) yang clickable, **nol** cell grup jenjang spesifik yang clickable — sesuai aturan "leaf tidak punya lagi yang bisa di-drill per jenjang tunggal".
6. Klik nilai TOTAL pada leaf tersebut → modal "EXPERT — TOTAL" menampilkan breakdown 1 baris jenjang ("Expert: 1/0/-1") — benar, karena satu jabatan hanya masuk 1 jenjang.
7. Cell dengan nilai `0` dicek langsung via DOM — tidak dibungkus `<button>` sama sekali (`hasButton: false`), murni teks `"0"`, tidak bisa diklik.

## Known Limitations

- Modal grup-jenjang-spesifik (langkah 3) tidak melakukan pembatalan permintaan (request cancellation) bila user menutup modal atau mengklik cell lain sebelum fetch selesai — untuk skala data saat ini responsnya sangat cepat (<1 detik) sehingga risiko race condition dianggap dapat diterima untuk MVP; bisa ditambah guard token bila diperlukan nanti.
- Parameter `metric` yang diklik (mis. "FTK" vs "Organik" pada grup yang sama) hanya ditampilkan sebagai keterangan teks ("Rincian dipicu dari kolom X") di header modal — isi tabel breakdown tetap menampilkan semua metrik (FTK/Organik/Tugas Karya/Pihak Ketiga/Total Real./Sisa) karena `TreeService` tidak (dan tidak perlu) memfilter per kolom metrik, hanya per jenjang/grade/status gap/search.
