# Dashboard Drill-down: Tombol Reset Filter & Rename Kolom Sisa → Gap

## Summary

Pada section **DRILL-DOWN SH/AP › UI/UP/UL › Jenjang Jabatan › Position Grade › Jabatan (masked)**:
1. Ditambahkan tombol **Reset Filter** di toolbar (di samping "Terapkan") yang mengembalikan pencarian, jenjang, position grade, dan status gap ke kondisi kosong/awal sekaligus.
2. Header kolom **Sisa** diganti jadi **Gap** di tabel drill-down utama dan kedua modal yang dipicu langsung dari tabel itu (breakdown per jenjang, dan modal nilai tabel Tahap 4).

**Logic filter dan perhitungan tidak diubah sama sekali** — ini murni perubahan label teks dan satu tombol baru yang memanggil ulang jalur fetch/render yang sama persis dengan "Terapkan".

## Files Changed

- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js):
  - Tambah `<button id="btn-reset-tree-filter">Reset Filter</button>` di toolbar tree.
  - Fungsi baru `resetTreeFilters()` — set keempat field `treeFilters` ke `''`, panggil `restoreTreeFilterInputs()` (fungsi yang sudah ada dari perbaikan C1) untuk mengosongkan tampilan input, `syncUrlState()` untuk membersihkan query string, lalu `renderTree()` — jalur yang identik dengan `applyTreeFilters()`.
  - Listener klik `#btn-reset-tree-filter` ditambahkan ke delegated click handler yang sudah ada.
  - Label "Sisa" → "Gap" di 4 tempat: `metricCols` (header tabel utama), `renderJenjangBreakdownTable()` (breakdown per jenjang — dipakai modal TOTAL Tahap 4), `METRIC_LABELS.sisa_delta` (subtitle modal), dan header tabel breakdown anak-organisasi di `openTreeValueDetail()`.
  - Caption scroll mobile ("Geser tabel ke kanan untuk melihat kolom FTK, Realisasi, dan Sisa.") diperbarui jadi "...dan Gap."

## Keputusan Cakupan Rename

`renderJenjangBreakdownTable()` dipakai bersama oleh dua modal: modal nilai tabel drill-down (Tahap 4, di dalam scope permintaan ini) dan modal detail SH/AP yang dipicu dari card "Sebaran & Pemenuhan per SH/AP" (di luar section drill-down). Karena keduanya memakai fungsi render yang sama, rename ini otomatis berlaku di kedua modal — daripada membuat dua versi label yang berbeda untuk data yang identik. Modal detail **jenjang** (dipicu dari chart "Pemenuhan FTK per Jenjang Jabatan") dan modal preview upload (`upload_submit.php`) **sengaja tidak disentuh** karena berada di luar section "DRILL-DOWN SH/AP" yang diminta.

## Database Changes
None.

## API Changes
None — `ftk_tree_api.php` tidak berubah, field data (`sisa_delta`) dan seluruh logic filter/agregasi tetap sama; hanya label tampilan yang berubah.

## Architecture Changes
None.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `php -l modules/dashboard/dashboard.php` — tanpa error.
- `node --check public/assets/js/dashboard.js` — tanpa error.

## Manual Test
Di Browser pane (`http://localhost:8888/Forsa/dashboard`):
1. Isi pencarian "PLN NP" + klik "Terapkan" → URL jadi `...?selection=...&q=PLN+NP`.
2. Klik "Reset Filter" → seluruh input (`tree-search`, `tree-filter-level`, `tree-filter-grade`, `tree-filter-gap`) kembali `""`, dan URL kembali ke `...?selection=...` tanpa `q`/`level`/`grade`/`gap` — dicek langsung lewat `document.getElementById(...).value` dan `location.href`.
3. Header tabel drill-down dicek lewat `textContent` semua `<thead>` — seluruh 10 grup jenjang (TOTAL + 9 jenjang) menampilkan "Gap" pada kolom terakhir, tidak ada lagi teks "Sisa".
4. Klik salah satu nilai TOTAL yang clickable → modal breakdown per jenjang menampilkan header "FTK | Realisasi | Gap" dengan nilai dan warna (`+3`, `-899`, dst.) tidak berubah dari sebelumnya — mengonfirmasi hanya label yang berubah, bukan angkanya.

## Known Limitations
- Modal detail jenjang (dari chart "Pemenuhan FTK per Jenjang Jabatan") dan tabel contoh data pada preview upload masih menampilkan label "Sisa" — di luar cakupan permintaan ("bagian DRILL-DOWN SH/AP..."). Beri tahu kalau ingin disamakan juga.
