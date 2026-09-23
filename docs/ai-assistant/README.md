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

## Catatan Implementasi

- Mascot Phase 1 adalah **placeholder SVG 2D**, bukan aset 3D (`.glb`) final yang disebut PRD §41 — pembuatan model 3D adalah pekerjaan produksi aset terpisah (butuh 3D artist/pipeline), di luar cakupan yang bisa dikerjakan lewat coding pass. Arsitektur interaksi (state machine, idle animation, scroll show/hide, klik membuka sidebar) sudah mengikuti PRD §26/§28/§29/§43 secara penuh, sehingga tinggal mengganti aset SVG dengan render 3D nantinya tanpa mengubah logic.
- Sidebar chat Phase 1 murni UI shell: mengirim pesan menampilkan balasan placeholder yang jujur (bukan jawaban AI sungguhan), karena belum ada LLM gateway/tool calling (itu Phase 2) maupun sumber data yang disetujui (itu Phase 3, wajib gate ke user lebih dulu per PRD §5).
- Model API key yang diberikan user (`gpt-4o-mini`) belum dipakai di Phase 1 karena Phase 1 tidak menyentuh backend AI sama sekali. Mulai dipakai di Phase 2 lewat `OpenAiProvider`, disimpan di `.env` (sudah ter-gitignore, dikonfirmasi tidak pernah masuk git), dan tidak pernah keluar dari class itu (tidak di-log, tidak dikirim ke client, tidak masuk prompt).
- Sejak Phase 2, sidebar chat sudah benar-benar memanggil `gpt-4o-mini` dan menyimpan percakapan ke database — tapi model **masih tidak punya akses data FTK/realisasi sama sekali** (tool registry kosong, system prompt eksplisit melarang mengarang angka). Diverifikasi: model menolak menjawab pertanyaan angka spesifik dan mengarahkan ke dashboard, bukan mengarang.
