# Instalasi

## Prasyarat

- PHP ≥ 8.1 dengan ekstensi `pdo_pgsql`
- PostgreSQL ≥ 13 (pengembangan diuji pada PostgreSQL 17)
- Composer

## Langkah

```bash
composer install
cp .env.example .env
# sesuaikan DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

createdb forsa   # jika database belum ada

for f in database/migrations/*.sql; do psql forsa -v ON_ERROR_STOP=1 -f "$f"; done
for f in database/seeds/*.sql; do psql forsa -v ON_ERROR_STOP=1 -f "$f"; done

php tools/seed_admin.php "Super Admin" admin@forsa.local <password-aman>
```

## Menjalankan

Development (PHP built-in server):

```bash
php -S 0.0.0.0:8080 router.php
```

Production (Apache): arahkan document root ke folder proyek, pastikan `mod_rewrite` aktif — `.htaccess` sudah menyertakan rewrite ke `router.php` dan larangan akses `.env`/`.sql`/`.md`.

Apache (termasuk lewat MAMP) juga bisa menyajikan proyek ini dari sebuah subfolder di dalam `htdocs` (mis. `http://localhost:8888/Forsa/dashboard`) tanpa konfigurasi tambahan — `.htaccess`/`router.php` sudah menangani prefix subfolder secara otomatis (lihat "Routing subfolder-agnostic" di `docs/architecture/overview.md`). Ini **tidak** berlaku untuk `php -S`: server built-in PHP selalu menyajikan folder yang dijalankan sebagai root, jadi jalankan dari root proyek itu sendiri (bukan folder induknya) dan akses tanpa prefix apa pun.

Pastikan folder `storage/uploads`, `storage/temp`, `storage/logs` writable oleh proses web server dan **tidak** dapat diakses publik secara langsung (di luar `router.php`).
