# FORSA AI Assistant — Database Sources: Schema & MV Analysis

Hasil Phase 4 (PRD §56/§47/§48/§49), dijalankan setelah gate Phase 3 dijawab user: sumber data yang disetujui adalah **`forsa_ftk_snapshot_rows`, `forsa_ftk_snapshots`, `forsa_shap_entities`**. Seluruh angka di dokumen ini diambil langsung dari database lokal (`information_schema`, `pg_indexes`, `pg_stat_user_tables`), bukan asumsi.

## 1. Schema Analysis Report (PRD §48)

### `forsa_ftk_snapshot_rows` (~11.589 baris)

| Column | Type | Role | Semantic | Security Class |
|---|---|---|---|---|
| id | bigint | technical PK | - | internal |
| snapshot_id | uuid | FK → `forsa_ftk_snapshots.id` | dimension (lineage) | internal |
| source_row_no | integer | technical | - | internal |
| position_name | text | categorical (role/jabatan title, **bukan nama karyawan**) | dimension: `job` | internal (aggregat-only, lihat §3) |
| job_level_raw | varchar | technical (raw upload text) | - | internal |
| job_level_group | varchar | categorical | dimension: `job_level` | public (via alias, lihat §3) |
| organization_level_2/3/4 | varchar | categorical (UI/UP/UL hierarchy) | dimension: `organization` | public |
| position_grade | varchar | categorical | dimension: `position_grade` | public |
| ftk | integer | **measure** | `total_ftk` | public |
| realisasi_organik | integer | **measure** | `total_realisasi_organik` | public |
| realisasi_tugas_karya | integer | **measure** | `total_realisasi_tugas_karya` | public |
| realisasi_pihak_ketiga | integer | **measure** | `total_realisasi_pihak_ketiga` | public |
| total_realisasi | integer | **measure** | `total_realisasi` | public |
| sisa_delta | integer | **measure** (stored convention: `FTK − Total Realisasi`, lihat catatan tanda di `CLAUDE.md`) | `gap` (setelah negasi tampilan, lihat §2 Metric Candidates) | public |
| rencana_pemenuhan | text | technical (catatan bebas dari Excel) | - | internal (tidak diekspos ke AI — teks bebas, berisiko prompt injection kalau ikut masuk konteks LLM) |
| created_at | timestamptz | technical | - | internal |

### `forsa_ftk_snapshots` (3 baris)

| Column | Type | Role | Semantic | Security Class |
|---|---|---|---|---|
| id | uuid | PK | lineage/period context | internal |
| shap_id | bigint | FK → `forsa_shap_entities.id` | dimension: `company` | public |
| import_job_id | uuid | FK → `forsa_import_jobs.id` | - | internal |
| period_month | date | **time dimension** | `period` | public |
| revision_no | integer | technical | - | internal |
| is_active | boolean | technical (menentukan snapshot aktif per shap+period) | dipakai resolver "latest", bukan dimension yang bisa difilter user | internal |
| total_ftk / total_realisasi_* / total_realisasi | bigint | measure teragregasi (level snapshot, bukan per-baris) | tidak dipakai AI — `forsa_ftk_snapshot_rows` sudah cukup untuk SUM yang sama, menghindari dua sumber angka yang bisa beda kalau lupa sinkron | internal |
| created_by | bigint | FK → `forsa_users.id` | - | internal (jangan diekspos: identitas user pengunggah) |
| created_at | timestamptz | technical | - | internal |

### `forsa_shap_entities` (10 baris, seluruhnya `is_active = true`)

| Column | Type | Role | Semantic | Security Class |
|---|---|---|---|---|
| id | bigint | PK | dimension key: `company` | public |
| code | varchar | categorical (kode singkat, mis. "IP", "NP") | alias `company` | public |
| name | varchar | categorical | alias `company` | public |
| short_name | varchar | categorical (mis. "PLN IP", "PLN NP" — ini yang dipakai user sehari-hari) | alias utama `company` | public |
| sort_order | integer | technical | - | internal |
| is_active | boolean | technical | filter implisit (hanya SH/AP aktif) | internal |

