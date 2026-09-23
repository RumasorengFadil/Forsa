# Dashboard Tahap 3 — Format Kolom Sisa

## Summary

Kolom "Sisa" pada tabel drill-down (dan kedua modal detailnya) sekarang: nilai positif tampil **hijau dengan prefix `+`**, nilai negatif tampil **merah**, nilai `0` tetap netral (abu-abu). Perubahan ini murni format tampilan (warna + prefix teks) — angka dan logic perhitungan Sisa (`FTK − Total Realisasi`) tidak diubah sama sekali.

## Konfirmasi Semantik Warna (Penting)

Sebelumnya, warna kolom Sisa sudah mengikuti makna bisnis PRD: `Sisa = FTK − Total Realisasi`, jadi **positif = posisi belum terpenuhi/shortage** (sebelumnya merah, "perlu perhatian") dan **negatif = realisasi melebihi FTK/surplus** (sebelumnya hijau). Permintaan Tahap 3 membalik ini (positif=hijau, negatif=merah), yang secara harfiah bertentangan dengan makna "shortage = perlu perhatian". Karena ini bisa jadi kesalahpahaman, saya konfirmasi dulu ke user sebelum menerapkan — **user memilih untuk membalik sesuai permintaan awal** (positif=hijau, negatif=merah). Ini sudah dicatat sebagai keputusan sadar di komentar CSS agar tidak "diperbaiki balik" secara tidak sengaja oleh sesi berikutnya.

## Files Changed

- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js):
  - Helper baru `fmtSisa(v)` — satu sumber kebenaran untuk kelas warna (`sisa-pos`/`sisa-neg`/`sisa-zero`) dan teks berprefix `+` untuk nilai positif, dipakai di semua tempat yang merender Sisa/Delta.
  - `metricCells()` (badan tabel tree, seluruh grup TOTAL + 9 jenjang) dan kedua modal detail (`openShapDetail`, `openJenjangDetail`) diarahkan memakai `fmtSisa()` alih-alih logic inline yang sebelumnya terduplikasi.
- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — `.sisa-pos`/`.sisa-neg` ditukar warnanya (`var(--green)`/`var(--red)`), dengan komentar menjelaskan ini keputusan sadar yang berlawanan dengan makna "positif = shortage" di PRD.

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None — perubahan presentasi murni, memakai warna yang sudah ada di palet (`--green`/`--red`/`--ink-soft`), tidak menambah warna baru.

## Documentation Updated

