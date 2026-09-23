# FORSA AI Assistant — Pembaruan Tahap 1: Config Maskot

## Summary

Durasi pergantian teks greeting maskot (sebelumnya hardcode `10000` di `mascot-animation.js`, mengikuti PRD §27 "setiap 10 detik berikutnya") sekarang dapat dikonfigurasi lewat `.env` (`MASCOT_GREETING_INTERVAL_MS`), tanpa perlu mengubah kode JS untuk mengubah nilainya.

## Files Changed

- [config/ai.php](../../../../config/ai.php) — tambah `mascot_greeting_interval_ms` (dari `env('MASCOT_GREETING_INTERVAL_MS', 10000)`).
- `.env` / `.env.example` — tambah `MASCOT_GREETING_INTERVAL_MS=10000` (default sesuai PRD §27).
- [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — `require config/ai.php`, expose nilainya ke frontend lewat `window.ForsaAiConfig = { mascotGreetingIntervalMs: ... }` di inline script yang sama dengan `CSRF_TOKEN`.
- [public/assets/js/ai/mascot-animation.js](../../../../public/assets/js/ai/mascot-animation.js) — `create(bubbleEl, intervalMs)` sekarang menerima interval sebagai parameter (fallback `10000` jika tidak diberikan/tidak valid), dipakai di `setInterval(...)` alih-alih angka hardcode.
- [public/assets/js/ai/mascot-controller.js](../../../../public/assets/js/ai/mascot-controller.js) — membaca `window.ForsaAiConfig.mascotGreetingIntervalMs` dan meneruskannya ke `MascotGreeting.create()`.

## Database Changes
None.

## API Changes
None.

## Architecture Changes
None — murni memindahkan satu angka dari hardcode JS ke config (`config/ai.php` → `.env`), pola yang sama dengan `MODEL_NAME`/`AI_DB_USERNAME` yang sudah ada.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `php -l config/ai.php modules/dashboard/dashboard.php` — tanpa error.
- `node --check` pada `mascot-animation.js` dan `mascot-controller.js` — tanpa error.

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`):
1. Default: `window.ForsaAiConfig.mascotGreetingIntervalMs === 10000`, dicek langsung di console.
2. **Uji nyata perubahan nilai**: `.env` diubah sementara ke `MASCOT_GREETING_INTERVAL_MS=2000`, reload halaman → greeting bubble benar-benar berganti teks dalam 2,5 detik (dicek `textContent` sebelum/sesudah `setTimeout`), membuktikan nilai config dari `.env` sungguhan dipakai runtime, bukan sekadar dibaca lalu diabaikan. `.env` dikembalikan ke `10000` setelah pengujian.

## Known Limitations
None.
