# FORSA AI Assistant — Tahap 2: AI Core (PRD Phase 2)

Lanjutan dari Tahap 1 (UI Foundation). Mencakup PRD §56 Phase 2: **provider abstraction, LLM gateway, structured output, tool registry, conversation storage**. Tidak mencakup semantic layer, `query_forsa` tool sungguhan, atau akses database FTK apa pun — itu masih terkunci di belakang gate Phase 3 (PRD §5/§57).

## Summary

Sidebar chat yang di Tahap 1 hanya UI shell dengan balasan placeholder, sekarang benar-benar memanggil model `gpt-4o-mini` (API key user) lewat provider abstraction, dan percakapan tersimpan permanen di database. **Model tetap tidak memiliki akses ke data FTK/realisasi apa pun** — `ToolRegistry` sengaja kosong (lihat docblock-nya), dan system prompt secara eksplisit melarang model mengarang angka. Diverifikasi langsung: saat ditanya "Berapa total FTK Indonesia Power bulan Agustus 2026?", model menjawab jujur belum punya akses, bukan mengarang angka — sesuai prinsip inti PRD §4.1.

## Files Changed

**Baru — `modules/ai/`:**
- `providers/AiProviderInterface.php` — kontrak `chat(array $messages, array $tools): AiResponse` (PRD §21, isi persis interface di PRD).
- `providers/AiResponse.php` — value object hasil panggilan LLM.
- `providers/AiProviderException.php` — exception khusus provider (tidak pernah membocorkan detail ke user, lihat route `chat.php`).
- `providers/OpenAiProvider.php` — implementasi nyata memanggil OpenAI Chat Completions API via cURL. **Satu-satunya tempat `MODEL_API_KEY` pernah dipakai.**
- `tools/ToolRegistry.php` — registry tool, **sengaja kosong** sampai Phase 6 (dijelaskan di docblock: mendaftarkan `query_forsa` sebelum gate Phase 3 + semantic layer Phase 4-5 akan melanggar PRD §5).
- `services/AiConversationService.php` — resolve/lookup percakapan, catat pesan user/assistant, judul otomatis dari pesan pertama.
- `services/AiOrchestrator.php` — rakit system prompt + histori + pesan baru, panggil provider, simpan balasan. System prompt eksplisit melarang mengarang angka FTK (PRD §4.1) karena belum ada tool/data.
- `repositories/AiConversationRepository.php`, `repositories/AiMessageRepository.php` — akses `forsa_ai_conversations`/`forsa_ai_messages` via PDO, mengikuti pola `Forsa\Database::connection()` yang sudah ada.
- `routes/chat.php` — endpoint `POST ai_chat_api.php`: `require_login()`, `csrf_require()`, validasi pesan (tidak kosong, maks 2000 karakter), panggil orchestrator, kembalikan `{conversation_id, reply}`.
- `routes/conversations.php` — endpoint `GET ai_conversations_api.php`: tanpa `?id=` mengembalikan daftar percakapan user; dengan `?id=` mengembalikan detail + seluruh pesan (untuk fitur "Riwayat").

**Baru — lainnya:**
- `config/ai.php` — baca `MODEL_API_KEY`/`MODEL_NAME`/`MODEL_BASE_URL` dari `.env`.
- `database/migrations/006_create_ai_conversation_tables.sql` — tabel `forsa_ai_conversations`, `forsa_ai_messages` (nama tabel disesuaikan ke konvensi `forsa_*` yang sudah ada; struktur kolom mengikuti PRD §45.1/§45.2 persis). **Sudah dijalankan** ke database `forsa` lokal.

