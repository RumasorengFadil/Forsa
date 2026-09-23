# Dashboard Tahap 2 — Pemisahan Jenjang Jabatan

## Summary

Menambahkan pemisah visual (border) yang jelas antar kelompok kolom jenjang jabatan pada tabel drill-down: `TOTAL | MA | MM | MD | SR. SPECIALIST | SPECIALIST | GEN 1-3 | SR. EXPERT | EXPERT | JR. EXPERT`. Sebelumnya pemisah hanya ada di baris header teratas (baris label jenjang, karena tiap grup adalah satu `<th colspan="6">`); baris header metrik (FTK/Organik/Tugas Karya/Pihak Ketiga/Total Real./Sisa) dan seluruh baris body (angka aktual) sama sekali tidak punya penanda batas antar grup, sehingga saat melihat 60 kolom angka sulit menentukan kolom mana milik jenjang mana.

## Root Cause / Gap

- Header baris 1 (`tr.group-row`): sudah punya `border-left` per grup (colspan=6 per grup, jadi border otomatis jatuh di kolom pertama tiap grup).
- Header baris 2 (sub-header metrik) dan seluruh baris body: setiap kolom adalah `<th>`/`<td>` individual tanpa penanda grup apa pun — tidak ada border, class, atau `data-*` yang menunjukkan "ini kolom pertama dari grup baru".

## Fix

Karena setiap grup jenjang **selalu** persis 6 kolom metrik, kolom pertama tiap grup selalu jatuh di posisi anak ke-`6n+2` di dalam barisnya (posisi 1 adalah kolom nama organisasi). Ini dimanfaatkan dengan selector CSS `:nth-child(6n+2)` — tidak perlu perubahan JS/markup sama sekali:

```css
.tree-table thead tr:not(.group-row) th:nth-child(6n+2),
.tree-table tbody td:nth-child(6n+2) {
    border-left: 2px solid var(--border);
}
```

Warna dan lebar border (`2px solid var(--border)`) disamakan dengan border-left milik header baris 1 (sebelumnya `1px solid #dfe5ef`, dinaikkan ke `2px solid var(--border)` — `var(--border)` = `#e3e8f0`, praktis warna yang sama, jadi tidak menambah warna baru ke palet, hanya menyamakan token) agar ketiga layer (header jenjang, header metrik, body) memakai satu garis pemisah yang konsisten dan lebih tebal dari garis antar-kolom/baris biasa (1px `#f0f2f7`).

## Files Changed

- [public/assets/css/forsa.css](../../../../public/assets/css/forsa.css):
  - `.tree-table thead tr.group-row th`: border-left dinaikkan dari `1px solid #dfe5ef` ke `2px solid var(--border)`.
  - Rule baru: `.tree-table thead tr:not(.group-row) th:nth-child(6n+2), .tree-table tbody td:nth-child(6n+2)`.

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None — perubahan CSS murni, tidak menyentuh markup/JS.

## Documentation Updated

- Laporan ini.

## Tests Performed

- `php -l` seluruh file PHP — tanpa error.
- `node --check` pada `dashboard.js` dan `users.js` — tanpa error (tidak ada perubahan JS di tahap ini, dicek untuk memastikan tidak ada regresi tak sengaja).

## Manual Test

Di Browser pane (`http://localhost:8888/Forsa/dashboard`, hard refresh, snapshot PLN NP):

1. Scroll horizontal ke posisi awal → terlihat garis pemisah tegas tepat sebelum kolom "FTK" pertama grup "MA" (setelah kolom "SISA" milik grup TOTAL), sejajar dengan tepi kiri header "MA".
2. Scroll horizontal lebih jauh → garis pemisah berikutnya muncul konsisten di setiap batas grup (antara MA→MM, dst.), selalu sejajar antara header baris 1, header baris 2, dan seluruh baris body.
3. Regresi Tahap 1 (expand/collapse setelah ganti periode): tombol "+" pada "PLN NP" tetap berhasil menampilkan UI/UL/UP.
4. Regresi Tahap 5 (sticky header dua-layer, dari sesi sebelumnya): jarak vertikal antara baris header jenjang dan baris header metrik tetap presisi `0px` (`getBoundingClientRect()`), tidak terpengaruh oleh penambahan border.

## Known Limitations

None.

## Update — Penguatan Garis Pemisah Mengikuti Referensi `pln_orbit_master` (`modules/ftk/ftk_tree.php`)

User meminta agar garis pemisah "ditarik lurus ke bawah... sampai ke nilai pada tabel, seperti yang ada pada orbit" (awalnya salah saya kira merujuk ke `ORBIT GeoMutasi`; dikoreksi user ke `pln_orbit_master` bagian `ftk_tree`). Dicek langsung `modules/ftk/ftk_tree.php` di `pln_orbit_master` (dan `pln_orbit`, isinya identik): halaman itu memakai teknik yang sama persis (`border-left` berbasis `:nth-child` per grup kolom), tapi dengan bobot visual lebih tegas — `3px` (bukan `2px`), memakai `!important` supaya tidak bisa tertimpa rule lain, dan ditambah `box-shadow: inset ... ` untuk kesan kedalaman (garis terlihat sedikit "terukir", bukan garis datar tipis biasa).

### Perubahan

