# Dashboard Tahap 2 — Clean URL

## Summary

Menghilangkan sufiks `.php` dari URL halaman yang dinavigasi user (`/login`, `/dashboard`, `/users`), sambil menjaga URL lama tetap berfungsi lewat 301 redirect permanen ke URL bersih. Endpoint aksi/API (`login_submit.php`, `logout.php`, `*_api.php`, `user_*.php`, dst.) sengaja **tidak** diganti — itu bukan "URL halaman" yang di-bookmark/dinavigasi user, dan membiarkannya apa adanya meminimalkan risiko salah ubah pada ~13 endpoint yang dipakai banyak tempat.

## Files Changed

- [route_map.php](../../../../route_map.php) — key halaman (`login`, `dashboard`, `users`) diganti dari `login.php`/`dashboard.php`/`users.php` menjadi tanpa `.php`; seluruh key endpoint aksi/API tidak diubah.
- [router.php](../../../../router.php) — menambahkan `$legacyPageRedirects` (301 dari 4 URL lama: `index.php`, `login.php`, `dashboard.php`, `users.php` ke padanan bersihnya, query string dipertahankan); root `/` kini resolve ke `login` (sebelumnya `index.php`).
- [.htaccess](../../../../.htaccess) — menambah `RewriteRule ^(modules|shared|config|database|tools)/ - [F,L]` agar Apache tidak pernah menyajikan file `.php` di dalam direktori internal secara langsung (lihat Architecture Changes).
- [shared/menu.php](../../../../shared/menu.php) — link navbar `/dashboard.php`→`/dashboard`, `/users.php`→`/users`.
- [shared/auth_guard.php](../../../../shared/auth_guard.php) — `redirect('/login.php')`→`redirect('/login')`; sekaligus memperbaiki deteksi "permintaan API vs halaman" yang sebelumnya memakai `$_SERVER['SCRIPT_NAME']` (lihat Known Limitations/bug lama).
- [modules/auth/login.php](../../../../modules/auth/login.php), [login_submit.php](../../../../modules/auth/login_submit.php), [logout.php](../../../../modules/auth/logout.php) — seluruh `redirect('/login.php')`/`redirect('/dashboard.php')` diganti ke URL bersih.

## Database Changes

None.

## API Changes

None — endpoint `*_api.php`/`*_submit.php`/`user_*.php` tidak berubah nama/path/kontrak sama sekali.

## Architecture Changes

- **Bug lama ditemukan & diperbaiki sekalian**: `require_login()` di `shared/auth_guard.php` mendeteksi "ini permintaan API" via `str_ends_with($_SERVER['SCRIPT_NAME'], '_api.php')`. Di balik front controller `router.php`, `SCRIPT_NAME` **selalu** `/router.php` apa pun URL yang diminta — jadi cek ini nyaris tidak pernah true, dan sesi kedaluwarsa pada endpoint `*_api.php` berisiko mengembalikan redirect HTML alih-alih JSON 401 (yang di sisi JS akan gagal di-`.json()`-kan). Diganti memakai `$_SERVER['REQUEST_URI']` (path permintaan asli, tidak terpengaruh routing) — sudah diverifikasi manual (lihat Manual Test).
- **Parity dev vs production**: PHP built-in server (`php -S ... router.php`) memproses *setiap* request lewat `router.php` (karena router selalu `return true`), sehingga akses langsung ke `modules/dashboard/dashboard.php` otomatis 404 (bukan key yang valid di `route_map.php`). Di Apache, tanpa perubahan `.htaccess`, `RewriteCond %{REQUEST_FILENAME} !-f` akan **gagal** untuk path itu (karena file memang ada di disk) sehingga Apache menyajikannya langsung sebagai file PHP biasa — bypass total terhadap `router.php`. Baris `RewriteRule ^(modules|shared|config|database|tools)/ - [F,L]` yang ditambahkan membuat kedua environment berperilaku sama (403 pada akses langsung ke internal, hanya bisa lewat clean URL/route yang terdaftar).

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` pada seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Dijalankan via `curl` (session cookie jar) dan browser:

1. `GET /` → 200, menampilkan halaman login.
2. `GET /login` → 200 (clean URL langsung berfungsi).
3. `GET /login.php` (URL lama) → `301 Moved Permanently`, `Location: /login`.
4. `GET /dashboard.php` → 301 → `/dashboard`; `GET /users.php` → 301 → `/users`; `GET /index.php` → 301 → `/`.
5. Login via `POST /login_submit.php` (endpoint tidak berubah) → redirect ke `/dashboard` (bukan lagi `/dashboard.php`).
6. `GET /dashboard` dan `GET /users` dengan sesi valid → 200, konten halaman tampil benar.
7. `GET /dashboard_api.php` dengan sesi valid → tetap 200 JSON normal (endpoint API tidak berubah).
8. `GET /dashboard_api.php` **tanpa** sesi (cookie kosong) → `401` dengan body JSON `{"success":false,"message":"Sesi berakhir..."}` — mengonfirmasi perbaikan deteksi API di `auth_guard.php` bekerja.
9. `GET /dashboard` tanpa sesi → `302` ke `/login` (bukan JSON) — mengonfirmasi jalur halaman biasa tidak terpengaruh oleh perbaikan itu.
10. `GET /logout.php` dengan sesi valid → sesi dihapus, redirect ke `/login`.
11. Verifikasi visual di Browser pane: navigasi ke `http://127.0.0.1:8080/dashboard.php` otomatis berubah menjadi `.../dashboard` di address bar; link navbar "Dashboard"/"Manajemen User" sudah memakai `href="/dashboard"`/`href="/users"`; klik "Manajemen User" berpindah halaman dan tampil normal; tombol bantuan `!` (Tahap 1) tetap berfungsi setelah perubahan URL.

## Known Limitations

- Endpoint aksi/API (form POST & `fetch()`) sengaja tetap memakai sufiks `.php` — bila di masa depan ingin konsisten penuh (mis. untuk alasan estetika di Network tab devtools), itu perubahan terpisah yang menyentuh belasan referensi JS/PHP dan sebaiknya dilakukan sebagai task tersendiri, bukan disisipkan di sini.
- Redirect 301 bersifat permanen dan **di-cache browser** — selama pengujian manual gunakan hard refresh / curl (bukan browser yang sama berulang kali) agar tidak salah baca hasil dari cache redirect sebelumnya.
