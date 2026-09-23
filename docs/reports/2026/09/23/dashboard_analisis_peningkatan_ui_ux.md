# Analisis Peningkatan UI/UX — Halaman Dashboard

URL dianalisis: `http://localhost:8888/Forsa/dashboard`
Metode: baca `modules/dashboard/dashboard.php`, `public/assets/js/dashboard.js`, `public/assets/css/forsa.css`; cek langsung di Browser pane; cross-check terhadap [Vercel Web Interface Guidelines](https://github.com/vercel-labs/web-interface-guidelines) (bagian yang relevan untuk aplikasi PHP+vanilla JS, di luar bagian khusus React/Next.js) plus penilaian UX umum untuk dashboard data-berat.

**Ini laporan analisis, belum ada kode yang diubah.** Setiap butir mengutip lokasi `file:line`, kenapa itu masalah/peluang, dan saran perbaikan singkat. Prioritas: **Tinggi** = berdampak nyata ke pengguna sehari-hari, **Sedang** = peningkatan berarti tapi bukan blocker, **Rendah** = polish kecil.

---

## A. Feedback Sistem & Aksesibilitas

### A1. [Tinggi] Update async (loading/gagal/hasil) tidak diumumkan ke screen reader
**Lokasi:** `public/assets/js/dashboard.js:82,87,99` — `content.innerHTML = renderKpiSkeleton()`, pesan "Gagal memuat data", empty state "Belum ada data…" semuanya mengganti `innerHTML` `#dashboard-content` secara diam-diam.
**Masalah:** tidak ada satupun elemen `aria-live` di seluruh halaman (`grep aria-live` = kosong). Untuk pengguna screen reader, pergantian periode/histori yang memicu re-fetch data tidak diumumkan sama sekali — mereka tidak tahu halaman sedang memuat, sudah selesai, atau gagal, kecuali menavigasi ulang secara manual ke elemen tersebut.
**Saran:** tambahkan `aria-live="polite"` pada `#dashboard-content` (atau wrapper status kecil terpisah di atasnya) supaya perubahan status "Memuat…" → data / error diumumkan otomatis.

### A2. [Sedang] Modal upload bisa ditutup tanpa peringatan meski sudah ada progres
**Lokasi:** `public/assets/js/dashboard.js:885` (`closeUploadModal`) dipanggil langsung dari tombol "×"/"Batal" (baris 906) dan `Escape` (baris 908-911) — tanpa pengecekan apa pun.
**Masalah:** user yang sudah pilih SH/AP + periode + upload file (bahkan sudah sampai step 2 "Preview/Validasi") bisa kehilangan semua input hanya dengan menekan Escape atau tombol × secara tidak sengaja — tidak ada konfirmasi "yakin batalkan?".
**Saran:** kalau `uploadToken` sudah terisi (artinya sudah lewat validasi) atau file sudah dipilih, tampilkan konfirmasi singkat sebelum benar-benar menutup modal. (Catatan baik: klik di area backdrop gelap TIDAK menutup modal — sudah benar, hanya tombol eksplisit dan Escape yang perlu pengaman ini.)

### A3. [Rendah] Nilai besar di KPI card tidak pakai `tabular-nums`
**Lokasi:** `public/assets/css/forsa.css:103` — `.kpi-card .kpi-value { font-size: 26px; font-weight: 800; }`.
**Masalah:** kolom tabel tree sudah pakai `font-variant-numeric: tabular-nums` (baris 285, 290) supaya digit sejajar, tapi angka besar di 4 KPI card ("5.652", "4.702", dst) belum — tidak fatal karena bukan kolom perbandingan berdampingan, tapi konsisten lebih rapi.
**Saran:** tambahkan `font-variant-numeric: tabular-nums;` ke `.kpi-card .kpi-value`.

---

## B. Efisiensi Pencarian & Filter Tree

### B1. [Tinggi] Kotak pencarian & filter grade tidak merespons tombol Enter
**Lokasi:** `public/assets/js/dashboard.js:241,243` (input `#tree-search`, `#tree-filter-grade`), diterapkan hanya lewat klik tombol "Terapkan" (baris 250, listener baris 515-521).
**Masalah:** kebiasaan universal user di kotak pencarian adalah mengetik lalu menekan Enter. Saat ini itu tidak melakukan apa-apa — user harus menggerakkan mouse ke tombol "Terapkan" setiap kali, padahal keyboard sudah di tangan mereka.
**Saran:** tambahkan listener `keydown` (Enter) pada kedua input yang men-trigger fungsi yang sama dengan klik "Terapkan" — perubahan kecil, dampak besar untuk kecepatan kerja harian.

### B2. [Sedang] Filter jenjang & status gap butuh klik "Terapkan" terpisah, bukan langsung apply
**Lokasi:** sama seperti B1, `<select id="tree-filter-level">` dan `<select id="tree-filter-gap">` (baris 242, 244-248) juga menunggu "Terapkan", bukan `change` event langsung.
**Masalah:** ini pilihan desain yang valid (menghindari fetch berulang saat user mengetik), tapi untuk dropdown (bukan input teks), user secara natural mengharapkan hasil langsung berubah begitu memilih opsi — pola dropdown-lalu-tombol-terpisah terasa seperti langkah ekstra yang tidak perlu.
**Saran (opsional, bukan wajib):** terapkan filter otomatis begitu `<select>` berubah (`change` event), sisakan tombol "Terapkan" hanya untuk kombinasi pencarian teks + grade (yang memang lebih baik di-debounce agar tidak fetch tiap ketikan).

---

## C. Navigasi & State di URL

### C1. [Tinggi] Pilihan periode/histori dan semua filter tree tidak tercermin di URL
**Lokasi:** `public/assets/js/dashboard.js:28` — `let currentSelection = null;` (variabel JS murni, tidak ada `history.pushState`/`URLSearchParams` di seluruh file); begitu juga `treeFilters` (baris 267-ish) hanya disimpan di memori.
**Masalah:**
1. Refresh halaman selalu kembali ke periode terbaru default — kalau user sedang meninjau histori bulan lalu lalu tidak sengaja refresh (atau membagikan URL ke rekan kerja untuk "lihat data bulan Agustus"), state itu hilang total.
2. Filter tree yang sedang diterapkan (pencarian, jenjang, grade, status gap) juga hilang saat refresh — padahal ini dashboard yang sering dipakai untuk analisis berulang ("cek SH/AP mana yang <90%, filter jenjang X").
**Saran:** sinkronkan `currentSelection` dan `treeFilters` ke query string (`?selection=snapshot:<uuid>&search=...&level=...`) via `history.replaceState()` setiap kali berubah, dan baca query string itu saat halaman pertama dimuat. Ini murni penambahan sinkronisasi state, tidak menyentuh logic drill-down atau agregasi yang sudah ada.

---

## D. Fitur yang Mungkin Dibutuhkan (belum ada sama sekali)

### D1. [Sedang] Tidak ada cara ekspor hasil drill-down tree
**Pengamatan:** di-grep seluruh `dashboard.js`/`dashboard.php` — tidak ada tombol/endpoint export/download (selain upload file .xlsx masuk, tidak ada jalur keluar). Untuk dashboard workforce monitoring yang datanya dipakai laporan manajemen ("Executive Insight", "Status Prioritas Pemenuhan" sudah eksplisit ditujukan untuk level manajemen), kebutuhan umum berikutnya biasanya "export tabel yang sedang difilter ini ke Excel untuk lampiran rapat".
**Saran (fitur baru, perlu dikonfirmasi dulu ke user sebelum dibangun — bukan sekadar UI polish):** tombol "Export ke Excel" pada card drill-down, mengekspor baris yang sedang ter-render (hasil filter aktif) memakai library PhpSpreadsheet yang sudah menjadi dependency project (dipakai `FtkParser` untuk baca file) untuk generate `.xlsx` di sisi server, atau minimal CSV di sisi client dari data yang sudah ada — tanpa perlu perhitungan baru, murni serialisasi data yang sudah difetch.

### D2. [Rendah] Tidak ada indikator "terakhir diperbarui" untuk histori upload
**Pengamatan:** tab "Histori Upload" menampilkan tabel snapshot tapi halaman dashboard utama tidak menunjukkan kapan data snapshot yang sedang dilihat terakhir kali di-upload/divalidasi (hanya nama periode, misal "September 2026").
**Saran:** tambahkan baris kecil "Diupload {tanggal}, oleh {user}" di bawah `#period-label` (`dashboard.php:33`) — datanya kemungkinan sudah tersedia dari `dashboard_api.php` (snapshot punya `created_at`/`uploaded_by`), tinggal ditampilkan.

---

## E. Sentuhan Mobile

### E1. [Rendah] Modal tidak set `overscroll-behavior: contain`
**Lokasi:** `public/assets/css/forsa.css:324` — `.modal-backdrop`.
**Masalah:** di mobile, scroll di dalam `.modal-body` yang mencapai ujung atas/bawah bisa "bocor" dan ikut men-scroll body halaman di belakangnya (efek rubber-band/bounce yang tidak diinginkan), khususnya pada modal upload yang isinya cukup panjang di step 2 (preview validasi).
**Saran:** tambahkan `overscroll-behavior: contain;` pada `.modal` atau `.modal-body`.

### E2. [Rendah] Tombol-tombol tidak set `touch-action: manipulation`
**Lokasi:** `.btn`, `.tree-toggle`, `.tree-val-btn` di `forsa.css`.
**Masalah:** tanpa `touch-action: manipulation`, sebagian browser mobile menunda registrasi tap ~300ms untuk menunggu kemungkinan double-tap-zoom — terasa sedikit "lag" saat expand/collapse tree berulang kali di HP.
**Saran:** tambahkan `touch-action: manipulation;` secara global ke `button` di reset dasar CSS.

---

## Ringkasan Prioritas

| # | Prioritas | Ringkasan |
|---|---|---|
| A1 | Tinggi | `aria-live` untuk status loading/error dashboard |
| A2 | Sedang | Konfirmasi sebelum menutup modal upload yang sudah ada progres |
| A3 | Rendah | `tabular-nums` di nilai KPI card |
| B1 | Tinggi | Tombol Enter di kotak pencarian/filter grade tree |
| B2 | Sedang | Filter dropdown langsung apply (opsional) |
| C1 | Tinggi | Sinkronkan periode & filter tree ke URL (deep-link, tahan refresh) |
| D1 | Sedang | Fitur export drill-down ke Excel/CSV (fitur baru) |
| D2 | Rendah | Tampilkan kapan data terakhir diupload |
| E1 | Rendah | `overscroll-behavior: contain` pada modal |
| E2 | Rendah | `touch-action: manipulation` pada tombol |

---

## Yang Sudah Baik (tidak masuk temuan)

- Angka sudah diformat locale-aware lewat `toLocaleString('id-ID', …)` (`dashboard.js:2-3`), bukan format hardcode.
- Placeholder pencarian sudah pakai elipsis benar (`…`, bukan `...`) dan teks loading juga ("Memuat…").
- Klik di area backdrop gelap tidak menutup modal secara tidak sengaja (hanya tombol eksplisit + Escape).
- Kolom angka tabel tree sudah `tabular-nums` dan rata kanan.
- Tidak ada `outline: none` tanpa pengganti fokus, tidak ada `transition: all`.

## Langkah Selanjutnya

Beri tahu butir mana (A1-E2) yang ingin dikerjakan. D1 (fitur export) disarankan dikonfirmasi dulu detailnya (format file, kolom apa saja yang perlu masuk) sebelum mulai dibangun karena ini fitur baru, bukan sekadar perbaikan UI.