`public/assets/css/forsa.css`:
- Border grup dinaikkan dari `2px solid var(--border)` → `3px solid rgba(86, 99, 122, .35)` (rgba dari `--ink-soft`, bukan warna baru — tetap dalam palet netral proyek, hanya lebih pekat daripada `var(--border)` yang sangat tipis) di tiga tempat: header baris jenjang (`tr.group-row th`), header baris metrik, dan seluruh `tbody td` pada posisi `:nth-child(6n+2)`.
- Ditambahkan `box-shadow: inset 2px 0 0 rgba(86, 99, 122, .12)` pada header metrik + body untuk kedalaman tipis, meniru teknik ORBIT tapi dengan opacity yang jauh lebih halus (ORBIT memakai warna biru terang di atas header gradien gelap; di sini dasarnya putih/terang jadi opacity diturunkan agar tidak berlebihan).
- Ditambahkan `!important` pada border grup di body/header metrik, mengikuti pola ORBIT — mencegah rule lain (mis. status/hover di masa depan) tanpa sengaja menghapus garis pemisah.

### Manual Test (Susulan)

1. Scroll horizontal ke batas grup TOTAL→MA → garis pemisah baru terlihat jelas lebih tebal (3px) dengan sedikit bayangan, dibanding versi sebelumnya (2px polos).
2. Tree di-expand (PLN NP → UI/UL/UP) lalu discroll horizontal bersamaan → garis tetap lurus tidak terputus dari header sampai baris UP (baris child terakhir), persis seperti yang diminta.
3. Sticky header (Tahap 5) diverifikasi ulang: jarak antara baris header jenjang dan header metrik tetap presisi `0px` — perubahan border/shadow tidak memengaruhi tata letak sticky.
4. `php -l` dan `node --check` tetap bersih.

## Update Kedua — Spesifikasi Lengkap: Border Internal Tipis + Border Ganda per Batas Grup

User memberi spesifikasi lebih rinci: border antar subkolom **dalam** satu grup harus tetap tipis (1px), border **antar grup** harus 3–4px dengan warna biru-keabuan lebih gelap dari grid biasa, diterapkan sebagai `border-right` pada kolom `Sisa` (penutup grup) **dan** `border-left` pada kolom pertama grup berikutnya (pembuka grup) — sesuai notasi `||` (garis ganda) pada spesifikasi.

### Perubahan

`public/assets/css/forsa.css`:
- Token warna baru di `:root`: `--group-sep: rgba(86, 99, 122, .55)` dan `--group-sep-shadow: rgba(86, 99, 122, .14)` — satu sumber warna dipakai konsisten di semua rule pemisah grup (sebelumnya warna di-hardcode inline berulang di tiap rule).
- **Border internal tipis** (baru): `.tree-table thead tr:not(.group-row) th:not(.col-name), .tree-table tbody td:not(.col-name) { border-left: 1px solid var(--border); }` — default tipis untuk semua subkolom metrik (FTK/Organik/Tugas Karya/Pihak Ketiga/Total Real./Sisa).
- **Border tebal pembuka grup** (kolom pertama tiap grup, `:nth-child(6n+2)`): `border-left: 3px solid var(--group-sep) !important` — menimpa border tipis default di atas.
- **Border tebal penutup grup** (baru, kolom `Sisa` tiap grup, `:nth-child(6n+7)`): `border-right: 3px solid var(--group-sep) !important`.
- Header baris jenjang (`tr.group-row th`, satu `<th colspan="6">` per grup): sekarang mendapat `border-left` **dan** `border-right` sekaligus (sebelumnya cuma `border-left`), sehingga setiap grup di header tampak "berbingkai" di kedua sisi, konsisten dengan body.

Hasilnya: setiap batas grup mendapat DUA garis tebal bersisian (border-right grup sebelumnya + border-left grup berikutnya) persis seperti notasi `||` pada spesifikasi, sementara subkolom di dalam grup yang sama hanya dipisahkan garis 1px standar.

### Kendala Verifikasi & Cara Mengatasinya

Setelah mengubah CSS, verifikasi awal di Browser pane menunjukkan `getComputedStyle` mengembalikan `0px` untuk border baru meski file di disk dan respons server (dicek via `curl`) sudah benar — ternyata tab browser preview menahan cache lama untuk `forsa.css` meski sudah di-hard-refresh (`Cmd+Shift+R`) dan tab ditutup/dibuka ulang. Dikonfirmasi dengan `fetch(url, {cache:'no-store'})` yang berhasil mengambil versi terbaru, lalu isi itu disuntikkan manual menggantikan `<link>` lama untuk keperluan verifikasi. Ini murni kendala tooling pratinjau otomatis, bukan bug aplikasi — pada browser pengguna sungguhan, hard-refresh biasa/normal seharusnya cukup.

### Manual Test (Update Kedua)

1. `getComputedStyle` pada kolom "Sisa" grup TOTAL → `border-right: 3px rgba(86, 99, 122, 0.55)`; kolom "FTK" grup MA (kolom setelahnya) → `border-left: 3px rgba(86, 99, 122, 0.55)` — dua garis tebal bersisian tepat di batas grup, sesuai spesifikasi.
2. Kolom "Organik" (subkolom kedua dalam grup MA, bukan batas grup) → `border-left: 1px` — border internal tetap tipis.
3. Diverifikasi juga pada header baris jenjang (`tr.group-row th`) dan header baris metrik — keduanya `3px` di batas grup yang sama, sejajar sempurna dengan body.
4. Regresi sticky header (Tahap 5): jarak vertikal header tetap `0px`.
5. `php -l` dan `node --check` tetap bersih.
