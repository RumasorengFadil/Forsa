# FORSA AI Assistant — Tahap 5: Security Hardening (PRD Phase 7)

Melanjutkan Tahap 4. Menutup dua known limitation yang didokumentasikan eksplisit di laporan Tahap 4: **dedicated read-only DB role** (PRD §4.3) dan **rate limiting per user** (PRD §15.1/§38).

## Summary

- Dibuat role PostgreSQL baru `forsa_ai_reader`: `LOGIN`, `NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT`, hanya `SELECT` pada **persis 3 tabel** yang disetujui user di Tahap 3 (`forsa_ftk_snapshot_rows`, `forsa_ftk_snapshots`, `forsa_shap_entities`) — tidak ada akses ke tabel lain sama sekali, termasuk `forsa_users`.
- Seluruh jalur pembacaan data AI (`QueryExecutor`, dan juga lookup company/period di `AliasResolver`/`QueryPlanner` yang sebelumnya masih lewat koneksi aplikasi biasa) sekarang **konsisten** memakai koneksi PDO terpisah (`AiReaderConnection`) yang login sebagai `forsa_ai_reader` — bukan cuma query akhirnya saja.
- Ditambahkan `RateLimiter`: maksimum 10 pesan per user per 60 detik (berbasis hitungan baris `forsa_ai_messages`, bukan in-memory, supaya konsisten lintas request/worker PHP-FPM).
- Diverifikasi langsung (bukan cuma baca kode): role baru benar-benar **tidak bisa** membaca `forsa_users`, **tidak bisa** INSERT, **tidak bisa** CREATE TABLE — dan tool `query_forsa` tetap berfungsi normal lewat role terbatas ini.

## Files Changed

**Baru:**
- [database/migrations/009_create_ai_reader_role.sql](../../../../database/migrations/009_create_ai_reader_role.sql) — pembuatan role + GRANT terbatas. **Sudah dijalankan.** Idempotent (aman dijalankan ulang).
- [modules/ai/query/AiReaderConnection.php](../../../../modules/ai/query/AiReaderConnection.php) — PDO singleton terpisah dari `Forsa\Database`, login sebagai `forsa_ai_reader`.
- [modules/ai/security/RateLimiter.php](../../../../modules/ai/security/RateLimiter.php) — rolling-window rate limit berbasis database.

