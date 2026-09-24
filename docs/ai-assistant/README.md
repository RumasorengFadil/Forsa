# FORSA AI Assistant

Fitur terpisah dari produk inti FORSA (dashboard FTK/realisasi), diimplementasikan bertahap sesuai [`PRD.md`](PRD.md) (disalin dari `PRD_FORSA_AI_Assistant.md` yang diberikan user, versi 1.0). `PRD.md` di folder ini adalah source of truth untuk fitur AI Assistant — jangan diubah kecuali user secara eksplisit meminta perubahan requirement.

## Status Implementasi

| Phase (sesuai PRD §56) | Status | Keterangan |
|---|---|---|
| 1 — UI Foundation | **Selesai** | Mascot placeholder, sidebar shell, greeting, scroll behavior, state machine, conversation UI (tanpa backend). Lihat `docs/reports/2026/09/23/ai_assistant_tahap1_ui_foundation.md`. |
| 2 — AI Core | **Selesai** | Provider abstraction (`AiProviderInterface`/`OpenAiProvider`, real call ke `gpt-4o-mini`), tool registry (kosong, sengaja — lihat catatan di file itu), conversation storage (`forsa_ai_conversations`/`forsa_ai_messages`). Lihat `docs/reports/2026/09/23/ai_assistant_tahap2_ai_core.md`. |
| 3 — Database Source Selection (**GATE**) | **Selesai (dijawab user)** | Sumber data disetujui: `forsa_ftk_snapshot_rows`, `forsa_ftk_snapshots`, `forsa_shap_entities`. |
| 4 — Data Analysis | **Selesai** | Lihat [`database-sources.md`](database-sources.md) (schema, relasi, security classification, MV analysis — rekomendasi: base table, volume data terlalu kecil untuk MV) dan [`semantic-layer.md`](semantic-layer.md) (proposal metric/dimension/tool schema). |
| 5 — Semantic Layer | **Selesai** | `SemanticRegistry`/`AliasResolver`/`PolicyResolver` + model `ftk_workforce` (`modules/ai/semantic/`). |
| 6 — Analytics Tool (`query_forsa`) | **Selesai** | `QueryPlanner`/`QueryBuilder`/`QueryExecutor` + `QueryForsaTool` terdaftar di `ToolRegistry`, tool-calling loop di `AiOrchestrator`. Diverifikasi dengan pertanyaan nyata lewat `gpt-4o-mini` DAN uji keamanan langsung (SQL injection, nama tabel/kolom mentah — semua diblokir di layer validasi, bukan cuma ditolak LLM). Lihat `docs/reports/2026/09/23/ai_assistant_tahap4_semantic_layer_tool.md`. |
| 7 — Security | **Selesai** | Dedicated read-only Postgres role `forsa_ai_reader` (SELECT-only ke 3 tabel yang disetujui, diverifikasi lewat percobaan akses langsung — lihat laporan Tahap 5), RBAC scope, query limit (maks 100 baris), query timeout (5s), rate limiting per user (`RateLimiter`, maks 10 pesan/60 detik). |
| 8 — Performance | **Selesai** | Cache file-based `AiQueryCache` (PRD §20, key `ai:{scope_hash}:{query_hash}`, TTL 5 menit), diverifikasi 240x lebih cepat untuk query berulang & tidak bocor lintas scope. Index/MV: tidak ada perubahan, sudah dianalisis cukup di `database-sources.md` §6. |
| 9 — Evaluation | **Selesai (sebagian otomatis)** | `tools/ai_evaluation_cli.php` — 10 skenario deterministik PRD §53 (semua PASS, repeatable). 2 kategori yang butuh penalaran LLM (follow-up context, ambiguous query) diuji manual — lihat laporan Tahap 6. |

## Pembaruan Pasca-MVP

Setelah 9 phase di atas selesai, dilakukan pembaruan tambahan (di luar penomoran phase PRD, tapi tetap dalam scope AI Assistant), masing-masing dengan laporan tersendiri di `docs/reports/2026/09/23/ai_assistant_update_tahapN_*.md`:

1. **Config Maskot** — durasi rotasi greeting (`MASCOT_GREETING_INTERVAL_MS`) dipindah dari hardcode ke `.env`/`config/ai.php`.
2. **Expand/Collapse Sidebar** — ikon di header sidebar untuk memperbesar (~50% lebar layar) / mengecilkan kembali sidebar chat.
3. **Render Markdown Chatbot** — balasan assistant (bold/italic/kode/list) dirender sebagai HTML sungguhan, bukan simbol Markdown mentah.
4. **Metadata Jawaban** — execution time, model, dan sumber jawaban ditampilkan ringkas di bawah tiap balasan.
5. **Progress Time** — elapsed time realtime ("Menyusun jawaban... 3.2s") selama menunggu, diganti execution time final saat selesai.

