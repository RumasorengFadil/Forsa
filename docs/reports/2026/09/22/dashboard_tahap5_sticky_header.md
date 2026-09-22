# Dashboard Tahap 5 — Sticky Header Tabel

## Summary

Membuat header grouping (baris metrik FTK/Organik/Tugas Karya/Pihak Ketiga/Total Real./Sisa) pada tabel drill-down FTK ikut sticky, sejajar tepat di bawah header jenjang jabatan (baris TOTAL/MA/MM/...) yang sudah sticky sebelumnya — tanpa saling menimpa, tetap sejajar dengan kolom body saat horizontal scroll, dan tetap terbaca saat vertical scroll.

## Root Cause

Sebelumnya **kedua** baris header (`tr.group-row` dan baris metrik di bawahnya) sama-sama diberi `position: sticky; top: 0;` lewat satu rule CSS umum (`.tree-table thead th`). Karena keduanya memakai `top:0` yang sama, begitu tabel di-scroll vertikal, baris metrik (yang secara alami berada *di bawah* baris jenjang) akan menempel tepat di `top:0` juga — menimpa/overlap baris jenjang di atasnya alih-alih berbaris rapi di bawahnya.

## Fix

Baris jenjang (`tr.group-row`) tetap `top: 0`. Baris metrik di bawahnya diberi `top: var(--tree-header1-h)` — sebuah CSS custom property yang **diukur langsung dari tinggi render nyata** baris jenjang (`getBoundingClientRect().height`) via JavaScript setelah setiap render tabel, bukan angka piksel tebakan (nilai terukur ternyata pecahan, `24.5px`, yang tidak mungkin ditebak tepat lewat kalkulasi manual dari font-size/padding). Ini menjamin kedua layer selalu presisi bersebelahan pada resolusi/zoom apa pun.

Stacking order (`z-index`) juga dirapikan: sel pojok sticky (kolom nama, rowspan 2) = 7 → baris jenjang = 6 → baris metrik = 5 → kolom nama sticky pada body = 2.

## Files Changed

- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — pemisahan `top` untuk `tr.group-row th` (0) vs `tr:not(.group-row) th` (`var(--tree-header1-h, 25px)`), z-index dirapikan berlapis.
- [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js) — `syncTreeHeaderStickyOffset()` (mengukur tinggi `tr.group-row` dan meng-set CSS variable pada `#tree-table`), dipanggil setiap kali dashboard di-render ulang (ganti periode/histori) dan pada `resize` (debounced 150ms, mengikuti pola debounce yang sudah dipakai di Guided Tour Tahap 1).

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None — perbaikan murni CSS + satu helper JS pengukuran DOM.

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Di Browser pane (snapshot PLN IP Agustus 2026, tree di-expand hingga PLN IP → UP menampilkan >10 baris Unit Pelaksana untuk memastikan ada cukup konten yang bisa di-scroll):

1. **Tidak overlap**: `getBoundingClientRect()` pada kedua baris header diukur via JS — `groupRow.bottom` dan `subRow.top` identik persis (`310.296875`), selisih `0` px. Tidak ada celah maupun tumpang tindih.
2. **Sejajar saat horizontal scroll**: `.tree-scroll.scrollLeft` diset ke `300`; posisi kiri (`getBoundingClientRect().left`) sel header kolom pertama dan sel body kolom pertama pada baris manapun identik (`86.5`) — kedua baris header bergerak bersama body, tidak "lepas"/miring.
3. **Tetap terbaca saat vertical scroll**: scroll internal `.tree-scroll` ke bawah (melewati >10 baris Unit Pelaksana) — kedua baris header (TOTAL/MA/... dan FTK/Organik/...) tetap terlihat penuh & tidak digantikan oleh baris body yang lewat di baliknya; kolom nama ("UNIT/ORGANISASI/JABATAN") juga tetap sticky di kiri seperti sebelumnya.
4. Verifikasi visual (screenshot) pada dua state scroll (vertikal dan horizontal) menunjukkan kedua header selalu utuh, tersusun rapi, tanpa distorsi teks.

## Known Limitations

- `--tree-header1-h` diukur ulang setiap render dashboard dan saat window resize; ia **tidak** diukur ulang bila hanya isi tabel yang berubah tanpa full re-render (mis. hanya expand/collapse baris) — namun ini aman karena tinggi baris header tidak bergantung pada jumlah baris body, jadi tidak perlu diukur ulang untuk kasus itu.

