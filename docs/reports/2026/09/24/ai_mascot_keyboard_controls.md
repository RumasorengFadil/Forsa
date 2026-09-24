# Implementation Report — Kontrol keyboard maskot

## Summary
Default hidden, toggle Ctrl/Cmd+Shift+K, W/S untuk climb/jump, A/D untuk berjalan ke pojok kiri/kanan. Scroll tetap aktif hanya saat fitur maskot diaktifkan. Animasi idle, greeting config, visibility pause, sidebar, dan reduced motion dipertahankan.

Audit renderer tidak menemukan animasi jalan existing. Pose walking ditambahkan pada mesh/renderer yang sama: langkah kaki dan tangan berlawanan, body bounce, orientasi yaw ±90° sesuai arah. Tidak mengganti karakter atau menggunakan aset/dependency tambahan.

## Files Changed
- `modules/dashboard/dashboard.php`: markup default hidden dan metadata shortcut.
- `public/assets/js/ai/mascot-controller.js`: status aktif, shortcut guard, perjalanan, posisi adaptif, integrasi scroll/sidebar.
- `public/assets/js/ai/mascot-renderer.js`: pose walking.
- `public/assets/js/ai/ai-assistant.js`: callback close sidebar.
- `public/assets/css/ai-assistant.css`: default visibility dan greeting kiri.
- `tests/ai/mascot.test.cjs`: regresi kontrol keyboard.

## Database Changes
None.

## API Changes
None.

## Architecture Changes
Memakai clock/state machine existing. Posisi horizontal dinormalisasi terhadap viewport. Walk dapat dibalik atau dihentikan oleh hide, tanpa timer tambahan. Gesture pull sidebar memperhitungkan posisi kiri maskot.

## Documentation Updated
README AI Assistant, indeks docs, laporan ini.

## Tests Performed
- PASS: Node regression suite (default hidden, interval config/fallback, visibility pause, scroll hysteresis, urutan sidebar, duplicate click, cancellation, shortcut guards, walking arah/pembalikan/tujuan, toggle nonaktif tidak dipicu scroll, reduced motion).
- PASS: Node syntax checks, PHP lint, git diff --check.
- Browser fixture dengan markup/aset aktual: default hidden, Ctrl+Shift+K menampilkan maskot, A berjalan menghadap kiri dan tiba di kiri bawah, greeting tidak terpotong, mengetik wasd dan toggle dalam input tidak mengendalikan maskot.
- Dashboard terautentikasi, browser lain, dan perangkat mobile belum diuji pada perubahan ini.

## Manual Test
1. Reload dashboard: maskot hidden, tanpa flash; scroll naik tidak memunculkannya.
2. Ctrl/Cmd+Shift+K: climb sampai idle. Tekan S: jump keluar. W: climb masuk.
3. A: karakter berjalan menghadap kiri sampai kiri bawah. D: menghadap kanan sampai posisi kanan awal. Balik arah sebelum sampai: tidak teleport.
4. Ketika berjalan, S lalu W: hide/show memakai posisi terakhir. Resize viewport: posisi tetap dalam area bawah.
5. Saat aktif, scroll turun/naik: jump/climb existing. Nonaktifkan dengan toggle, lalu scroll/W: tetap hidden.
6. Fokus input/textarea/select/editor/contenteditable: W/S/A/D dan toggle tidak mengendalikan maskot. Coba kombinasi Ctrl+S, Cmd+A, Alt+D: tidak dicegat maskot.
7. Klik maskot dari kiri dan kanan: react/grab/pull/lock/jump/chat berjalan; close mengembalikan posisi sebelumnya. W/S/A/D saat chat tidak mengganggu percakapan.
8. Tab hidden: animasi pause; reduced motion: perpindahan langsung tanpa jalan panjang.

## Known Limitations
Shortcut yang dicadangkan browser/OS/extension tidak bisa dijamin diteruskan ke halaman. Khusus Ctrl/Cmd+Shift+K dapat digunakan fitur developer browser tertentu. Handler hanya mencegah default pada kombinasi yang dikenali dan eligible, tidak memakai capture/stopPropagation, serta menghormati defaultPrevented. Uji browser yang digunakan pengguna diperlukan untuk konflik global tersebut.
