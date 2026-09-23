# Dashboard Tahap 1 — Perbaiki Expand Drill-down

## Summary

Expand/collapse pada tabel drill-down berhenti berfungsi total setelah user mengganti periode **Realisasi SH/AP** (atau memilih histori/snapshot lain). Root cause: listener klik tombol expand didelegasikan ke elemen `#tree-tbody`, tapi elemen itu **dibuat ulang dari nol** setiap kali dashboard di-render ulang (ganti periode meng-assign ulang `innerHTML` dari `#dashboard-content`), sementara flag guard (`treeHandlersBound`) yang seharusnya mencegah listener dobel malah mencegah listener menempel lagi ke tbody yang baru. Diperbaiki dengan memindahkan delegasi ke `document` (elemen yang tidak pernah diganti).

Catatan konteks: direktori kerja baru saja berpindah ke copy proyek ini (`/Applications/MAMP/htdocs/Forsa`, disajikan via MAMP Apache di `http://localhost:8888/Forsa/`). Copy ini punya riwayat commit sendiri yang sudah berkembang lebih jauh (routing subfolder-agnostic, dsb.) tapi belum pernah menerima perbaikan bug ini — jadi perbaikan yang sebelumnya sudah dibuat di copy lain (`/Users/fadil/Pemrograman/php/Forsa`) diterapkan ulang di sini secara terpisah.

## Root Cause

```js
let treeHandlersBound = false;
function attachTreeHandlers() {
    if (treeHandlersBound) return;
    treeHandlersBound = true;
    document.getElementById('tree-tbody').addEventListener('click', ...);
}
```

`attachTreeHandlers()` dipanggil setiap kali `renderTree()` selesai memuat data (termasuk setiap ganti periode). Tujuan `treeHandlersBound` awalnya benar: mencegah listener dobel menempel di elemen yang sama berulang kali. Tapi asumsinya salah — **`#tree-tbody` bukan elemen yang persisten**. Saat periode diganti, `loadDashboard()` meng-assign ulang `content.innerHTML = renderDashboard(data)`, yang membuang seluruh subtree lama (termasuk `#tree-tbody` beserta listener yang menempel di situ) dan membuat `#tree-tbody` yang benar-benar baru. Karena `treeHandlersBound` sudah `true` dari load pertama, `attachTreeHandlers()` langsung `return` tanpa pernah menempelkan listener ke tbody yang baru — tombol "+"/"−" jadi tidak bereaksi apa pun terhadap klik.

## Fix

Delegasi listener dipindah dari `document.getElementById('tree-tbody')` ke `document` — elemen yang tidak pernah di-replace berapa kali pun dashboard di-render ulang. `.tree-toggle` sudah diverifikasi sebagai class yang hanya dipakai untuk tombol expand tree (tidak ada elemen lain yang memakainya), jadi delegasi di level `document` aman tanpa perlu scoping tambahan. Tidak ada perubahan pada logic expand/collapse itu sendiri.

## Files Changed

- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js) — target delegasi `attachTreeHandlers()` diganti `document.getElementById('tree-tbody')` → `document`.
- [CLAUDE.md](../../../../CLAUDE.md) — catatan konvensi UI tentang delegasi tree diperbarui agar tidak lagi menyesatkan sesi berikutnya (sebelumnya menyebut "`#tree-tbody` bound once", padahal itu justru penyebab bug ini).

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None.

## Documentation Updated

- `CLAUDE.md` (konvensi UI — delegasi listener tree).
- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Dijalankan di Browser pane melalui `http://localhost:8888/Forsa/` (MAMP Apache, bukan `php -S`), memakai data uji yang sudah ada di database bersama (snapshot PLN NP & PLN IP untuk periode September 2026 dan Agustus 2026):

1. Login (`admin@forsa.local` / `forsa123`) → dashboard tampil, periode default September 2026, SH/AP "PLN NP".
2. Expand "PLN NP" pada periode default → berhasil menampilkan UI/UL/UP (baseline: expand bekerja normal sebelum ganti periode).
3. Ganti dropdown histori ke "Agustus 2026" (`period:2026-08-01`) → tabel tree memuat ulang menampilkan "PLN IP" dan "PLN NP" (keduanya collapsed).
4. Klik tombol "+" pada "PLN IP" → berhasil expand menampilkan UI/UL/UP dengan angka yang benar (FTK 821/674/4.157 dst., konsisten dengan snapshot Agustus) — mengonfirmasi fix bekerja tepat pada skenario yang dilaporkan (expand setelah ganti periode).
5. Regresi: expand tetap berfungsi normal pada page-load pertama (belum pernah ganti periode) — tidak ada regresi pada skenario yang sebelumnya sudah bekerja.

## Known Limitations

None.