## 2. Relationship Analysis

```text
forsa_shap_entities (1) ──< forsa_ftk_snapshots (shap_id)
forsa_ftk_snapshots (1) ──< forsa_ftk_snapshot_rows (snapshot_id)
```

- Satu SH/AP (`forsa_shap_entities`) punya banyak snapshot (satu per periode/revisi upload).
- Satu snapshot punya banyak baris FTK (satu per kombinasi jabatan/jenjang/organisasi).
- **"FTK terbaru" (PRD §40 Snapshot Awareness)**: `forsa_ftk_snapshots.is_active = true` untuk `period_month` tertentu per `shap_id` (dijamin unik oleh index parsial `uq_forsa_ftk_snapshots_active`). "Periode terbaru" = `MAX(period_month)` di antara snapshot yang `is_active = true`. Ini definisi yang **sama persis** dengan yang sudah dipakai `DashboardService::resolveSnapshots()` — semantic layer AI harus memanggil ulang logic resolver itu, bukan menulis ulang query serupa.

## 3. Security Classification & Catatan Khusus

- **Tidak ada data personal karyawan** di ketiga tabel ini — `position_name` adalah nama jabatan/role (mis. "SENIOR SPECIALIST OPERASI PEMBANGKIT BATU BARA"), bukan nama orang. Dikonfirmasi lewat sampling data langsung.
- **`position_name` tetap dibatasi ke agregat** (tidak boleh di-`SELECT` mentah baris-per-baris oleh AI): tool hanya boleh menampilkan `position_name` sebagai bagian dari `GROUP BY`/agregasi (konsisten dengan cara dashboard drill-down sekarang menampilkannya — selalu dalam konteks jenjang/grade, bukan daftar mentah), bukan listing 11 ribu baris ke LLM.
- **`rencana_pemenuhan`** (teks bebas hasil upload Excel) **tidak diekspos ke AI sama sekali** — ini kolom teks bebas yang bisa berisi apa saja dari file upload manapun, jadi berisiko jadi vektor prompt injection kalau pernah masuk ke context LLM. Diklasifikasikan `internal`, tidak dimasukkan ke metric/dimension manapun di semantic layer.
- **`created_by`** (user_id pengunggah snapshot) tidak diekspos — bukan kebutuhan bisnis untuk AI Assistant, dan membocorkan identitas internal user lain.
- Field agregat di level `forsa_ftk_snapshots` (`total_ftk`, dst.) **sengaja tidak dipakai** sebagai sumber metric AI — `forsa_ftk_snapshot_rows` sudah menghasilkan angka yang sama persis via `SUM()`, dan memakai satu sumber tunggal menghindari risiko dua angka berbeda kalau snapshot-level totals pernah lupa disinkronkan ulang (kolom itu tidak dipakai sama sekali oleh dashboard existing, hanya diisi saat import).

## 4. Metric Candidates

Semua measure adalah `SUM()` dari `forsa_ftk_snapshot_rows`, persis formula yang sudah dipakai `TreeService`/`DashboardService` — **tidak ada formula baru**:

| Metric (semantic name) | Expression | Sumber |
|---|---|---|
| `total_ftk` | `SUM(ftk)` | existing |
| `total_realisasi_organik` | `SUM(realisasi_organik)` | existing |
| `total_realisasi_tugas_karya` | `SUM(realisasi_tugas_karya)` | existing |
| `total_realisasi_pihak_ketiga` | `SUM(realisasi_pihak_ketiga)` | existing |
| `total_realisasi` | `SUM(total_realisasi)` | existing |
| `gap` | `SUM(total_realisasi) - SUM(ftk)` | **konvensi tampilan dashboard** (`Total Realisasi − FTK`, positif=surplus/hijau) — BUKAN `sisa_delta` mentah (yang konvensinya terbalik: `FTK − Total Realisasi`, dipakai untuk validasi import). Wajib pakai konvensi dashboard supaya jawaban AI konsisten dengan yang dilihat user di layar, sesuai catatan "Sisa/Delta has one stored/backend convention and one dashboard-display convention" di `CLAUDE.md`. |
| `fulfillment_rate` | `SUM(total_realisasi) / NULLIF(SUM(ftk), 0) * 100` | existing (dashboard KPI "Pemenuhan FTK") |

