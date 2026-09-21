# Fitur: Dashboard

Implementasi: `modules/dashboard/dashboard.php`, `dashboard_api.php`, `history_api.php`, `DashboardService.php`, `public/assets/js/dashboard.js`.

## Selection Mode

- `period:<YYYY-MM-DD>` — mode gabungan: mengambil seluruh snapshot **aktif** pada periode tersebut per SH/AP.
- `snapshot:<uuid>` — mode inspeksi histori: menampilkan satu snapshot tertentu (SH/AP + periode + revisi) apa adanya, termasuk revisi non-aktif.
- default (tanpa parameter) → periode terbaru yang memiliki snapshot aktif.

## KPI (PRD §11)

- Total FTK = `SUM(ftk)` snapshot terpilih.
- Total Realisasi = `SUM(total_realisasi)`.
- Pemenuhan = Realisasi / FTK × 100 (0 jika FTK=0, tanpa division-by-zero).
- Gap Dashboard = Realisasi − FTK (beda arah dengan kolom `Sisa` pada tabel drill-down, yang memakai FTK − Realisasi — lihat catatan PRD §11.4).

## Status Prioritas & Gap Terbesar

Threshold dikonfigurasi di `config/ftk_rules.php` (`fulfillment_thresholds`), bukan hardcode di query, agar dapat diubah tanpa menyentuh service.

## Management Insight

Rule-based (`DashboardService::buildInsight`): 3 jenjang dengan `belum_dipenuhi` terbesar + SH/AP dengan gap absolut terbesar, dihitung ulang setiap kali snapshot terpilih berubah.

## Empty State & Coverage

Bila periode belum punya snapshot sama sekali → tampilkan empty state + tombol upload. Bila sebagian SH/AP sudah upload, KPI dihitung hanya dari SH/AP yang tersedia (lihat `coverage.available` pada payload API).
