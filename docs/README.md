# FORSA — Formasi & Realisasi SH/AP

Dokumentasi ini mengikuti struktur yang disyaratkan PRD (`docs/product/PRD.md`).

- [product/PRD.md](product/PRD.md) — dokumen produk lengkap.
- [architecture/overview.md](architecture/overview.md) — arsitektur aplikasi & alur data.
- [database/schema.md](database/schema.md) — skema database.
- [database/migrations.md](database/migrations.md) — daftar migration.
- [security/authentication.md](security/authentication.md) — autentikasi.
- [security/authorization.md](security/authorization.md) — otorisasi/role.
- [features/dashboard](features/dashboard) — dashboard & agregasi.
- [features/import](features/import) — upload & snapshot.
- [features/ftk-tree](features/ftk-tree) — drill-down tree.
- [features/user-management](features/user-management) — manajemen user.
- [deployment/installation.md](deployment/installation.md) — instalasi.
- [ai-assistant/](ai-assistant/) — FORSA AI Assistant (fitur terpisah, PRD, status implementasi bertahap, analisis sumber data & proposal semantic layer).
- [reports/](reports/) — laporan implementasi per tanggal.

## Menjalankan Aplikasi (Lokal)

```bash
composer install
cp .env.example .env   # sesuaikan kredensial DB
for f in database/migrations/*.sql; do psql forsa -f "$f"; done
for f in database/seeds/*.sql; do psql forsa -f "$f"; done
php tools/seed_admin.php "Super Admin" admin@forsa.local forsa123
php -S 127.0.0.1:8080 router.php
```

Login default: `admin@forsa.local` / `forsa123` (ganti setelah instalasi).