- Laporan ini (mencatat keputusan semantik warna secara eksplisit, termasuk konfirmasi dari user).

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`, cache-busting `asset_url()` memastikan versi terbaru selalu termuat):

1. Baris "PLN NP" (grup TOTAL, Sisa=950) → teks `+950`, warna hijau (`rgb(31,138,76)` = `--green`), class `sisa-pos`.
2. Drill-down sampai leaf jabatan "EXPERT — PoG 18/19/20" (Sisa=1) → `+1` hijau; "JUNIOR EXPERT..." (Sisa=2) → `+2` hijau.
3. Beberapa leaf jabatan dengan Sisa=0 (mis. "KEPALA SATUAN CORPORATE LEGAL") → teks `0` (tanpa prefix), warna netral abu-abu (`rgb(86,99,122)` = `--ink-soft`), class `sisa-zero`.
4. Ditemukan baris dengan Sisa negatif (Sisa=-3) → teks `-3`, warna merah (`rgb(192,57,43)` = `--red`), class `sisa-neg`.
5. Modal detail SH/AP (Tahap 4) dicek ulang — tabel "Sisa/Delta" dan tabel per-jenjang di dalamnya menampilkan format yang identik (`+950` hijau, `0` netral, `-3` merah) — konsisten karena memakai helper `fmtSisa()` yang sama dengan tabel utama.

## Known Limitations

- Format ini **tidak** diterapkan pada kolom "Sisa" di tabel contoh data pada modal *preview upload* (`upload_submit.php?action=preview`) — itu bagian dari alur upload, bukan bagian dari tabel drill-down dashboard yang diminta di Tahap 3, dan sengaja tidak disentuh untuk menjaga scope perubahan tetap sempit.

## Update — Perbaikan Rumus: `Sisa = Total Realisasi − FTK`

User meminta rumus tampilan Sisa dibalik: `Sisa = Total Realisasi − FTK` (bukan `FTK − Total Realisasi` seperti sebelumnya), dengan contoh eksplisit: FTK 2000/Realisasi 1900 → `-100` merah; FTK 2000/Realisasi 2100 → `+100` hijau.

### Kenapa Tidak Mengubah Database/Backend

Nilai `sisa_delta` yang tersimpan di `forsa_ftk_snapshot_rows` (dihitung `FtkParser` saat upload, memakai rumus `FTK − Total Realisasi`) adalah rumus resmi PRD untuk validasi import — **tidak diubah**, supaya tidak perlu migrasi data untuk snapshot yang sudah pernah diimpor, dan supaya modal preview upload (yang memvalidasi rumus dari file Excel) tetap konsisten dengan PRD.

Alih-alih itu, rumus baru diterapkan **hanya di layer tampilan dashboard**: `fmtSisa()` di `dashboard.js` sekarang me-negasikan nilai yang diterima dari API (`displayed = -(stored FTK − Total Realisasi) = Total Realisasi − FTK`). Ini aman dilakukan meski nilainya adalah hasil `SUM()` (agregasi TOTAL/per-grup di backend), karena secara matematis `-(jumlah) = jumlah dari (negasi masing-masing)` — jadi tidak perlu mengubah query SQL apa pun di `TreeService`.

Efek samping yang diinginkan: sekarang tanda dan warna kolom Sisa **sama persis** dengan KPI "Gap FTK" di bagian atas dashboard (yang memang sudah memakai `Total Realisasi − FTK`) — dua konvensi tanda yang sebelumnya sengaja dibiarkan berbeda (dicatat di `CLAUDE.md`) kini disatukan atas permintaan eksplisit user. Filter status gap (`Kurang`/`Terpenuhi`/`Lebih`) di toolbar tree **tidak terpengaruh** karena filter itu membaca nilai `sisa_delta` mentah dari database (bukan versi yang sudah dinegasikan untuk tampilan), dan makna "Kurang" (shortage) tidak bergantung pada konvensi tanda mana yang ditampilkan.

### Files Changed (Update)

- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js) — `fmtSisa()` menegasikan nilai sebelum menentukan warna/teks; komentar diperbarui menjelaskan hubungan dengan nilai tersimpan dan KPI Gap FTK.
- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — komentar `.sisa-pos`/`.sisa-neg` diperbarui (tidak lagi "berlawanan dengan PRD", melainkan "selaras dengan KPI Gap FTK").
- [CLAUDE.md](../../../../CLAUDE.md) — bagian "Two sign conventions" ditulis ulang untuk mencerminkan realita baru: nilai **tersimpan** tetap `FTK − Total Realisasi`, nilai **tampilan dashboard** adalah kebalikannya, dan filter gap-status tetap membaca nilai tersimpan (bukan versi tampilan).

### Manual Test (Update)

1. Baris "PLN NP" grup TOTAL: FTK `5.652`, Total Real. `4.702` → Sisa tampil `-950` (merah) — sebelumnya `+950` (hijau). Sesuai rumus baru: `4.702 − 5.652 = -950`.
2. Contoh dari user diverifikasi manual secara aritmatika (bukan lewat data uji, karena angka FTK 2000/Realisasi 1900 tidak ada di data uji saat ini): rumus `fmtSisa` untuk `stored = FTK − Realisasi = 2000 − 1900 = 100` menghasilkan `display = -100` → merah, prefix `-` — cocok. Untuk `stored = 2000 − 2100 = -100` menghasilkan `display = +100` → hijau, prefix `+` — cocok.
3. `php -l` dan `node --check` tetap bersih.

### Known Limitations (Update)

- Tidak ada data uji dengan FTK persis 2000 di database saat ini, sehingga verifikasi angka contoh dari user dilakukan via penelusuran rumus (aritmatika), bukan lewat tampilan browser langsung dengan angka yang sama persis — perilaku fungsinya sudah diverifikasi lewat data lain (PLN NP: 4.702 − 5.652 = -950) yang membuktikan rumus baru diterapkan dengan benar.
