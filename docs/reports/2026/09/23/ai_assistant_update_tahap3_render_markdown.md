# FORSA AI Assistant — Pembaruan Tahap 3: Render Markdown Chatbot

## Summary

Respons assistant sekarang dirender sebagai HTML (bold, italic, inline code, bullet list, paragraf), bukan lagi teks mentah dengan simbol Markdown (`**`, `-`, `` ` ``) yang terlihat apa adanya. Contoh dari permintaan: `- **PLN NP**: Gap sebesar -950` sekarang tampil sebagai item list sungguhan dengan **PLN NP** dicetak tebal.

## Files Changed

- **Baru:** [public/assets/js/ai/ai-markdown.js](../../../../public/assets/js/ai/ai-markdown.js) — `window.ForsaAi.renderMarkdown(text)`. Renderer subset-Markdown minimal (bukan CommonMark penuh): bold `**x**`, italic `*x*`, inline code `` `x` ``, bullet list `- `/`* `, paragraf/line break. Meng-escape HTML terlebih dahulu **sebelum** menerapkan format apa pun, supaya balasan yang (secara tidak sengaja atau lewat percobaan) mengandung `<script>` tetap tampil sebagai teks literal, tidak pernah dieksekusi.
- [public/assets/js/ai/ai-chat.js](../../../../public/assets/js/ai/ai-chat.js) — `addMessage()`: pesan **user** tetap `textContent` (teks mentah, tidak pernah diinterpretasi sebagai Markdown); pesan **assistant** sekarang `innerHTML = renderMarkdown(text)`.
- [public/assets/css/ai-assistant.css](../../../../public/assets/css/ai-assistant.css) — reset margin elemen blok (`p`, `ul`, `li`) di dalam bubble supaya tetap terlihat seperti pesan chat ringkas, bukan artikel penuh; styling `code` (background halus, tabular-nums untuk angka).
- [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — tambah `<script src=".../ai-markdown.js">` sebelum `ai-chat.js` (urutan load, karena `ai-chat.js` memanggilnya).

## Database Changes
None.

## API Changes
None — `ai_chat_api.php` tidak berubah, ini murni perubahan cara menampilkan `reply` yang sudah ada di frontend.

## Architecture Changes
None.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `php -l modules/dashboard/dashboard.php` — tanpa error.
- `node --check` pada `ai-markdown.js` dan `ai-chat.js` — tanpa error.

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`):
1. `renderMarkdown('- **PLN NP**: Gap sebesar -950\n- **PLN IP**: ...\n\nCoba \`query_forsa\` dan *italic*.')` → menghasilkan `<ul><li><strong>PLN NP</strong>...` dst. — persis contoh dari permintaan.
2. **Uji keamanan**: `renderMarkdown('<script>alert(1)</script> and **bold**')` → hasil `&lt;script&gt;alert(1)&lt;/script&gt;` (di-escape, bukan tag `<script>` sungguhan) — dikonfirmasi `html.includes('<script>') === false`.
3. **End-to-end lewat `gpt-4o-mini` sungguhan**: "Bandingkan gap FTK PLN NP dan PLN IP dalam format list markdown, tebalkan nama perusahaannya." → balasan asli model mengandung Markdown (`**PLN NP**`, list `-`), dan setelah dirender DOM benar-benar berisi elemen `<strong>` (2 buah) dan `<ul><li>` (2 item) sungguhan — bukan teks bermunculan tanda bintang/dash.

## Known Limitations
- Bukan implementasi CommonMark lengkap (tidak ada heading `#`, blockquote, tabel, numbered list `1.`, nested list) — hanya subset yang benar-benar dipakai model dalam menjawab pertanyaan FORSA (bold/italic/kode/bullet list/paragraf). Bisa diperluas kalau ternyata dibutuhkan konstruksi lain di kemudian hari.