## Update — Perbaikan Susulan: Kolom Nama Melompat Saat Horizontal Scroll

Setelah laporan awal di atas, user melaporkan bahwa header "UNIT / ORGANISASI / JABATAN" **berpindah posisi/ketinggian ke bawah TOTAL/MA/MM/...** tepat ketika tabel di-scroll horizontal — bug yang tidak tertangkap pada pengujian awal karena verifikasi sebelumnya hanya mengukur *box* header (`getBoundingClientRect()`), bukan urutan render visual (paint order) saat scroll horizontal digabung dengan sel rowspan.

### Root Cause Sebenarnya (dua lapis)

1. **Sel pojok rowspan="2" tidak stabil saat sticky dua-arah**: sel `UNIT/ORGANISASI/JABATAN` sebelumnya satu `<th rowspan="2">` yang harus sticky di DUA sumbu sekaligus (`top:0` dan `left:0`). Chrome/Safari diketahui salah menghitung *stacking order* untuk sel semacam ini begitu tabel di-scroll horizontal — z-index yang dideklarasikan tidak lagi dihormati, sehingga header "TOTAL"/"MA" (row 1) ikut ter-render **di atas** sel pojok, membuatnya seolah "turun" ke level baris metrik (row 2).
2. **Bug spesifisitas CSS** (ditemukan saat memperbaiki poin 1): setelah sel pojok dipecah menjadi dua `<th class="col-name">` biasa (satu per baris, tanpa `rowspan`), rule `.tree-table thead th.col-name` (spesifisitas 2 elemen + 2 class) ternyata masih kalah oleh `.tree-table thead tr.group-row th` (3 elemen + 2 class) — sehingga `z-index`/`background` milik `col-name` tetap tertimpa nilai milik `group-row`, dan sel nama sempat hilang total dari tampilan (background & z-index ikut ke group-row, bukan miliknya sendiri).

### Fix

1. `renderTreeHeader()` di `dashboard.js` tidak lagi memakai `rowspan="2"` untuk sel nama. Baris jenjang (row 1) berisi `<th class="col-name">UNIT / ORGANISASI / JABATAN</th>` sungguhan; baris metrik (row 2) berisi `<th class="col-name"></th>` kosong dengan background/border yang sama — keduanya sticky satu-arah sesuai barisnya masing-masing (row 1: `top:0`; row 2: `top:var(--tree-header1-h)`), keduanya juga sticky `left:0` untuk kolom. Secara visual identik dengan satu sel menerus, tapi menghindari kombinasi rowspan+sticky-dua-arah yang bermasalah.
2. Selector `.tree-table thead th.col-name` diubah menjadi `.tree-table thead tr th.col-name` (menambahkan elemen `tr`) agar spesifisitasnya menyamai `tr.group-row th` dan menang lewat urutan deklarasi (rule ini ditulis belakangan di file).
3. `border-collapse: collapse` diganti `border-collapse: separate; border-spacing: 0;` sebagai langkah defensif tambahan terhadap bug rendering sticky-cell lain yang umum di Chrome/Safari (perubahan ini sendiri **tidak cukup** untuk memperbaiki bug di atas — perbaikan sesungguhnya ada di poin 1 & 2 — tapi tetap dipertahankan sebagai praktik yang lebih aman untuk tabel sticky).

### Manual Test (Susulan)

1. Reproduksi bug awal dikonfirmasi: sebelum fix, `document.elementFromPoint()` pada koordinat sel nama mengembalikan elemen `<th>TOTAL</th>`, membuktikan sel nama benar-benar tertimpa (bukan sekadar salah lihat visual).
2. Setelah fix: `getComputedStyle` pada sel nama menunjukkan `z-index:8`, `background: rgb(247, 249, 252)` (nilai yang benar, bukan lagi ikut ke `group-row`).
3. Kombinasi scroll horizontal (`scrollLeft=350`) **dan** vertikal (`scrollTop=200`) sekaligus diuji — kedua baris header maupun kolom nama tetap tersusun benar (row 1 sejajar TOTAL/MA, row 2 kosong sejajar metrik, teks tidak terpotong/tertimpa).
4. Regresi: `php -l` & `node --check` tetap bersih; tampilan tree pada scroll posisi awal (0,0) tidak berubah dari sebelumnya.
