# Konfigurasi schema database via DB_SCHEMA

## Summary

Sebelumnya nama schema Postgres (`forsa`) hardcoded satu tempat di
`shared/Database.php` (`SET search_path TO forsa, public`) dan tidak bisa
diubah tanpa mengedit kode. Ditambahkan opsi `DB_SCHEMA` di `.env`, dibaca
terpusat di `config/database.php`, dengan fallback ke `forsa` kalau tidak
diisi. Grep terhadap seluruh kode PHP (`FROM forsa\.`, `JOIN forsa\.`, dsb.)
mengonfirmasi tidak ada tempat lain yang menulis prefix schema secara
manual di query — satu-satunya titik yang perlu diubah memang
`shared/Database.php`.

## Files Changed

- `config/database.php` — tambah key `'schema' => env('DB_SCHEMA') ?: 'forsa'`.
  Fallback ditangani di sini (bukan di `Database.php`) supaya satu-satunya
  tempat yang tahu soal default schema adalah file config.
- `shared/Database.php` — `SET search_path TO forsa, public` (hardcoded)
  → `SET search_path TO {$schema}, public` memakai `$config['schema']`.
  Karena nama schema tidak bisa di-bind sebagai parameter PDO dalam
  statement `SET`, ditambahkan validasi identifier ketat
  (`^[a-zA-Z_][a-zA-Z0-9_]*$`) sebelum di-exec — melempar
  `RuntimeException` kalau `DB_SCHEMA` diisi nilai yang bukan identifier
  Postgres valid, supaya nama schema salah gagal jelas di awal alih-alih
  berpotensi SQL injection lewat `SET search_path` atau membuat semua query
  gagal diam-diam.
- `.env.example` — tambah `DB_SCHEMA=forsa` sebagai contoh/dokumentasi.
  `.env` lokal (gitignored) tidak diubah — fallback otomatis membuatnya
  tetap berperilaku sama seperti sebelumnya tanpa key ini.
- `docs/database/schema.md`, `docs/deployment/installation.md` —
  dokumentasi opsi `DB_SCHEMA` dan bahwa migration di
  `database/migrations/` masih membuat schema `forsa` secara hardcoded
  (perlu disesuaikan manual kalau `DB_SCHEMA` diisi nama lain).

## Database Changes

None (tidak ada migration baru; ini murni konfigurasi koneksi runtime).
Catatan: `database/migrations/001_create_auth_tables.sql` tetap memakai
`CREATE SCHEMA IF NOT EXISTS forsa` hardcoded — di luar scope perubahan
ini, karena migration adalah DDL yang menentukan nama schema itu sendiri,
bukan runtime query yang mengikuti `search_path`.

## API Changes

None.

## Architecture Changes

Titik konfigurasi schema database dipusatkan di `config/database.php` →
`Database::connection()`, mengikuti pola yang sama dengan `DB_HOST`/
`DB_DATABASE`/dst. yang sudah ada.

## Documentation Updated

- `docs/database/schema.md`
- `docs/deployment/installation.md`

## Tests Performed

- `php -l` pada `config/database.php` dan `shared/Database.php` — lolos.
- Smoke test langsung ke database lokal (lihat "Manual Test").

## Manual Test

1. Tanpa `DB_SCHEMA` di `.env` (kondisi existing) → `SHOW search_path`
   tetap `forsa, public`, `SELECT current_schema()` tetap `forsa` — tidak
   ada regresi pada koneksi/query yang sudah ada.
2. `DB_SCHEMA=forsa_qa_test` (env var sementara, schema dibuat lalu
   dihapus lagi setelah test) → `search_path` dan `current_schema()`
   otomatis ikut berubah ke `forsa_qa_test` tanpa ubah kode.
3. `DB_SCHEMA="forsa; DROP SCHEMA public"` (percobaan injeksi via nilai
   config) → `Database::connection()` melempar `RuntimeException` dengan
   pesan jelas, koneksi tidak dibuat, tidak ada statement SQL tambahan
   yang tereksekusi.

## Known Limitations

- Migration SQL (`database/migrations/*.sql`) tidak ikut membaca
  `DB_SCHEMA` — nama schema di sana masih harus disamakan manual kalau
  proyek benar-benar pindah ke nama schema lain selain `forsa`.
