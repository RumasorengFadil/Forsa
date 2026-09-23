# Perbaikan UI/UX Dashboard — A1, B1, C1

Menindaklanjuti `docs/reports/2026/09/23/dashboard_analisis_peningkatan_ui_ux.md`, dikerjakan bertahap sesuai permintaan user (bukan sekaligus dalam satu perubahan).

## Tahap 1 — A1: `aria-live` untuk status loading/error dashboard

**Summary:** `#dashboard-content` (satu-satunya elemen yang di-`innerHTML`-replace tiap kali dashboard memuat/gagal/menampilkan data) sekarang diberi `aria-live="polite"`, sehingga perubahan status ("Memuat dashboard…" → data / "Gagal memuat data") diumumkan otomatis ke screen reader tanpa user perlu menavigasi ulang ke sana.

**Files Changed:** [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — tambah atribut `aria-live="polite"` pada `#dashboard-content`.

**Database/API/Architecture Changes:** None.

**Manual Test:** dicek `document.getElementById('dashboard-content').getAttribute('aria-live')` di browser sungguhan → `"polite"`.

---

## Tahap 2 — B1: Tombol Enter di kotak pencarian & filter grade tree

**Summary:** kotak "Cari organisasi / jabatan…" dan "Position Grade" sekarang merespons tombol Enter persis seperti klik "Terapkan" — user tidak perlu lagi menggerakkan mouse ke tombol setelah mengetik.

**Files Changed:** [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js):
- Logic apply filter diekstrak jadi `applyTreeFilters()` (dipakai ulang, bukan duplikasi).
- Listener `keydown` baru didelegasikan ke `document` (bukan ke input langsung, karena kedua input didaur ulang tiap `renderDashboard()` — pola yang sama dengan perbaikan Tahap 1 sebelumnya) yang memanggil `applyTreeFilters()` saat `Enter` ditekan pada `#tree-search` atau `#tree-filter-grade`.

**Database/API/Architecture Changes:** None — memanggil filter/fetch yang sama persis dengan tombol "Terapkan", tidak ada logic baru.

**Manual Test:** ketik "PLN NP" di kotak pencarian lalu tekan Enter (didispatch sebagai `KeyboardEvent('keydown', {key:'Enter'})` sungguhan) → request `ftk_tree_api.php?...&search=PLN+NP&...` terkirim, dikonfirmasi lewat network log.

---

## Tahap 3 — C1: Sinkronisasi periode & filter tree ke URL

**Summary:** periode/histori snapshot yang dipilih dan seluruh filter tree (pencarian, jenjang, position grade, status gap) sekarang tercermin di query string URL (`?selection=...&q=...&level=...&grade=...&gap=...`) lewat `history.replaceState()`. Refresh halaman atau membagikan link sekarang mempertahankan tampilan yang sama, bukan selalu kembali ke periode terbaru default.

**Files Changed:** [public/assets/js/dashboard.js](../../../../public/assets/js/dashboard.js):
- `readUrlState()` (baru) — parse `window.location.search` jadi objek `{selection, search, job_level_group, position_grade, gap_status}`.
- `syncUrlState()` (baru) — tulis balik state saat ini ke URL via `history.replaceState` (bukan `pushState`, supaya tombol Back browser tidak dipenuhi riwayat filter).
- `treeFilters` sekarang di-seed dari `readUrlState()` saat pertama kali dideklarasikan, bukan selalu string kosong.
- `loadHistoryOptions()`: kalau URL punya `selection` yang valid (cocok salah satu opsi dropdown histori), dipakai sebagai `currentSelection` awal alih-alih default periode terbaru; `syncUrlState()` dipanggil setelah dropdown berubah.
- `applyTreeFilters()` memanggil `syncUrlState()` setiap kali filter diterapkan.
- `restoreTreeFilterInputs()` (baru, dipanggil di `loadDashboard()` setelah `populateTreeLevelFilterOptions()`) — mengisi ulang value input/select filter tree dari `treeFilters`, karena markup toolbar filter didaur ulang kosong tiap kali dashboard re-render (period/history switch). **Efek samping yang diinginkan:** ini juga memperbaiki inkonsistensi lama — sebelumnya, setelah ganti periode, kotak filter tampil kosong secara visual padahal `treeFilters` yang sesungguhnya dipakai untuk query API masih menyimpan nilai lama (filter tetap ter-apply tapi terlihat seperti sudah ter-reset).

**Database/API/Architecture Changes:** None — murni state sinkronisasi client-side, tidak ada endpoint atau parameter baru; `ftk_tree_api.php`/`dashboard_api.php` menerima parameter yang sama seperti sebelumnya.

**Manual Test:**
1. Buka `/Forsa/dashboard` tanpa query string → dicek `location.href` otomatis jadi `...?selection=period%3A2026-09-01` begitu periode default ter-resolve.
2. Isi pencarian "PLN NP" + klik "Terapkan" → `location.href` jadi `...?selection=period%3A2026-09-01&q=PLN+NP`.
3. Reload persis dengan URL itu → `document.getElementById('tree-search').value` kembali terisi `"PLN NP"`, dan request `ftk_tree_api.php` pertama pada page load sudah membawa `search=PLN+NP` (dicek lewat network log) — bukan menunggu user mengisi ulang manual.

## Documentation Updated
- Laporan ini.

## Tests Performed
- `php -l modules/dashboard/dashboard.php` — tanpa error.
- `node --check public/assets/js/dashboard.js` — tanpa error.
- Ketiga tahap diverifikasi langsung di Browser pane (bukan hanya baca kode), termasuk network request dan `history.replaceState` sungguhan.

## Known Limitations
- Filter `job_level_group`/`gap_status` (dropdown) belum diuji manual di laporan ini secara eksplisit untuk kombinasi URL-restore (hanya `search` yang diuji end-to-end), tapi memakai jalur kode yang identik (`treeFilters` object + `restoreTreeFilterInputs()` generik untuk keempat field), jadi risiko regresi rendah.
- URL state tidak disinkronkan untuk tab "Histori Upload" (halaman/pager/filter SH/AP di tab itu) — di luar cakupan C1 yang secara spesifik menyebut "periode/histori dan filter tree".