## Catatan Implementasi

- Implementasi maskot diperbaiki berdasarkan PRD §25–29: mesh 3D prosedural lokal, idle `requestAnimationFrame` yang pause saat tab hidden, greeting sesuai interval config (default 10 detik aktif) tanpa reset saat hide/show, jump/climb dengan hysteresis, dan urutan react/grab/pull/lock/jump sebelum chat aktif. Interval dibaca dari `.env` melalui `config/ai.php` → `window.ForsaAiConfig` → controller greeting. Nilai tidak valid atau ≤0 menggunakan fallback 10.000ms. Reload dashboard setelah mengubah config.
- Lihat [audit](../reports/2026/09/24/ai_prd25_29_audit.md), [Tahap 1](../reports/2026/09/24/ai_prd25_29_tahap1_visual_idle_greeting.md), [Tahap 2](../reports/2026/09/24/ai_prd25_29_tahap2_scroll.md), dan [Tahap 3](../reports/2026/09/24/ai_prd25_29_tahap3_sidebar.md) untuk hasil verifikasi dan manual test.
- Sidebar chat Phase 1 murni UI shell: mengirim pesan menampilkan balasan placeholder yang jujur (bukan jawaban AI sungguhan), karena belum ada LLM gateway/tool calling (itu Phase 2) maupun sumber data yang disetujui (itu Phase 3, wajib gate ke user lebih dulu per PRD §5).
- Model API key yang diberikan user (`gpt-4o-mini`) belum dipakai di Phase 1 karena Phase 1 tidak menyentuh backend AI sama sekali. Mulai dipakai di Phase 2 lewat `OpenAiProvider`, disimpan di `.env` (sudah ter-gitignore, dikonfirmasi tidak pernah masuk git), dan tidak pernah keluar dari class itu (tidak di-log, tidak dikirim ke client, tidak masuk prompt).
- Sejak Phase 2, sidebar chat sudah benar-benar memanggil `gpt-4o-mini` dan menyimpan percakapan ke database — tapi model **masih tidak punya akses data FTK/realisasi sama sekali** (tool registry kosong, system prompt eksplisit melarang mengarang angka). Diverifikasi: model menolak menjawab pertanyaan angka spesifik dan mengarahkan ke dashboard, bukan mengarang.

## Kontrol maskot

- Default hidden (juga sebelum JavaScript selesai dimuat).
- `Ctrl/Cmd + Shift + K`: aktifkan/nonaktifkan maskot dengan climb/jump existing. Saat nonaktif, scroll tidak memunculkannya.
- Saat aktif: `W` tampil, `S` sembunyi sementara, `A` berjalan ke kiri bawah, `D` berjalan ke kanan bawah. Saat hidden sementara, W atau scroll-up bisa menampilkan kembali.
- Jalan memakai pose langkah/ayunan tangan pada renderer existing dan badan menghadap arah jalan. Posisi mengikuti ukuran viewport; greeting di sisi kiri tetap masuk viewport.
- Shortcut diabaikan pada input, textarea, select, contenteditable, editor, dan kontrol interaktif lain; juga saat IME, key repeat, atau event sudah ditangani. W/S/A/D hanya tanpa modifier. Selama sidebar membuka/terbuka, W/S/A/D tidak mengganggu chat. Toggle utama di luar area mengetik dapat menutup sidebar dan menonaktifkan maskot.
- Interval greeting tetap dari config/.env; clock pause ketika tab hidden atau maskot dinonaktifkan. Reduced motion langsung mencapai posisi akhir.

[Implementation Report dan manual test kontrol keyboard](../reports/2026/09/24/ai_mascot_keyboard_controls.md).

## Sisi panel dan durasi jalan

Panel mengikuti sisi maskot saat dibuka: kanan atau kiri. Arah grab/pull/jump mengikuti sisi tersebut. Pada desktop, dashboard menyediakan ruang sesuai lebar panel, termasuk saat expand/collapse; pada mobile ≤640px, panel fullscreen menggantikan tampilan dashboard dan dashboard sementara inert. Menutup panel mengembalikan ruang konten.

`MASCOT_WALK_DURATION_MS=6000` di `.env` dibaca `config/ai.php` lalu diteruskan ke controller. Satuan milidetik, durasi perjalanan penuh antarsudut (6000 = 6 detik); perjalanan sebagian proporsional terhadap jarak. Nilai kosong/tidak valid/≤0 fallback 6000ms. Reload dashboard setelah perubahan. Greeting tetap memakai `MASCOT_GREETING_INTERVAL_MS`.

[Laporan panel adaptif dan config jalan](../reports/2026/09/24/ai_mascot_sidebar_walk_config.md).
