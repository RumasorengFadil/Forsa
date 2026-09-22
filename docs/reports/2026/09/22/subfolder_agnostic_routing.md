# Routing subfolder-agnostic (mengikuti pola pln_orbit)

## Summary

Sebelumnya, FORSA hanya bisa diakses lewat Apache/MAMP jika folder proyek
dijadikan document root secara langsung (`http://localhost:8080/dashboard`).
Mengaksesnya sebagai subfolder di `htdocs` (`http://localhost:8888/Forsa/dashboard`,
sesuai layout MAMP default user) menghasilkan 404, karena `router.php`
mencocokkan `route_map.php` terhadap `REQUEST_URI` absolut (`/Forsa/dashboard`),
sementara key di `route_map.php` tidak berprefix (`dashboard`).

Atas permintaan user untuk mengikuti rancangan yang sudah dipakai di proyek
sibling `pln_orbit` (di `htdocs/pln_orbit`), routing diubah agar
subfolder-agnostic: `.htaccess` melempar route lewat query string
(`router.php?_forsa_route=$1`), memanfaatkan bahwa pattern `RewriteRule`
dicocokkan relatif terhadap direktori `.htaccess` sendiri, bukan path
absolut. Seluruh URL internal (aset, form action, fetch AJAX, redirect)
diubah dari absolut (`/assets/...`) menjadi relatif (`assets/...`), sesuai
pola yang sama dipakai `pln_orbit` untuk halaman-halaman flat (satu level).

## Files Changed

- `.htaccess` — rewrite akhir memakai `router.php?_forsa_route=$1`; rule
  aset `assets/(.*)$` kehilangan `RewriteCond %{REQUEST_URI} ^/assets/` yang
  keliru mengecek path absolut (ikut membawa prefix subfolder) alih-alih
  path relatif yang sudah benar dicocokkan oleh `RewriteRule` itu sendiri.
- `router.php` — baca `_forsa_route` dari query string bila ada (jalur
  Apache); fallback ke parsing `REQUEST_URI` hanya untuk `php -S` (yang
  tidak membaca `.htaccess` dan selalu menyajikan proyek sebagai document
  root). `$legacyPageRedirects` diubah ke target relatif.
- `shared/auth_guard.php`, `modules/auth/login.php`,
  `modules/auth/login_submit.php`, `modules/auth/logout.php` —
  `redirect('/login')`/`redirect('/dashboard')` → `redirect('login')`/`redirect('dashboard')`.
- `shared/menu.php` — href navbar (`dashboard`, `users`, `logout.php`) dan
  URL logo (`assets/img/...`) jadi relatif.
- `modules/dashboard/dashboard.php`, `modules/administrasi/users.php`,
  `modules/auth/login.php` — `<link>`/`<script>` CSS-JS dan form `action`
  jadi relatif.
- `public/assets/js/dashboard.js`, `public/assets/js/users.js` — semua
  `fetch('/....php')` jadi `fetch('....php')` (tanpa leading slash).
- `docs/architecture/overview.md` — bagian baru "Routing subfolder-agnostic".
- `docs/deployment/installation.md` — catatan bahwa Apache/MAMP bisa
  menyajikan app dari subfolder tanpa config tambahan; `php -S` tetap harus
  dijalankan dari root proyek.

Susulan (bug ditemukan setelah laporan awal): akses ke root folder app
sendiri dengan trailing slash (`/Forsa/`, atau `/` di document root)
sempat menghasilkan `403 Forbidden` alih-alih masuk ke `router.php`, karena
rule catch-all sengaja punya kondisi `!-d` (supaya folder fisik seperti
`modules/`/`assets/` tidak ikut tertelan rewrite) — path root memang sebuah
direktori fisik, jadi kondisi itu gagal dan Apache jatuh ke directory
listing bawaan yang diblokir `Options -Indexes`. Ditambahkan satu rule baru
di `.htaccess` (`RewriteRule ^$ router.php?_forsa_route=`, mengikuti pola
yang sama di `.htaccess` `pln_orbit`) untuk menangani kasus ini secara
eksplisit sebelum rule catch-all.

## Database Changes

None.

## API Changes

None (endpoint path & payload tidak berubah, hanya cara link/fetch menuju
ke sana yang jadi relatif).

## Architecture Changes

