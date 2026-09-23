# FORSA AI Assistant — Pembaruan Tahap 4: Metadata Jawaban

## Summary

Setiap balasan assistant sekarang menampilkan caption ringkas di bawahnya: **Execution Time**, **Model** yang dipakai, dan **Sumber jawaban**. Sumber jawaban jujur apa adanya: kalau tool `query_forsa` dipanggil, tampil "Data FORSA (snapshot tersimpan) — periode YYYY-MM-DD"; kalau tidak (percakapan umum, atau model menolak/bertanya balik), tampil "Percakapan umum (tidak menggunakan data FORSA)" — **tidak pernah menampilkan SQL**, sesuai PRD §31.

## Files Changed

- [modules/ai/services/AiOrchestrator.php](../../../../modules/ai/services/AiOrchestrator.php):
  - `reply()` sekarang mengukur waktu eksekusi total (`microtime()` dari awal sampai balasan akhir siap), menerima `modelName` di constructor, dan mengumpulkan tool apa saja yang benar-benar dipanggil selama turn ini (`$toolsUsed`).
  - `runTool()` sekarang mengembalikan tuple `[jsonUntukLLM, metadata]` alih-alih string saja, supaya `reply()` tahu tool mana yang sukses dipakai tanpa membaca ulang log audit.
  - `buildSourceLabel()` baru — membangun label sumber yang jujur dan ringkas dari daftar tool yang dipakai, dengan periode data yang sebenarnya di-resolve (bukan SQL).
  - Return `reply()` berubah dari `string` menjadi array `{reply, execution_time_ms, model, source}`.
- [modules/ai/routes/chat.php](../../../../modules/ai/routes/chat.php) — meneruskan `$aiConfig['model']` ke `AiOrchestrator`, response JSON sekarang menyertakan `execution_time_ms`, `model`, `source`.
- [public/assets/js/ai/ai-chat.js](../../../../public/assets/js/ai/ai-chat.js) — `addMessageMeta()` baru, dipanggil setelah `addMessage('assistant', ...)` untuk merender caption metadata di bawah bubble.
- [public/assets/css/ai-assistant.css](../../../../public/assets/css/ai-assistant.css) — `.ai-msg-meta` (teks kecil abu-abu, tabular-nums untuk angka waktu).

## Database Changes
None.

## API Changes
`POST ai_chat_api.php` response `data` bertambah 3 field baru: `execution_time_ms` (int), `model` (string), `source` (string|null). Field `reply`/`conversation_id` tidak berubah — kompatibel untuk konsumen lama.

## Architecture Changes
None — perluasan struktur return `AiOrchestrator::reply()` yang sudah ada, tidak ada lapisan baru.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `php -l` pada `AiOrchestrator.php`, `chat.php` — tanpa error.
- `node --check ai-chat.js` — tanpa error.

## Manual Test

Lewat `gpt-4o-mini` sungguhan di Browser pane:
1. "Berapa gap FTK PLN NP periode terbaru?" (memicu tool) → caption menampilkan **"⏱ 2.8s · gpt-4o-mini · Data FORSA (snapshot tersimpan) — periode 2026-09-01"** — periode yang ditampilkan cocok dengan periode yang benar-benar dipakai query (dikonfirmasi sama dengan pengujian tahap-tahap sebelumnya).
2. "Halo, siapa kamu?" (tidak memicu tool) → caption **"⏱ 1.2s · gpt-4o-mini · Percakapan umum (tidak menggunakan data FORSA)"** — jujur menandakan tidak ada data FORSA yang dipakai untuk balasan ini.

## Known Limitations
- Metadata hanya tampil untuk pesan yang baru dikirim di sesi berjalan — percakapan yang dimuat ulang lewat "Riwayat" tidak menampilkan metadata (execution time/model/source tidak disimpan di `forsa_ai_messages`, hanya `role`+`content`). Ini disengaja: menyimpan metadata di tabel pesan akan mengubah skema PRD §45.2 tanpa kebutuhan eksplisit — bisa ditambahkan nanti kalau memang diperlukan.