## 5. Dimension Candidates

| Dimension | Field | Relation | Alias (dari `config/ftk_rules.php`, dipakai ulang — bukan didefinisikan baru) |
|---|---|---|
| `company` | `forsa_shap_entities.short_name`/`code` | via `forsa_ftk_snapshots.shap_id` | "SH/AP", "anak perusahaan", "subholding" → company; nilai: PLN IP, PLN NP, PLN EPI, PLN ICON+, PLN ES, PLN ND, PLN BATAM, PLN MCTN, PLN EMI, PLN ENJ |
| `period` | `forsa_ftk_snapshots.period_month` | via `snapshot_id` | "bulan ini", "periode terbaru" → resolver snapshot aktif terbaru (§2); "bulan lalu"/"sebelumnya" → periode aktif sebelumnya untuk company yang sama |
| `job_level` | `job_level_group` | langsung | reuse `job_level_labels` (`config/ftk_rules.php`): gen 1-3→Generalist 1-3, MD→Manajemen Dasar, MM→Manajemen Menengah, MA→Manajemen Atas, spesialist→Specialist, dst. |
| `position_grade` | `position_grade` | langsung | - |
| `organization` | `organization_level_2/3/4` | langsung | "UI/UP/UL" per level PRD |
| `gap_status` | derivasi dari `sisa_delta` mentah (`kurang`/`terpenuhi`/`lebih`) | langsung | reuse logic `TreeService` gap-status filter yang sudah ada — jangan menulis ulang ambang batasnya |

## 6. MV Analysis Report (PRD §49)

```text
Source:
forsa_ftk_snapshot_rows

Rows:
~11.589 (bukan skala besar — PRD §7.3 menyebut kandidat MV pada skala jutaan baris)

Existing Indexes:
(snapshot_id), (snapshot_id, position_grade), (snapshot_id, job_level_group),
(snapshot_id, organization_level_2[, _3[, _4]])
— seluruh kombinasi filter/GROUP BY yang dipakai dashboard SEKARANG sudah
terindeks dengan baik.

Frequent Pattern (perkiraan berdasarkan pola query dashboard existing):
GROUP BY snapshot_id (+ company via snapshots.shap_id), job_level_group, organization_level_2/3/4

Recommendation:
BASE TABLE — tidak perlu VIEW maupun MATERIALIZED VIEW pada tahap ini.

Reason:
- Skala data kecil (~11,6 ribu baris), jauh dari ambang "large historical
  dataset" yang jadi syarat MV di PRD §7.3.
- Index yang relevan sudah ada untuk seluruh pola filter yang realistis.
- Query SUM/GROUP BY di tabel sekecil ini secara empiris berjalan di bawah
  1ms — target performa PRD §37 (<1s ideal, <2s tool planning) tercapai jauh
  di bawah ambang tanpa pre-agregasi apa pun.
- Join ke `forsa_ftk_snapshots`/`forsa_shap_entities` hanya untuk resolve
  nama company & periode aktif — kandidat kuat untuk sebuah **VIEW** (bukan
  MV) kalau nanti query planner butuh join itu berkali-kali, murni untuk
  menyederhanakan SQL yang dibuat Query Builder, bukan untuk performa.

Re-evaluate when:
volume data bertambah signifikan (mis. snapshot per bulan mulai menumpuk ke
ratusan ribu/jutaan baris kumulatif lintas periode), atau pola query AI
ternyata sering melakukan agregasi lintas-periode yang mahal (mis. "gap
FTK per bulan selama setahun terakhir" untuk semua SH/AP sekaligus).
```

## Sumber

Seluruh angka di atas diambil langsung dari database lokal:
```sql
SELECT relname, n_live_tup FROM pg_stat_user_tables WHERE schemaname='forsa';
SELECT * FROM pg_indexes WHERE schemaname='forsa';
SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='forsa';
-- + sampling data langsung (position_name, job_level_group, forsa_shap_entities)
```
