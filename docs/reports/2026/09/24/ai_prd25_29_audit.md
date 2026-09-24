# Audit PRD AI Assistant 25–29

Source of truth: `docs/ai-assistant/PRD.md` §25–29, tidak diubah.

| Poin | Existing | Perbaikan |
|---|---|---|
| 25 | SVG 2D placeholder; klaim produksi 3D di luar scope | Karakter original berupa mesh ellipsoid 3D prosedural, perspektif dan pencahayaan, wajah responsif, sirip asimetris, core grafik; biru/netral FORSA |
| 26 | CSS loops, sway/bounce menulis transform yang sama; tidak pause document.hidden | Satu clock requestAnimationFrame, tujuh gerak idle, render dibatasi 30fps, pause visibilitychange |
| 27 | Interval bisa menyimpang dari 10 detik; start selalu reset greeting | Fixed greeting sekali per page load, pilihan acak tanpa pengulangan berurutan tiap 10 detik waktu tab aktif; hide/show tidak reset |
| 28 | Keluar berupa dua transform, masuk hanya slide utuh; timer bisa bertabrakan | Anticipation/jump/exit dan tangan/climb/kepala/badan; hysteresis 50/40 dipertahankan, reverse direction diantrikan |
| 29 | Sidebar langsung terbuka; maskot fade lalu diganti ikon; input fokus sebelum selesai; timer tertinggal setelah close | React/grab/pull/lock/jump/guide berurutan; karakter sama mencapai slot header; inert sampai selesai; close membatalkan state |

Scope: frontend yang diperlukan untuk §25–29, pengujian dan dokumentasi. Tidak ada perubahan semantic layer, database, API, provider, atau perhitungan FTK. Renderer prosedural tidak menghasilkan GLB (§41 di luar scope).

Keputusan: interval frontend mengikuti PRD 10.000ms; konfigurasi lama `MASCOT_GREETING_INTERVAL_MS` tidak lagi mengubah greeting. Reduced motion melewati animasi; tab tersembunyi menghentikan clock dan melanjutkan saat aktif kembali.