**Diubah:**
- [modules/ai/query/QueryExecutor.php](../../../../modules/ai/query/QueryExecutor.php) — pakai `AiReaderConnection` alih-alih `Forsa\Database`.
- [modules/ai/semantic/AliasResolver.php](../../../../modules/ai/semantic/AliasResolver.php) — lookup fuzzy-match nama company sekarang juga lewat `AiReaderConnection` (sebelumnya masih lewat koneksi app biasa — celah konsistensi yang ditemukan & ditutup di tahap ini).
- [modules/ai/query/QueryPlanner.php](../../../../modules/ai/query/QueryPlanner.php) — resolusi periode ("latest"/"previous_period") juga dipindah ke `AiReaderConnection`.
- [modules/ai/routes/chat.php](../../../../modules/ai/routes/chat.php) — panggil `RateLimiter` sebelum memproses pesan, `429` jika melebihi batas.
- `config/ai.php` — tambah `db_username`/`db_password` (dari `AI_DB_USERNAME`/`AI_DB_PASSWORD`).
- `.env`/`.env.example` — kredensial role baru (password asli hanya di `.env`, gitignored; `.env.example` kosong).
- `composer.json` — PSR-4 `Forsa\Ai\Security\`.
- `docs/ai-assistant/README.md` — status Phase 7 → selesai.

## Kendala Teknis yang Ditemukan & Diperbaiki Saat Implementasi

Percobaan pertama memakai pola `DROP ROLE IF EXISTS` + `CREATE ROLE` untuk idempotency **gagal** saat dijalankan kedua kali: `ERROR: role "forsa_ai_reader" cannot be dropped because some objects depend on it` (GRANT yang sudah ada dihitung sebagai dependency). Diganti dengan pola `CREATE ROLE jika belum ada, else ALTER ROLE ... PASSWORD` — dan `GRANT` dibiarkan apa adanya karena re-grant privilege yang sudah dimiliki bukan error.

Percobaan kedua juga sempat gagal: substitusi variabel psql (`:'var'`) **tidak** menembus ke dalam blok `DO $$...$$` (diperlakukan sebagai teks literal `:` oleh parser PL/pgSQL, `ERROR: syntax error at or near ":"`). Diperbaiki dengan menitipkan password lewat `set_config()` di level statement biasa (di luar `$$`, tempat substitusi psql memang berlaku), lalu dibaca kembali di dalam blok `DO` via `current_setting()`. Kedua perbaikan ini diuji ulang sampai berhasil jalan dua kali berturut-turut tanpa error sebelum dianggap selesai.

## Database Changes

Migration `009_create_ai_reader_role.sql` — role level cluster (bukan per-database seperti migration biasa), **sengaja tidak dijalankan lewat loop generik** `for f in migrations/*.sql` karena butuh password runtime yang tidak boleh masuk file (dijelaskan di komentar file itu sendiri). Sudah dijalankan manual dengan password yang di-generate acak (`bin2hex(random_bytes(24))`), tersimpan hanya di `.env`.

## API Changes

`POST ai_chat_api.php` sekarang bisa mengembalikan `429` ("Terlalu banyak permintaan dalam waktu singkat...") jika user mengirim >10 pesan dalam 60 detik.

## Architecture Changes

Dua koneksi database independen sekarang hidup berdampingan: `Forsa\Database` (role aplikasi biasa, dipakai seluruh modul FORSA existing + tabel percakapan AI sendiri) dan `Forsa\Ai\Query\AiReaderConnection` (role `forsa_ai_reader`, khusus 3 tabel analytics yang disetujui). Ini murni penambahan lapisan pertahanan; tidak ada perubahan pada koneksi/role yang sudah ada.

## Documentation Updated
- `docs/ai-assistant/README.md`.
- Laporan ini.

## Tests Performed
- `php -l` seluruh file baru/diubah — tanpa error.
- `composer dump-autoload` — berhasil.

## Manual Test

Dijalankan nyata terhadap Postgres lokal:

1. **Role dibuat & idempotent**: migration dijalankan 2x berturut-turut dengan password berbeda tiap kali — berhasil tanpa error kedua kalinya (setelah perbaikan pola idempotency di atas).
2. **Akses dikonfirmasi read-only ketat**, login langsung sebagai `forsa_ai_reader` via `psql`:
   - `SELECT COUNT(*) FROM forsa_ftk_snapshot_rows` → `11589` (berhasil, tabel disetujui).
   - `SELECT * FROM forsa_users` → `ERROR: permission denied for table forsa_users` (diblokir, sesuai rencana).
   - `INSERT INTO forsa_shap_entities ...` → `ERROR: permission denied for table forsa_shap_entities` (diblokir).
   - `CREATE TABLE evil (id int)` → `ERROR: permission denied for schema forsa` (diblokir).
3. **Tool tetap berfungsi lewat koneksi terbatas**: `AiReaderConnection::connection()->query('SELECT current_user')` mengonfirmasi `forsa_ai_reader` yang login, dan `QueryForsaTool::execute()` tetap mengembalikan data asli yang benar (`Indonesia Power` → `ftk 5652, realisasi 4702, gap -950`, sama seperti Tahap 4) — membuktikan pengetatan akses tidak merusak fungsi.
4. **Percobaan langsung** membaca `forsa_users` lewat `AiReaderConnection` (bukan cuma via `psql` manual) → tetap diblokir dengan pesan permission-denied yang sama — konsisten dari jalur kode PHP, bukan cuma dari client `psql`.
5. **Rate limiter**: diuji dengan `maxRequests=3` pada percakapan uji (dibersihkan lagi setelahnya) — pesan ke-3 dan seterusnya dalam window 60 detik terdeteksi `tooManyRequests()=true`.
6. **End-to-end lewat browser** (`gpt-4o-mini` sungguhan, setelah semua perubahan di atas): "Berapa fulfillment rate PLN NP periode terbaru?" → **"sekitar 83,19%"**, cocok dengan KPI "Pemenuhan FTK" (83,2%) yang sudah lama tampil di dashboard — mengonfirmasi seluruh pipeline (LLM → tool → semantic layer → koneksi DB terbatas) tetap benar setelah hardening.

## Known Limitations

- Rate limit (10 pesan/60 detik) adalah nilai default yang wajar untuk development, belum ditentukan berdasarkan beban produksi nyata — mudah diubah lewat parameter constructor `RateLimiter` di `routes/chat.php`.
- Tidak ada mekanisme rotasi password `forsa_ai_reader` otomatis — rotasi manual (jalankan ulang migration 009 dengan password baru + update `.env`).
- Caching (PRD §20, Phase 8) masih belum ada — belum diperlukan pada volume data saat ini.

## Next Step

**Phase 8 — Performance** (evaluasi cache/index lebih lanjut jika volume data bertambah — saat ini belum perlu, lihat `database-sources.md`) dan **Phase 9 — Evaluation** (test set formal PRD §53: kategori simple metric, filter, multi-filter, comparison, ranking, time comparison, follow-up context, ambiguous query, unauthorized query, unsupported metric, empty result, large result — beberapa sudah diuji manual di Tahap 4/5, belum dirangkai jadi suite otomatis).
