# Dashboard Tahap 1 — Navbar Logo PLN & User Guide

## Summary

Menambahkan logo PLN pada navbar (dengan fallback aman karena asset resmi belum tersedia di repo) dan tombol bantuan `!` di sebelah judul dashboard yang membuka Guided Tour 8 langkah khusus halaman dashboard. Pola UI/UX (spotlight ring + popover, bukan library eksternal) diadaptasi dari `partials/admin_header.php` + `assets/admin.js` pada project `ORBIT GeoMutasi`, disesuaikan dengan struktur single-file JS/CSS FORSA.

## Files Changed

- [shared/menu.php](../../../../shared/menu.php) — `forsa_brand_logo_url()` (deteksi asset logo di `public/assets/img/pln-logo.{svg,png,webp}`, fallback ke badge "F" bila belum ada), render `<img>` bila ditemukan.
- [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — tombol `#btn-dashboard-guide` (`!`) di sebelah judul `FTK WORKFORCE MONITORING DASHBOARD`.
- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — `.badge-logo`, `.help-btn`, dan blok `.tour-*` (overlay, dim, spotlight ring, popover) memakai warna `--blue` proyek ini (bukan `--brand` milik GeoMutasi).
- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js) — modul `Tour` (IIFE) + `DASHBOARD_TOUR_STEPS` (8 langkah) + wiring klik tombol `!`.
- `public/assets/img/` — folder baru, kosong (tempat asset logo PLN diletakkan nanti).

## Database Changes

None.

## API Changes

None — seluruhnya perubahan tampilan/JS sisi klien, tidak ada endpoint baru.

## Architecture Changes

Tidak mengubah arsitektur; menambah satu fungsi helper di `shared/menu.php` dan satu modul mandiri di `dashboard.js` (mengikuti pola "setiap halaman punya JS sendiri", sama seperti `users.js`).

## Documentation Updated

- Laporan ini (`docs/reports/2026/09/22/dashboard_tahap1_navbar_user_guide.md`).
- `docs/README.md` tidak perlu diubah (struktur reports sudah terdaftar generik).

## Tests Performed

- `php -l` pada seluruh file PHP — tanpa error.
- `node --check public/assets/js/dashboard.js` — tanpa error.

## Manual Test

Di browser (login sebagai Super Admin, `http://127.0.0.1:8080/dashboard.php`):

1. Navbar menampilkan badge "F" (logo PLN belum ada asset-nya — lihat Known Limitations) di kiri atas.
2. Tombol `!` muncul tepat di kanan judul dashboard.
3. Klik `!` → overlay gelap + spotlight ring + popover muncul, dimulai dari "Langkah 1 dari 8" pada dropdown histori.
4. Klik "Lanjut" berulang → ring & popover berpindah mengikuti target (`#select-history` → `#btn-open-upload` → `.tabs` → `.kpi-row` → `.shap-grid` → `.priority-card` → `.tree-toolbar` → `#tree-table`), termasuk auto-scroll ke target yang berada di luar viewport (diverifikasi untuk langkah 5–8).
5. Tombol berubah menjadi "Selesai" pada langkah terakhir; klik menutup overlay dan dashboard kembali sepenuhnya interaktif.
6. "Lewati" dan tombol close (`×`) juga diuji menutup tur kapan saja.

## Update — Logo PLN Ditambahkan (susulan, hari yang sama)

User memberikan asset logo PLN resmi (PNG 1024×1024, lockup ikon+wordmark). Diproses menjadi:

- `public/assets/img/pln-logo-mark.png` — crop persegi rapat pada ikon petir+ombak saja (297×297), dipakai untuk badge navbar 30×30 yang kompak. **Ini yang sekarang tampil di navbar.**
- `public/assets/img/pln-logo-full.png` — crop lockup penuh (ikon + tulisan "PLN", 762×307), disimpan untuk kebutuhan lain (mis. halaman login) bila diminta nanti.
- `public/assets/img/pln-logo.png` — file asli yang diunggah user (1024×1024, kanvas putih penuh), disimpan sebagai arsip.

`forsa_brand_logo_url()` di `shared/menu.php` diperbarui: kandidat sekarang memprioritaskan `pln-logo-mark.*` (khusus badge kecil) sebelum fallback ke `pln-logo.*` (lockup penuh, andai file mark tidak ada).

Diverifikasi di browser: navbar dashboard menampilkan logo PLN (kotak kuning, petir merah, ombak biru) menggantikan badge "F", tetap legible pada ukuran 30×30 dengan border-radius 8px.

## Known Limitations

- Guided Tour baru dipasang di halaman dashboard sesuai permintaan Tahap 1; belum ada mekanisme "jangan tampilkan lagi" (localStorage) — dianggap di luar scope Tahap 1, bisa ditambahkan bila diminta.
- Teks langkah 5–6 tur (Sebaran per SH/AP, Gap Terbesar) belum menyebutkan interaksi klik/drilldown karena fitur tersebut baru masuk di Tahap 3 — teks akan diperbarui saat tahap itu selesai.
- Halaman login (`modules/auth/login.php`) masih memakai badge huruf "F" statis (markup terpisah dari `render_topbar()`) — belum diminta untuk diganti, di luar scope "navbar" yang diminta kali ini. Asset `pln-logo-full.png` sudah disiapkan bila nanti diminta.
