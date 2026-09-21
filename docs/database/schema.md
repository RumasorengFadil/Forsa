# Skema Database — `forsa`

Semua tabel berada pada schema Postgres `forsa` (lihat `config/database.php` + `Database::connection()` yang men-set `search_path`).

| Tabel | Fungsi |
|---|---|
| `forsa_users` | akun login |
| `forsa_roles` | daftar role (seed: `SUPER_ADMIN`) |
| `forsa_user_roles` | pivot user↔role |
| `forsa_shap_entities` | master SH/AP (PLN IP, NP, EPI, ICON+, ES, ND, BATAM, MCTN, EMI, ENJ) |
| `forsa_import_jobs` | histori proses upload (1 baris per upload) |
| `forsa_import_errors` | error/warning per baris Excel pada suatu import job |
| `forsa_ftk_snapshots` | snapshot immutable (1 SH/AP + 1 periode + 1 revisi); hanya satu `is_active=true` per (shap, periode) — dijaga partial unique index |
| `forsa_ftk_snapshot_rows` | baris detail FTK/realisasi per snapshot (sumber seluruh agregasi dashboard & tree) |
| `forsa_audit_logs` | jejak audit (login, upload, aktivasi snapshot, manajemen user) |

Lihat definisi lengkap kolom pada `database/migrations/*.sql`.

## Keputusan Desain Penting

- **Tidak ada tabel master organisasi** untuk UI/UP/UL, Unit Pelaksana, Unit Layanan — nilai disimpan apa adanya pada `forsa_ftk_snapshot_rows` agar histori tetap merepresentasikan bentuk organisasi saat upload (PRD §22).
- **`snapshot_id` adalah boundary query utama** — semua agregasi dashboard/tree wajib difilter berdasarkan snapshot yang dipilih agar data antar periode/revisi tidak tercampur.
- Nilai organisasi kosong (`NULL`, `''`, `'-'`, whitespace) dinormalisasi menjadi `NULL` saat parsing, agar level tree yang kosong otomatis dilewati.
