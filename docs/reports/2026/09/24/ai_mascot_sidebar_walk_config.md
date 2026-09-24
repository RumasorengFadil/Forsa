# Implementation Report — Panel mengikuti maskot dan config durasi jalan

## Summary
Panel dibuka dari sisi maskot, dengan pull/jump yang dicerminkan pada sisi kiri. Ruang dashboard desktop mengikuti bagian panel yang terlihat agar tidak tertutup, termasuk saat tutup/expand. Mobile mempertahankan fullscreen dan menonaktifkan konten belakang selama panel terlihat. Shortcut, idle, scroll, greeting config, dan reduced motion tetap tersedia.

`MASCOT_WALK_DURATION_MS=6000` ditambahkan ke `.env` lokal dan `.env.example`. Config `mascot_walk_duration_ms` diteruskan sebagai `mascotWalkDurationMs`. Nilai positif menentukan durasi penuh antarsudut, perjalanan parsial proporsional; fallback 6000ms.

## Files Changed
`.env` (lokal, tidak dilacak), `.env.example`, `config/ai.php`, `modules/dashboard/dashboard.php`, `public/assets/js/ai/mascot-controller.js`, `public/assets/js/ai/ai-assistant.js`, `public/assets/css/ai-assistant.css`, `tests/ai/mascot.test.cjs`, dokumentasi.

## Database Changes
None.

## API Changes
None.

## Architecture Changes
Lifecycle/state machine existing dipertahankan. Posisi panel dikunci saat pembukaan berdasarkan posisi horizontal maskot. Margin dashboard mengikuti batas panel aktual melalui clock yang sama, sehingga resize/expand/close tetap sinkron. Perpindahan sisi panel saat tertutup dilakukan di luar viewport tanpa transisi lintas konten.

## Documentation Updated
README AI Assistant dan indeks docs.

## Tests Performed
- PASS: regresi keyboard, scroll, greeting, sidebar dan reduced motion.
- PASS: durasi 2000/4000ms serta fallback 0/negatif/missing; panel kiri/kanan, arah pull, reservasi margin dan penggantian dashboard mobile melalui test deterministik.
- PASS: lint PHP, node syntax check, git diff --check.
- PASS: pembacaan config PHP lokal menghasilkan durasi 6000ms.
- Browser fixture aktual: panel kiri dan expand diverifikasi secara visual; dashboard tetap berada di luar panel. Tidak mengirim request AI.

## Manual Test
1. Reload dashboard, aktifkan Ctrl/Cmd+Shift+K. Klik maskot kanan: panel masuk dari kanan, konten menyesuaikan lebar tanpa tertutup.
2. Tutup, tekan A, tunggu sampai kiri, klik maskot: panel masuk dari kiri, tangan menarik tepi panel dan maskot melompat ke header kiri.
3. Expand/collapse kedua sisi: konten dan guide mengikuti ukuran panel. Tutup: lebar dashboard kembali penuh.
4. Tekan D dan buka lagi: panel kembali kanan tanpa melintas menutupi dashboard saat masih tertutup.
5. Set `MASCOT_WALK_DURATION_MS=2000`, reload: perjalanan penuh sekitar 2 detik aktif. Ubah ke 8000: sekitar 8 detik. Nilai 0: fallback 6 detik. Kembalikan nilai pilihan pengguna.
6. Uji viewport ≤640px: panel fullscreen, konten belakang tidak terlihat/fokus; setelah ditutup dashboard kembali normal.
7. Coba W/S, scroll, typing dalam input/editor dan reduced motion: perilaku sebelumnya tetap berlaku.

## Known Limitations
Uji visual menggunakan fixture dengan markup/aset aktual, bukan dashboard terautentikasi dengan data penuh. Mobile diuji melalui logika otomatis, belum visual di perangkat fisik. Config berlaku setelah reload dan clock tetap pause saat tab hidden.
