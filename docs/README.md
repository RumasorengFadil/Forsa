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
- [Panel adaptif dan config jalan maskot](reports/2026/09/24/ai_mascot_sidebar_walk_config.md) — panel kiri/kanan, reservasi konten dan durasi `.env`.
- [Kontrol keyboard maskot](reports/2026/09/24/ai_mascot_keyboard_controls.md) — toggle, W/S/A/D, proteksi editor dan manual test.
- [Interval greeting maskot dari config](reports/2026/09/24/ai_mascot_interval_config.md) — pemulihan `MASCOT_GREETING_INTERVAL_MS` dan manual test.
- [Audit dan implementasi PRD AI 25–29](reports/2026/09/24/ai_prd25_29_audit.md) — [Tahap 1](reports/2026/09/24/ai_prd25_29_tahap1_visual_idle_greeting.md), [Tahap 2](reports/2026/09/24/ai_prd25_29_tahap2_scroll.md), [Tahap 3](reports/2026/09/24/ai_prd25_29_tahap3_sidebar.md), termasuk manual test.

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
