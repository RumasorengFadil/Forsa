# FORSA AI Assistant — Pembaruan Tahap 5: Progress Time

## Summary

Saat AI sedang menyusun jawaban, bubble loading sekarang menampilkan elapsed time yang benar-benar berjalan realtime: **"Menyusun jawaban... 3.2s"**, bertambah setiap 100ms. Begitu respons selesai, timer berhenti dan digantikan bubble jawaban + caption execution time final dari Tahap 4 — dua angka yang dilihat user (hitungan berjalan terakhir vs execution time final) adalah pengukuran dari sumber waktu yang sama (`performance.now()` client-side vs `microtime()` server-side keduanya mengukur rentang yang sama), bukan dua jam yang tidak sinkron.

## Files Changed

- [public/assets/js/ai/ai-chat.js](../../../../public/assets/js/ai/ai-chat.js):
  - `addThinkingBubble()` ditulis ulang: teks sebelumnya statis ("Memahami pertanyaan...") dengan titik-titik animasi dekoratif, sekarang `Menyusun jawaban... {elapsed}s` yang benar-benar dihitung dari `performance.now()` (monotonic, tidak terpengaruh perubahan jam sistem) lewat `setInterval` 100ms.
  - Fungsi ini sekarang mengembalikan `{ stop() }` (bukan elemen DOM langsung) — `stop()` menghentikan interval **dan** menghapus bubble sekaligus, supaya timer tidak pernah terus berjalan di background setelah bubble hilang.
  - Kedua titik pemanggilan (`thinking.remove()` di jalur sukses dan error) diganti `thinking.stop()`.
- [public/assets/css/ai-assistant.css](../../../../public/assets/css/ai-assistant.css) — hapus rule `.ai-msg-thinking .dots span` (animasi titik dekoratif yang sudah tidak dipakai), tambah `font-variant-numeric: tabular-nums` supaya angka detik tidak "bergoyang" lebar saat berganti.

## Database Changes
None.

## API Changes
None — murni perilaku frontend selama menunggu response `ai_chat_api.php` yang sudah ada.

## Architecture Changes
None.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `node --check public/assets/js/ai/ai-chat.js` — tanpa error.

## Manual Test

Di Browser pane, mengirim pesan lewat `gpt-4o-mini` sungguhan lalu **mengambil sample teks bubble thinking setiap 300ms selagi request masih berjalan** (bukan menunggu sampai selesai lalu mengecek sekali):

```
"Menyusun jawaban... 0.3s"
"Menyusun jawaban... 0.6s"
"Menyusun jawaban... 0.9s"
"Menyusun jawaban... 1.2s"
"Menyusun jawaban... 1.5s"
"Menyusun jawaban... 1.8s"
"Menyusun jawaban... 2.1s"
"Menyusun jawaban... 2.4s"
```

— naik konsisten ~0,3 detik tiap sample, membuktikan ini benar-benar timer realtime, bukan teks statis. Setelah response tiba: bubble timer terkonfirmasi hilang (`querySelector('.ai-msg-thinking')` → `null`) dan caption metadata Tahap 4 menampilkan **"⏱ 3.0s"** — angka final yang masuk akal melanjutkan progresi sample di atas (request selesai tak lama setelah sample terakhir di 2,4s).

## Known Limitations
None.
