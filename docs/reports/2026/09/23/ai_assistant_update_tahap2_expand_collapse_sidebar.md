# FORSA AI Assistant — Pembaruan Tahap 2: Expand/Collapse Sidebar

## Summary

Ditambahkan tombol expand/collapse (ikon panah diagonal) di header sidebar AI. Normal = lebar existing (`min(420px, 100vw)`); Expand = ~50% lebar layar (`50vw`); Collapse = kembali ke lebar normal. Di breakpoint mobile (sidebar sudah full-screen drawer), tombol otomatis disembunyikan karena tidak ada gunanya (dan `50vw` di sana justru akan mengecilkan sidebar, bukan membesarkan).

## Files Changed

- [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — tambah tombol `#btn-ai-expand` (SVG ikon) di `.ai-sidebar-header-actions`, di antara "Riwayat" dan "Tutup".
- [public/assets/css/ai-assistant.css](../../../../public/assets/css/ai-assistant.css):
  - `.ai-sidebar { transition: transform ..., width .28s ease; }` — animasi halus saat resize.
  - `.ai-sidebar.expanded { width: 50vw; }`.
  - `.ai-sidebar-icon` — styling tombol icon persegi.
  - Di media query `max-width: 640px`: `#btn-ai-expand { display: none; }` dan `.ai-sidebar.expanded { width: 100vw; }` (fallback aman kalau class sempat aktif sebelum resize ke mobile).
- [public/assets/js/ai/ai-chat.js](../../../../public/assets/js/ai/ai-chat.js) — listener klik `#btn-ai-expand`: toggle class `expanded` pada `#ai-sidebar`, swap ikon SVG (panah keluar ↔ panah masuk), update `aria-pressed`/`aria-label`.

## Database Changes
None.

## API Changes
None.

## Architecture Changes
None — `#ai-sidebar` tetap `position: fixed`, jadi resize lebar sidebar murni mengubah ukuran overlay itu sendiri, tidak pernah membuat dashboard di baliknya reflow.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `php -l modules/dashboard/dashboard.php` — tanpa error.
- `node --check public/assets/js/ai/ai-chat.js` — tanpa error.

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`, viewport desktop 1024px):
1. Buka sidebar, cek lebar awal → `getComputedStyle(sidebar).width === "420px"`.
2. Klik tombol expand → class `expanded` aktif, `aria-pressed="true"`, lebar berubah jadi **512px** (tepat 50% dari 1024px viewport).
3. Klik lagi (collapse) → class `expanded` hilang, `aria-pressed="false"`, lebar kembali **420px**.
4. **Dashboard di belakang tidak terganggu**: lebar `.page-wrap` diukur sebelum dan selama sidebar dalam kondisi expanded → identik (1024px, tidak berubah) — dikonfirmasi karena sidebar `position: fixed` (overlay murni, bukan sibling flex/grid).
5. Viewport diubah ke mobile (375px): `#btn-ai-expand` terkonfirmasi `display: none` (tersembunyi), sidebar tetap 375px (full-width) seperti sebelum ada fitur ini — tidak ada regresi pada perilaku mobile existing.

## Known Limitations
- Status expanded/collapsed tidak disimpan lintas sesi (reset ke normal setiap kali sidebar dibuka ulang setelah reload halaman) — tidak diminta untuk persist, jadi tidak ditambahkan.
