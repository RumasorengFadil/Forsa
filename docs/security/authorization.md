# Otorisasi

Fase MVP hanya memiliki satu role: `SUPER_ADMIN` (seed di `database/seeds/seed_roles.sql`), dengan akses penuh ke seluruh modul.

Struktur dibuat extensible melalui:

- `forsa_roles` + `forsa_user_roles` — mendukung banyak role per user tanpa migrasi ulang.
- `config/role_access.php` — peta role → permission (`'SUPER_ADMIN' => ['*']`), siap diperluas saat role baru ditambahkan tanpa mengubah skema tabel.

Semua endpoint yang mengubah data (`*_submit.php`, `user_*.php`, `upload_submit.php`) memvalidasi CSRF token (`shared/csrf.php::csrf_require()`) di samping `require_login()`, dan mencatat aksi ke `forsa_audit_logs` (`shared/audit.php`).