**Diubah:**
- [public/assets/js/ai/ai-chat.js](../../../../public/assets/js/ai/ai-chat.js) — diganti dari stub Tahap 1 menjadi pemanggilan nyata ke `ai_chat_api.php`/`ai_conversations_api.php`; "Riwayat" sekarang menampilkan judul percakapan asli dan bisa diklik untuk memuat ulang percakapan lama.
- [route_map.php](../../../../route_map.php) — tambah `ai_chat_api.php`, `ai_conversations_api.php`.
- [composer.json](../../../../composer.json) — tambah 4 PSR-4 mapping untuk `Forsa\Ai\{Providers,Services,Repositories,Tools}`, `composer dump-autoload` sudah dijalankan.
- `.env` / `.env.example` — sudah ada dari Tahap 1, tidak berubah.
- `docs/ai-assistant/README.md` — status Phase 2 ditandai selesai.

## Keputusan Arsitektur (Penyesuaian dari PRD §41)

PRD §41 menyebut struktur folder `controllers/` (`AiChatController.php`, dst). PRD §41 sendiri menyatakan folder ini "mengikuti existing PHP Native Modular Monolith FORSA" — dan pola existing FORSA (`modules/ftk/ftk_tree_api.php`, `modules/dashboard/dashboard_api.php`) **tidak punya lapisan controller**: route file langsung memanggil Service. Untuk konsistensi dengan kode yang sudah ada (bukan mengikuti diagram PRD secara harfiah di titik ini), `routes/chat.php`/`routes/conversations.php` berperan sebagai route+controller tipis, persis pola `ftk_tree_api.php`. Tidak ada perubahan pada behavior yang didokumentasikan PRD, hanya menghapus satu lapisan indirection yang tidak dipakai di manapun pada codebase ini.

File PRD §41 yang **sengaja belum dibuat** karena belum ada fungsi tanpa semantic layer/gate Phase 3:
- `services/AiContextService.php` — context resolution (PRD §34) butuh entity resolution (nama company, metric) yang baru ada di Phase 5.
- `services/AiSuggestionService.php` — suggested questions PRD §33 masih daftar statis hardcode di frontend (sudah cukup untuk Phase 1/2); baru bermakna dibuat dinamis "dari semantic registry yang aktif" setelah semantic registry ada (Phase 5).
- `tools/ToolValidator.php` — tidak ada tool terdaftar untuk divalidasi.
- Semua folder `security/`, `cache/`, `analysis/`, `semantic/`, `query/`, `views/` — semuanya Phase 3 ke atas.

## Database Changes

Migration baru `006_create_ai_conversation_tables.sql`:
- `forsa_ai_conversations(id, user_id FK forsa_users, title, status, created_at, updated_at)` + index `(user_id, updated_at DESC)`.
- `forsa_ai_messages(id, conversation_id FK forsa_ai_conversations ON DELETE CASCADE, role, content, tool_call_id, created_at)` + index `(conversation_id, created_at)`.

Sudah dijalankan: `psql -h 127.0.0.1 -p 5432 -U fadil -d forsa -f database/migrations/006_create_ai_conversation_tables.sql` — `CREATE TABLE`/`CREATE INDEX` berhasil, dikonfirmasi lewat `\d` dan query langsung berisi data uji nyata.

## API Changes

- `POST ai_chat_api.php` — body JSON `{conversation_id: int|null, message: string}`, header `X-CSRF-Token` wajib. Response `{success, data: {conversation_id, reply}}` atau error (`422` pesan kosong/>2000 karakter, `419` CSRF invalid, `502` provider AI gagal dihubungi — pesan generik, tidak membocorkan detail provider ke user).
- `GET ai_conversations_api.php` — tanpa parameter: daftar percakapan user (`id, title, status, created_at, updated_at`). Dengan `?id=`: detail percakapan + seluruh pesan, `404` jika bukan milik user yang login.

## Architecture Changes

Modul `modules/ai/` sekarang punya lapisan backend (providers/services/repositories/routes/tools), mengikuti PSR-4 autoload baru di `composer.json`. Tidak ada perubahan pada modul FTK/dashboard/import yang sudah ada.

## Documentation Updated
- `docs/ai-assistant/README.md` (status Phase 2).
- Laporan ini.