Mekanisme dispatch route berubah dari "parse `REQUEST_URI` absolut" menjadi
"baca route relatif dari `.htaccess` (Apache) atau dari `REQUEST_URI`
(php -S, karena selalu di document root)". Lihat detail di
`docs/architecture/overview.md` §"Routing subfolder-agnostic".

## Documentation Updated

- `docs/architecture/overview.md`
- `docs/deployment/installation.md`

## Tests Performed

- `php -l` pada seluruh file PHP yang diubah — semua lolos.
- `node --check` pada `dashboard.js` dan `users.js` — lolos.

## Manual Test

Dilakukan di MAMP (Apache), akses via `http://localhost:8888/Forsa/...`
(browser bawaan Claude Code):

1. `GET /Forsa/login` → 200, CSS (`/Forsa/assets/css/forsa.css`) 200 —
   sebelum fix untuk asset rule, ini sempat 404, sudah diperbaiki.
2. Login dengan user QA sementara (`qa-test@forsa.local`, dibuat via
   `php tools/seed_admin.php` untuk verifikasi, lalu di-nonaktifkan lagi
   setelah test — **tidak dihapus** karena FK `forsa_audit_logs` sudah
   mereferensikan baris LOGIN-nya) → redirect ke `/Forsa/dashboard`, 200.
3. Di `/Forsa/dashboard`: CSS, JS, logo, `history_api.php`,
   `dashboard_api.php`, `ftk_tree_api.php` semua ter-load dengan prefix
   `/Forsa/...` yang benar (200 semua).
4. Klik nav "Manajemen User" → `href="users"` resolve ke `/Forsa/users`,
   200, `assets/js/users.js` ter-load dengan prefix yang benar.
5. `GET /Forsa/` (trailing slash) → sempat `403 Forbidden`; setelah
   tambahan rule `^$` di `.htaccess`, jadi redirect ke `/Forsa/dashboard`
   (karena sudah login), 200.

Expected result: seluruh path di atas tetap 200 dan tidak membawa prefix
ganda/salah — tercapai.

Susulan: `php -S 127.0.0.1:8091 router.php` (dev server built-in) juga
ditest lewat `curl` (bukan browser, tanpa GUI) untuk memastikan jalur
`_forsa_route`-nya Apache tidak meregresi jalur `REQUEST_URI` langsung yang
dipakai `php -S`:

1. `GET /` → 200 (langsung serve halaman login, karena scriptName kosong
   dipetakan ke `login`).
2. `GET /login` → 200; `GET /assets/css/forsa.css` → 200
   (`content-type: text/css`).
3. `GET /dashboard.php` (URL lama) → `301` ke `/dashboard`.
4. `GET /dashboard` tanpa sesi → `302` ke `/login`; `GET /login_submit.php`
   lewat GET (bukan POST) → `302` ke `/login`.
5. `GET /rute-tidak-ada` → `404`.
6. Alur login penuh pakai `curl` + cookie jar dan user QA sementara: `POST
   /login_submit.php` (email+password+`_csrf` dari halaman login) → `302`
   ke `/dashboard`; `GET /dashboard` dengan cookie sesi → `200`, HTML-nya
   memuat `href="dashboard"`, `href="users"`, `href="logout.php"`,
   `src="assets/js/dashboard.js"` (semua relatif, sesuai desain); `GET
   /assets/js/dashboard.js` → `200`; `GET /dashboard_api.php?...` → `200`;
   `GET /users` → `200`; `GET /logout.php` → `302` ke `/login`.

Expected result: seluruh status code & redirect target di atas sesuai
perilaku sebelum perubahan (tidak ada regresi pada jalur `php -S`) —
tercapai. Server test dihentikan setelah selesai; user QA sementara
dikembalikan ke `is_active = false`.

## Known Limitations

- Solusi ini mengasumsikan seluruh halaman FORSA berada di satu level clean
  URL (tidak ada nesting seperti `/dashboard/detail`). Jika nanti ada rute
  bersarang, path relatif tanpa leading slash tidak akan otomatis benar
  lagi dan perlu helper base-URL seperti `orbitMenuUrl()` di `pln_orbit`.
- Deployment di subfolder hanya didukung untuk Apache/MAMP (lewat
  `.htaccess`). `php -S` tetap harus dijalankan dari root proyek seperti
  sebelumnya.
