# Dashboard Tahap 6 — Tinggi Tabel

## Summary

Mengubah container tabel drill-down (`.tree-scroll`) dari `max-height` (mengikuti jumlah konten) menjadi `height` tetap yang responsif terhadap viewport, agar area tabel tidak lagi mengecil ketika data organisasi hanya sedikit (mis. baru satu SH/AP yang belum di-expand), sambil tetap mempertahankan scroll internal saat konten melebihi tinggi tersebut.

## Root Cause

`.tree-scroll { max-height: 640px; }` hanya memberi **batas atas**, bukan tinggi tetap — CSS `max-height` tidak memaksa elemen mengisi ruang itu; tinggi box tetap mengikuti tinggi konten aktual (`height: auto` implisit) selama masih di bawah 640px. Akibatnya, saat baru 1 baris (`PLN IP`, belum di-expand), box menyusut hanya setinggi 1 baris + header, membuat kartu drill-down dan tata letak di sekitarnya terlihat "mengecil" secara tidak konsisten dibanding saat datanya banyak.

## Fix

`max-height: 640px` diganti `height: clamp(420px, 70vh, 640px)`:
- **`height` (bukan `max-height`)** memaksa box selalu memiliki tinggi tersebut, terlepas dari jumlah baris — konten yang lebih pendek dari itu menyisakan ruang kosong di dalam box (bukan mengecilkan box), konten yang lebih panjang tetap discroll secara internal via `overflow: auto` (tidak berubah).
- **`clamp(420px, 70vh, 640px)`** membuatnya responsif terhadap tinggi viewport: `70vh` sebagai basis (mis. 537,6px pada viewport 768px), dengan batas bawah 420px (agar tidak terlalu pendek di layar laptop pendek) dan batas atas 640px (menyamai nilai `max-height` sebelumnya, sehingga tampilan di layar besar tidak berubah drastis).

## Files Changed

- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — `.tree-scroll`: `max-height: 640px` → `height: clamp(420px, 70vh, 640px)`.

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None — perubahan CSS satu baris (plus komentar penjelasan).

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Di Browser pane (viewport 800×768, snapshot PLN IP Agustus 2026):

1. **Data minim (1 baris, belum di-expand)**: tinggi box diukur via JS (`getBoundingClientRect().height`) = `537.59px`, identik dengan hasil kalkulasi `70vh` pada viewport 768px. Screenshot mengonfirmasi box menampilkan satu baris "PLN IP" di bagian atas dengan area putih kosong memenuhi sisa tinggi box — bukan lagi menyusut mengikuti 1 baris tersebut.
2. **Data banyak (42 baris, SH/AP → UI/UL/UP → seluruh Unit Pelaksana)**: tinggi box diukur ulang = `537.59px` — **identik persis** dengan kondisi 1 baris, membuktikan tinggi benar-benar tetap, tidak terikat jumlah konten. `scrollHeight` (1268px) > tinggi box → `canScroll: true`, mengonfirmasi scroll internal tetap berfungsi begitu konten melebihi tinggi tetap tersebut.
3. Regresi: header sticky dua-layer (Tahap 5) tetap berfungsi normal di dalam box dengan tinggi baru ini (diverifikasi lewat screenshot saat 42 baris — header TOTAL/MA/... dan FTK/Organik/... tetap terlihat penuh saat daftar Unit Pelaksana di-scroll).

## Known Limitations

- Nilai `clamp(420px, 70vh, 640px)` dipilih berdasarkan penilaian visual (bukan permintaan angka piksel spesifik dari user); bila ada preferensi tinggi minimum/maksimum yang berbeda, angka ini mudah disesuaikan di satu tempat (`public/assets/css/forsa.css`, selector `.tree-scroll`).
- Tidak ada penyesuaian tambahan berdasarkan tinggi elemen-elemen dashboard DI ATAS tabel (KPI, chart, dll.) — `70vh` dihitung dari tinggi viewport penuh, bukan "sisa ruang setelah dikurangi konten di atasnya", karena tabel drill-down berada di halaman yang men-scroll penuh (bukan dashboard shell dengan region-region scroll independen), sehingga "mengisi sisa viewport" secara literal tidak berlaku sama seperti aplikasi single-screen. Pendekatan `70vh` dipilih sebagai perkiraan praktis yang tetap terasa "mengisi layar" tanpa kalkulasi offset yang rapuh terhadap perubahan konten dashboard di atasnya.
