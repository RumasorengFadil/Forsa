# Dashboard Tahap 2 — Verifikasi Garis Pemisah Sampai Level Jabatan + Perbaikan Cache Aset

## Summary

User melaporkan garis pemisah antar grup jenjang (TOTAL/MA/MM/...) hanya terlihat "pada jabatan aja" dan meminta garis ditarik sampai level terbawah (baris jabatan/posisi individual). Investigasi menemukan: **secara struktural garis sudah menjangkau seluruh level** (diverifikasi presisi sampai baris jabatan asli — "EXPERT — PoG 18/19/20", leaf terdalam), tapi kemungkinan besar apa yang dilihat user adalah **CSS versi lama yang ter-cache di browser** — bug yang sama persis ditemukan saat verifikasi Tahap 2 sebelumnya (server sudah menyajikan file benar via `curl`, tapi browser preview menahan versi lama meski di-hard-refresh). Kali ini diperbaiki secara permanen dengan cache-busting query string pada semua aset statis, bukan sekadar disiasati saat verifikasi.

## Root Cause

Apache/MAMP tidak mengirim header `Cache-Control`/`ETag` khusus untuk file di `public/assets/`, sehingga browser bebas meng-cache `forsa.css`/`dashboard.js`/`users.js` berdasarkan heuristiknya sendiri — pada praktiknya ini membuat perubahan CSS/JS tidak langsung terlihat meski file di server sudah berubah, bahkan setelah hard refresh manual. Ini sudah dua kali menyebabkan kebingungan verifikasi dalam sesi ini.

## Fix

1. **Verifikasi struktural (bukan visual) bahwa garis pemisah memang sudah benar sampai leaf**: drill-down manual sampai baris jabatan asli (`PLN NP → UI → KP → "EXPERT — PoG 18/19/20"`, node dengan titik/leaf-dot, bukan tombol expand), lalu `getComputedStyle()` pada kolom "Sisa" (border-right) dan kolom "FTK" grup berikutnya (border-left) — keduanya `3px`, sama seperti di level SH/AP root. Tidak ada perbedaan penanganan CSS antara baris org dan baris leaf (selector `.tree-table tbody td:nth-child(...)` memang generik, tidak bergantung pada jenis/kedalaman baris).
2. **Perbaikan permanen cache**: menambahkan `asset_url()` (`shared/response.php`) yang menghasilkan `assets/css/forsa.css?v=<filemtime>` — versi query string berbasis waktu modifikasi file di disk. Setiap kali file CSS/JS berubah, URL-nya otomatis berubah, sehingga browser manapun (termasuk yang sebelumnya menahan cache lama) dipaksa mengambil versi terbaru tanpa perlu hard refresh manual.

## Files Changed

- [shared/response.php](../../../../shared/response.php) — fungsi baru `asset_url()`.
- [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — `<link>` forsa.css dan `<script>` dashboard.js memakai `asset_url()`.
- [modules/auth/login.php](../../../../modules/auth/login.php) — `<link>` forsa.css memakai `asset_url()`.
- [modules/administrasi/users.php](../../../../modules/administrasi/users.php) — `<link>` forsa.css dan `<script>` users.js memakai `asset_url()`.
- [CLAUDE.md](../../../../CLAUDE.md) — catatan konvensi baru: aset statis harus lewat `asset_url()`, bukan path polos, karena tidak ada cache-control header dari server.

## Database Changes

None.

## API Changes

None.

## Architecture Changes

Konvensi baru: setiap referensi ke file di `public/assets/` dari halaman PHP wajib dibungkus `asset_url()` agar cache selalu ter-bust otomatis mengikuti `filemtime()`.

## Documentation Updated

- `CLAUDE.md` (bagian arsitektur — cache-busting aset).
- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.
- `curl http://localhost:8888/Forsa/login` → `href="assets/css/forsa.css?v=1790144449"` (query version muncul benar).

## Manual Test

1. Drill-down manual sampai leaf jabatan terdalam (`PLN NP → UI → KP → "EXPERT — PoG 18 / 19 / 20"`) → `getComputedStyle` pada batas grup TOTAL→MA di baris tersebut: `border-right: 3px` (kolom Sisa) dan `border-left: 3px` (kolom FTK berikutnya) — identik dengan baris SH/AP root, membuktikan garis pemisah memang sudah konsisten dari header sampai leaf terdalam.
2. Reload halaman dashboard di tab browser baru (tanpa workaround manual apa pun) → `document.querySelector('link[rel=stylesheet]').href` menunjukkan `...forsa.css?v=1790144449`, dan `getComputedStyle(document.documentElement).getPropertyValue('--group-sep')` langsung mengembalikan nilai yang benar — mengonfirmasi cache-busting bekerja tanpa perlu hard refresh/private window.
3. Halaman login tetap tampil normal (200 OK, logo PLN tetap muncul) — memastikan perubahan tidak merusak halaman lain yang juga memakai `asset_url()`.

## Known Limitations

- `asset_url()` hanya diterapkan pada `forsa.css`, `dashboard.js`, dan `users.js` (satu-satunya aset CSS/JS yang direferensikan dari halaman PHP saat ini). Gambar (`assets/img/...`) belum memakai cache-busting karena jarang berubah dan bukan sumber masalah yang dilaporkan — bisa ditambahkan nanti dengan pola yang sama bila diperlukan.
