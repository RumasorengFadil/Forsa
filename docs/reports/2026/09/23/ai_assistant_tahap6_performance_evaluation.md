# FORSA AI Assistant — Tahap 6: Performance & Evaluation (PRD Phase 8-9)

Melanjutkan Tahap 5. Mengerjakan **Phase 8 (Performance)** dan **Phase 9 (Evaluation)** — dua phase terakhir di PRD §56 sebelum acceptance criteria (§55) ditinjau ulang secara keseluruhan.

## Summary

- **Cache** (PRD §20) diimplementasikan sebagai file-based cache di `storage/ai/cache/`, key `ai:{user_scope_hash}:{semantic_query_hash}` persis format yang diminta PRD. Diverifikasi: query berulang jadi ~240x lebih cepat (19ms → 0,08ms), dan dua scope RBAC berbeda menghasilkan cache key yang berbeda (tidak bisa bocor).
- **Evaluation suite** (PRD §52-54) dibuat sebagai `tools/ai_evaluation_cli.php`, mengikuti pola CLI tools existing (`tools/import_ftk_cli.php`) — bukan PHPUnit, karena proyek ini memang belum memakai PHPUnit di manapun (`CLAUDE.md` sendiri menyatakan `tests/` masih placeholder kosong dan verifikasi manual jadi satu-satunya jaring pengaman saat ini). 10 skenario deterministik PRD §53 — **semua PASS**, bisa dijalankan ulang kapan saja tanpa biaya API.
- Ditemukan dan ditutup celah nyata di system prompt: PRD §35 (Ambiguity Handling) meminta model **bertanya balik** saat periode ambigu, bukan menebak — sebelumnya sistem selalu diam-diam memakai periode terbaru. Ditambahkan instruksi eksplisit, **diverifikasi langsung**: "Tampilkan data bulan lalu." (tanpa konteks company) sekarang membuat model bertanya balik "perusahaan/subholding mana yang dimaksud?" — dan dikonfirmasi lewat log `forsa_ai_tool_executions` bahwa `query_forsa` **tidak** ikut terpanggil (tidak ada tebakan diam-diam).

## Files Changed

**Baru:**
- [modules/ai/cache/AiQueryCache.php](../../../../modules/ai/cache/AiQueryCache.php) — cache file-based, TTL, key scope-aware.
- [tools/ai_evaluation_cli.php](../../../../tools/ai_evaluation_cli.php) — evaluation suite CLI, 10 skenario + ringkasan pass/fail.
- `storage/ai/cache/.gitkeep` — direktori cache (isi cache sendiri di-gitignore).