## Tests Performed
- `php -l` pada seluruh file PHP baru — tanpa error.
- `node --check public/assets/js/ai/ai-chat.js` — tanpa error.
- `composer dump-autoload` — berhasil, class ter-load tanpa error (dibuktikan lewat smoke test CLI yang benar-benar memanggil `OpenAiProvider`).
- `grep` em dash — nihil di teks yang dirender ke user.

## Manual Test

Dijalankan nyata (bukan simulasi) terhadap `gpt-4o-mini` memakai API key user, di Browser pane (`http://localhost:8888/Forsa/dashboard`) dan psql:

1. **Smoke test provider** (CLI, sebelum diintegrasikan ke UI): panggil `OpenAiProvider->chat()` langsung → menerima balasan asli dari `gpt-4o-mini` ("Saya adalah FORSA AI Assistant."), `finish_reason=stop`. Membuktikan API key valid dan HTTP call berfungsi.
2. Klik mascot → kirim "Halo, siapa nama kamu?" → balasan asli dari model muncul di sidebar (bukan placeholder Tahap 1 lagi).
3. Kirim "Berapa total FTK Indonesia Power bulan Agustus 2026?" → model **menolak mengarang**, menjawab belum punya akses data dan mengarahkan ke dashboard — memverifikasi guardrail PRD §4.1 bekerja meski belum ada tool/data sama sekali.
4. Klik "Riwayat" → menampilkan judul percakapan asli ("Halo, siapa nama kamu?") dari database, bukan lagi "Belum ada percakapan tersimpan." (karena sekarang sudah ada data nyata).
5. Klik "Baru" → chat kosong. Klik item di "Riwayat" → seluruh 4 pesan (2 user + 2 assistant) dari percakapan sebelumnya dimuat ulang persis, dicek lewat `innerHTML`.
6. Verifikasi langsung ke database (`psql`): `forsa_ai_conversations` berisi 1 baris (title otomatis dari pesan pertama), `forsa_ai_messages` berisi 4 baris dengan role yang benar (`user`/`assistant`) dan isi yang cocok dengan yang tampil di UI.
7. Uji keamanan: POST ke `ai_chat_api.php` tanpa header `X-CSRF-Token` → `419` ditolak. POST dengan `message: ""` → `422` ditolak. Keduanya dicek lewat `fetch()` langsung dari console browser.
8. Tidak ada error di console browser pada seluruh langkah di atas.

## Known Limitations

- Model masih bisa "berhalusinasi" secara umum (di luar topik FTK) karena ini adalah keterbatasan LLM secara umum, bukan sesuatu yang bisa dicegah tool/validator di tahap ini — mitigasi utamanya (larangan mengarang angka FTK) sudah diverifikasi bekerja.
- Tidak ada rate limiting per user (PRD §15.1/§38) — setiap pesan langsung memanggil OpenAI tanpa batas. Wajar untuk tahap pengembangan, tapi **harus ditambahkan sebelum production** (PRD §7 Security phase).
- Tidak ada obrolan lanjutan concurrency/pooling khusus (PRD §38) — memakai koneksi PDO singleton yang sudah ada, cukup untuk beban development.
- Riwayat percakapan menampilkan seluruh percakapan tanpa pagination — cukup untuk sekarang, PRD tidak mewajibkan pagination sampai Phase 8 (Performance).

## Next Step

Sesuai PRD §5/§56/§57, langkah berikutnya adalah **Phase 3 — Database Source Selection**, sebuah **STOP/GATE eksplisit**. Implementasi tidak boleh lanjut membuat semantic model, metric, dimension, relation, tool mapping, database view, atau materialized view sebelum pertanyaan berikut dijawab:

> **Tabel atau view PostgreSQL FORSA mana saja yang ingin digunakan sebagai sumber data AI Assistant?**

Contoh dari PRD: `ftk_snapshot_rows` (atau nama tabel FORSA yang setara, misal `forsa_ftk_snapshot_rows`), `organizations`/`forsa_shap_entities`, `companies`, dll — atau view yang sudah ada.
