# Migrations

Dijalankan berurutan dengan `psql forsa -f database/migrations/<file>.sql`:

1. `001_create_auth_tables.sql` — schema `forsa`, `forsa_users`, `forsa_roles`, `forsa_user_roles`.
2. `002_create_shap_master.sql` — `forsa_shap_entities`.
3. `003_create_import_tables.sql` — `forsa_import_jobs`, `forsa_import_errors`.
4. `004_create_ftk_snapshot_tables.sql` — `forsa_ftk_snapshots` (+ partial unique index satu snapshot aktif per shap+periode), `forsa_ftk_snapshot_rows` (+ index agregasi).
5. `005_create_audit_logs.sql` — `forsa_audit_logs`.

Seeds: `database/seeds/seed_roles.sql`, `database/seeds/seed_shap_entities.sql`.

Setelah migration, buat user Super Admin pertama dengan:

```bash
php tools/seed_admin.php "Nama" "email@domain" "password"
```
