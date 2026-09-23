# Dashboard Tahap 2 — Garis Pemisah Jenjang Tersambung Penuh (Hilangkan Garis Ganda)

## Summary

User melaporkan garis pemisah antar grup jenjang (TOTAL/MA/MM/...) masih terlihat "terputus-putus" meski secara struktural sudah terverifikasi menjangkau seluruh level. Root cause sebenarnya: implementasi sebelumnya memasang **dua** border tebal bersisian di setiap batas grup (`border-right` pada kolom "Sisa" penutup grup + `border-left` pada kolom pertama grup berikutnya), dengan efek bayangan (`box-shadow`) hanya di salah satu sisi — kombinasi ini membuat batas terlihat tidak rata/tidak solid, bukan satu garis tunggal yang bersih. Diperbaiki dengan menyederhanakan menjadi **satu** border saja per batas grup.

## Root Cause

- Border ganda (border-right grup keluar + border-left grup masuk) yang bersisian langsung (berkat `border-spacing:0`) seharusnya terlihat seperti satu pita tebal solid — tapi karena `box-shadow: inset` hanya diterapkan pada salah satu sisi (border-left grup masuk), dua garis yang bersisian itu punya "kedalaman" visual berbeda, menciptakan kesan tidak menyatu/putus-putus alih-alih satu garis tegas.
- Ini murni masalah presentasi (CSS), bukan masalah struktur — data, kolom, dan level hierarki tidak berubah sama sekali.

## Fix

`public/assets/css/forsa.css`:
- `border-right` pada `:nth-child(6n+7)` (kolom Sisa, penutup grup) **dihapus** sepenuhnya — baik untuk header baris jenjang (`tr.group-row th`), header baris metrik, maupun body.
- Disisakan **hanya** `border-left: 3px solid var(--group-sep)` pada `:nth-child(6n+2)` (kolom pertama tiap grup, FTK) — satu sumber kebenaran per batas grup, di semua layer (header jenjang, header metrik, body), di semua kedalaman baris (root SH/AP, org level 2/3, hingga leaf jabatan).
- Border internal antar subkolom dalam grup yang sama tetap `1px` seperti sebelumnya (tidak berubah).

Karena hanya ada SATU deklarasi border per batas grup (bukan dua yang harus "bertemu" dengan tampilan konsisten), garis dijamin tersambung solid dari header paling atas sampai baris jabatan paling bawah, di posisi kolom yang sama persis — tidak bergantung pada rowspan/colspan (tabel ini tidak lagi memakai rowspan sejak perbaikan sticky-header sebelumnya), nested row (selector `tbody td` berlaku generik ke semua baris tanpa memandang kedalaman), sticky header (border adalah properti box model biasa, tidak dipengaruhi `position:sticky`), atau child element manapun.

## Files Changed

- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css) — hapus 1 rule (`border-right` di `:nth-child(6n+7)`) dan 1 deklarasi (`border-right` di `tr.group-row th`); komentar diperbarui menjelaskan keputusan "satu garis, bukan dua".

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None — perubahan CSS murni.

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error.

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`, cache-busting `asset_url()` dari perbaikan sebelumnya memastikan versi CSS yang diuji selalu yang terbaru):

1. Drill-down penuh: `PLN NP → UI → KP → "EXPERT — PoG 18/19/20"` (leaf jabatan asli). Diukur `getComputedStyle` pada 4 level (SH/AP root, org level 2 "UI", org level 3 "KP", leaf jabatan) sekaligus untuk batas grup TOTAL→MA — **hasil identik di keempat level**: `border-left` kolom FTK(TOTAL) = `3px`, `border-left` kolom Organik(TOTAL) = `1px` (internal, tipis), `border-right` kolom Sisa(TOTAL) = `0px` (tidak ada lagi border ganda), `border-left` kolom FTK(MA) = `3px`.
2. Header baris jenjang (`tr.group-row`) dan header baris metrik diukur juga — `border-right` pada sel/kolom TOTAL = `0px`, `border-left` pada MA = `3px` — konsisten dengan body, hanya satu sisi yang membawa border.
3. Verifikasi visual (screenshot) dengan scroll horizontal ke batas grup TOTAL→MA sambil tree ter-expand 4 level dalam (PLN NP→UI→KP→leaf) — garis tampak sebagai **satu kolom vertikal utuh** dari header sampai baris jabatan paling bawah, tanpa celah maupun kesan "dobel".
4. Scroll vertikal melewati puluhan baris jabatan (leaf) sambil header tetap sticky di atas — garis pemisah tetap solid tersambung di setiap baris yang lewat, tidak terputus di titik mana pun.
5. Regresi sticky header (Tahap 5): jarak vertikal antara header jenjang dan header metrik tetap presisi `0px`.

## Known Limitations

None.