**Diubah:**
- [modules/ai/tools/QueryForsaTool.php](../../../../modules/ai/tools/QueryForsaTool.php) — cek cache sebelum menjalankan query, simpan hasil sukses ke cache setelahnya.
- [modules/ai/services/AiOrchestrator.php](../../../../modules/ai/services/AiOrchestrator.php) — tambah instruksi PRD §35 (Ambiguity Handling) ke system prompt.
- `.gitignore` — abaikan isi `storage/ai/cache/*`, simpan `.gitkeep`.
- `composer.json` — PSR-4 `Forsa\Ai\Cache\`.
- `docs/ai-assistant/README.md` — status Phase 8-9 → selesai.

## Keputusan Desain

- **Cache file-based, bukan Redis** — stack proyek ini tidak punya instance Redis di manapun (dikonfirmasi: hanya PostgreSQL), dan setiap query terukur di bawah 20ms bahkan tanpa cache (Tahap 4). Menambah dependency infrastruktur baru untuk penghematan sub-milidetik adalah scope creep, bukan perbaikan performa yang nyata dibutuhkan. `storage/ai/` sendiri sudah ada di struktur folder resmi PRD §41.
- **Evaluation suite CLI, bukan PHPUnit** — mengikuti konvensi nyata proyek ini (tidak ada test framework di manapun sampai sekarang), bukan memperkenalkan dependency baru tanpa dikonfirmasi dulu ke user.
- **2 kategori PRD §53 sengaja tidak diotomasi** (follow-up context, ambiguous query) — keduanya bergantung pada penalaran bahasa natural LLM atas histori percakapan, bukan logic deterministik di `QueryForsaTool`. Diuji manual lewat browser (lihat Manual Test), didokumentasikan jelas di output CLI-nya sendiri supaya tidak disangka "belum diuji sama sekali".

## Database Changes
None.

## API Changes
None.

## Architecture Changes
None baru — cache adalah lapisan tambahan murni di dalam `QueryForsaTool::execute()`, tidak mengubah kontrak/response tool.

## Documentation Updated
- `docs/ai-assistant/README.md`.
- Laporan ini.

## Tests Performed
- `php -l` seluruh file baru/diubah — tanpa error.
- `composer dump-autoload` — berhasil.
- `php tools/ai_evaluation_cli.php` — **10/10 PASS** (lihat detail di Manual Test).

## Manual Test

1. **Cache speed**: panggilan `query_forsa` identik dua kali berturut-turut → panggilan pertama 19,23ms (menyentuh database), panggilan kedua 0,08ms (dari cache) — file cache dikonfirmasi tepat 1 buah dibuat untuk 1 kombinasi scope+query.
2. **Cache scope isolation**: `AiQueryCache::key()` dengan `allowed_company_ids: null` vs `[1,2]` menghasilkan key berbeda; scope+query yang identik menghasilkan key yang sama persis — dicek langsung nilai hash-nya.
3. **Cache TTL**: instance cache dengan TTL 1 detik, `set()` lalu `get()` langsung → data ada; `sleep(2)` lalu `get()` lagi → `null` (kedaluwarsa dan file terhapus otomatis).
4. **Evaluation suite**: `php tools/ai_evaluation_cli.php` → 10/10 PASS, mencakup simple metric, filter, multi-filter, comparison (previous_period), ranking, unsupported metric, empty result, unauthorized query (RBAC), large result (limit di-cap ke 100), dan regresi keamanan (SQL injection tidak mengeksekusi apa pun, `forsa_users` tetap utuh).
5. **Ambiguity handling (lewat browser, `gpt-4o-mini` sungguhan)**: "Tampilkan data bulan lalu." (tanpa nama company) → model menjawab **"saya perlu tahu perusahaan atau subholding mana yang Anda maksud"**, bukan menebak periode — dan dikonfirmasi lewat `forsa_ai_tool_executions` bahwa TIDAK ada baris baru (tool memang tidak ikut dipanggil untuk pertanyaan ambigu ini).

## Known Limitations

- Follow-up context ("kalau bulan sebelumnya?") sepenuhnya bergantung pada kemampuan model membaca histori percakapan sendiri — tidak ada `AiContextService` terpisah yang secara eksplisit melacak "company yang sedang dibahas" (didokumentasikan sejak Tahap 4 sebagai keputusan scope, karena butuh semantic layer yang lebih matang untuk bermanfaat nyata).
- Evaluation suite tidak mencakup uji beban (load test) — PRD §37/§38 menyebutkan target performa untuk banyak pengguna bersamaan, belum diuji dengan concurrency nyata.
- Cache tidak invalidate otomatis saat ada upload/snapshot baru — mengandalkan TTL 5 menit. Untuk kasus normal (upload tidak terjadi setiap menit) ini cukup aman; kalau ada kebutuhan "harus langsung ter-refresh setelah upload", perlu ditambah invalidation eksplisit di alur import (di luar cakupan permintaan saat ini).

## Status Keseluruhan PRD

Seluruh 9 phase di PRD §56 sudah dikerjakan (Phase 1-9). Rekomendasi selanjutnya: tinjau ulang **Acceptance Criteria** (PRD §55) sebagai checklist akhir sebelum dianggap MVP selesai — sebagian besar item sudah terpenuhi dan terverifikasi di laporan Tahap 1-6, tapi belum pernah ditinjau sebagai satu checklist utuh.
