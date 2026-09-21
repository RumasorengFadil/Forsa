# PRD — FORSA
## Formasi & Realisasi SH/AP

**Nama sistem:** FORSA — Formasi & Realisasi SH/AP  
**Versi dokumen:** 1.0  
**Status:** Draft untuk implementasi  
**Stack utama:** PHP Native + PostgreSQL  
**Referensi UI:** FTK Workforce Monitoring Dashboard pada attachment  
**Referensi data:** `Masking data FTK (1) - PoG fixed - Jenjang mapped.xlsx`  
**Referensi pola tree:** halaman `ftk_tree` pada ORBIT  

---

## 1. Ringkasan Produk

FORSA adalah aplikasi monitoring **Formasi Tenaga Kerja (FTK) dan realisasi tenaga kerja pada level Sub Holding dan Anak Perusahaan (SH/AP)**.

Sumber data FORSA **bukan sinkronisasi langsung dari sistem lain pada fase awal**, melainkan file template Excel yang diunggah oleh user. Setiap file merepresentasikan data FTK dan realisasi untuk **satu SH/AP pada satu periode**.

Contoh:

- PT PLN Indonesia Power mengunggah file FTK + realisasi Agustus 2026;
- PT PLN ICON PLUS mengunggah file FTK + realisasi Agustus 2026;
- SH/AP lain mengunggah file masing-masing;
- FORSA menyimpan setiap upload sebagai **snapshot historis**;
- dashboard membaca snapshot tersebut dan menggabungkan data sesuai periode/snapshot yang dipilih.

Sistem menyediakan tiga fungsi utama:

1. **Upload & histori data FTK/realisasi SH/AP.**
2. **Dashboard monitoring** dengan tampilan mengikuti mockup FTK Workforce Monitoring Dashboard.
3. **Drill-down tree FTK** dari SH/AP sampai jabatan dengan agregasi FTK, realisasi, dan sisa/delta.

---

## 2. Tujuan

### 2.1 Tujuan Utama

- Menyediakan satu dashboard terpusat untuk melihat kondisi FTK dan realisasi seluruh SH/AP.
- Menampilkan persentase pemenuhan FTK, gap, prioritas, dan insight manajemen.
- Menyediakan drill-down organisasi dari SH/AP sampai jabatan.
- Menyimpan histori setiap file yang pernah diunggah sehingga data periode/revisi lama tetap dapat dilihat.
- Menjadikan file Excel sebagai **source of truth data snapshot** pada fase awal.
- Menjaga implementasi sederhana namun modular agar mudah dikembangkan.

### 2.2 Non-Goal Fase Awal

Fitur berikut belum menjadi scope MVP:

- integrasi realtime dengan ORBIT/HXMS/ERP;
- workflow approval upload;
- multi-role kompleks;
- edit FTK langsung dari dashboard;
- mutasi pegawai;
- perencanaan workforce otomatis;
- AI generatif untuk insight;
- mobile application native.

---

## 3. Pengguna dan Hak Akses

### 3.1 Role Awal

Fase awal menggunakan satu role:

**SUPER_ADMIN**

Hak akses:

- login/logout;
- melihat seluruh dashboard;
- memilih histori data;
- upload data SH/AP;
- melihat hasil validasi upload;
- melihat drill-down FTK;
- mengelola user sederhana;
- mengaktifkan/nonaktifkan user;
- reset password user;
- melihat histori upload.

> Struktur role tetap dibuat extensible agar role lain dapat ditambahkan di fase berikutnya tanpa redesign tabel user.

---

## 4. Halaman Aplikasi

Scope halaman MVP:

1. **Login**
2. **Dashboard FORSA**
3. **Upload Realisasi SH/AP**
4. **Histori Upload**
5. **FTK Drill-down** — berada pada bagian bawah dashboard dan dapat diperluas.
6. **Manajemen User**

Navigasi utama disarankan:

```text
FORSA
├── Dashboard
├── Data SH/AP
│   ├── Upload Data
│   └── Histori Upload
└── Administrasi
    └── Manajemen User
```

---

# 5. Sumber Data Upload

## 5.1 Format Referensi Template

Template referensi yang digunakan memiliki struktur berikut:

| Kolom | Field FORSA | Keterangan |
|---|---|---|
| NO. | `row_no` | Nomor urut sumber |
| SEBUTAN JABATAN | `position_name` | Nama/sebutan jabatan |
| JENJANG JABATAN | `job_level_raw` | Jenjang jabatan dari file |
| UI/UP/UL | `organization_level_2` | Organisasi Level 2 |
| UNIT PELAKSANA/KANTOR PUSAT | `organization_level_3` | Organisasi Level 3 |
| UNIT LAYANAN | `organization_level_4` | Organisasi Level 4 |
| POSITION GRADE(PoG) | `position_grade` | PoG/grade jabatan |
| FTK | `ftk` | Formasi tenaga kerja |
| Realisasi → Organik | `realisasi_organik` | Realisasi pegawai organik |
| Realisasi → Tugas Karya | `realisasi_tugas_karya` | Realisasi tugas karya |
| Realisasi → Pihak Ketiga | `realisasi_pihak_ketiga` | Realisasi pihak ketiga |
| Total Realisasi | `total_realisasi` | Total seluruh realisasi |
| Sisa | `sisa_delta` | FTK dikurangi total realisasi |
| Rencana Pemenuhan | `rencana_pemenuhan` | Informasi rencana pemenuhan |

Template menggunakan header dua tingkat pada bagian **Realisasi**.

### 5.1.1 Formula Normalisasi

FORSA tidak hanya mempercayai angka turunan pada file, tetapi memvalidasi ulang:

```text
Total Realisasi = Organik + Tugas Karya + Pihak Ketiga
Sisa / Delta    = FTK - Total Realisasi
```

Jika nilai `Total Realisasi` atau `Sisa` dalam file berbeda dengan hasil formula, upload diberi warning/error sesuai rule validasi.

---

## 5.2 Metadata Saat Upload

User wajib mengisi:

- **SH/AP**
- **Periode data** — bulan dan tahun
- **File `.xlsx`**
- **Catatan** — opsional

Contoh:

```text
SH/AP        : PT PLN INDONESIA POWER
Periode      : Agustus 2026
File         : FTK_IP_Agustus_2026.xlsx
Catatan      : Revisi realisasi tanggal 21 September
```

Pemilihan SH/AP dibuat eksplisit pada form upload dan **tidak hanya ditebak dari nama file**.

---

# 6. Master SH/AP

Master minimal SH/AP disediakan di database.

Contoh sesuai mockup:

- PLN IP
- PLN NP
- PLN EPI
- PLN ICON+
- PLN ES
- PLN ND
- PLN BATAM
- PLN MCTN
- PLN EMI
- PLN ENJ

Field master:

```text
id
code
name
short_name
sort_order
is_active
created_at
updated_at
```

Master dapat ditambah di fase berikutnya tanpa mengubah struktur snapshot.

---

# 7. Mekanisme Upload dan Snapshot

## 7.1 Prinsip Snapshot

Setiap upload yang berhasil menghasilkan satu **snapshot immutable**.

Artinya:

- data lama tidak ditimpa;
- upload revisi membuat snapshot baru;
- user dapat melihat versi terbaru atau versi lama;
- dashboard default memakai snapshot aktif/terbaru untuk periode yang dipilih.

Contoh:

```text
PT PLN Indonesia Power
└── Agustus 2026
    ├── Rev 1 — 18 Sep 2026 10:30
    ├── Rev 2 — 20 Sep 2026 14:10
    └── Rev 3 — 21 Sep 2026 19:40  ← aktif/terbaru
```

## 7.2 Alur Upload

```text
User
  ↓
Pilih SH/AP + Periode + File
  ↓
Upload
  ↓
Validasi file
  ↓
Parsing header & baris
  ↓
Normalisasi jenjang + organisasi
  ↓
Validasi angka & formula
  ↓
Preview hasil
  ↓
Konfirmasi
  ↓
Simpan Snapshot + Rows
  ↓
Tandai sebagai snapshot terbaru
  ↓
Dashboard membaca snapshot baru
```

## 7.3 Status Import

```text
UPLOADED
VALIDATING
INVALID
READY
IMPORTED
FAILED
ARCHIVED
```

---

# 8. Validasi Upload

## 8.1 Validasi File

- hanya `.xlsx`;
- MIME harus valid;
- batas ukuran dapat dikonfigurasi;
- workbook harus memiliki sheet data yang valid;
- file tidak boleh kosong.

## 8.2 Validasi Header

Header wajib:

- SEBUTAN JABATAN
- JENJANG JABATAN
- UI/UP/UL
- UNIT PELAKSANA/KANTOR PUSAT
- UNIT LAYANAN
- POSITION GRADE(PoG)
- FTK
- Organik
- Tugas Karya
- Pihak Ketiga
- Total Realisasi
- Sisa

`Rencana Pemenuhan` dapat kosong per baris namun kolom tetap dikenali bila tersedia.

## 8.3 Validasi Baris

- `SEBUTAN JABATAN` wajib.
- `FTK` harus numerik dan >= 0.
- Realisasi harus numerik dan >= 0.
- blank numeric diperlakukan sebagai `0` setelah validasi.
- `-`, string kosong, dan whitespace pada organisasi diperlakukan sebagai `NULL`.
- leading/trailing spaces di-trim.
- multiple spaces dinormalisasi.
- perbandingan mapping jenjang case-insensitive.

## 8.4 Preview

Sebelum konfirmasi, tampilkan:

- total rows;
- valid rows;
- warning rows;
- error rows;
- total FTK;
- total realisasi;
- contoh 10–20 baris hasil parsing;
- daftar error beserta nomor baris Excel.

Upload dengan error kritis tidak dapat dikonfirmasi.

---

# 9. Mapping Jenjang Jabatan

Normalisasi dilakukan case-insensitive setelah trim.

| Nilai Sumber | Nilai Hasil Mapping |
|---|---|
| Generalist 1 / GENERALIST 1 | `gen 1-3` |
| Generalist 2 / GENERALIST 2 | `gen 1-3` |
| Generalist 3 / GENERALIST 3 | `gen 1-3` |
| `gen 1-3` | `gen 1-3` |
| Manajemen Dasar / MANAJEMEN DASAR / MD | `MD` |
| Manajemen Menengah / MANAJEMEN MENENGAH / MM | `MM` |
| Manajemen Atas / MANAJEMEN ATAS / MA | `MA` |
| Specialist / SPECIALIST / spesialist | `spesialist` |
| Senior Specialist | `Senior Specialist` |
| Expert | `Expert` |
| Senior Expert | `Senior Expert` |
| Junior Expert | `Junior Expert` |
| kosong | tetap kosong / `NULL` |

### 9.1 Aturan Jenjang Kosong

Jenjang kosong:

- tetap masuk perhitungan **Total**;
- tidak dimasukkan ke grup jenjang tertentu;
- ditandai sebagai warning pada hasil upload;
- dapat difilter untuk audit kualitas data.

---

# 10. Dashboard FORSA

## 10.1 Layout

Dashboard mengikuti struktur visual attachment:

```text
┌──────────────────────────────────────────────────────────────────┐
│ FTK WORKFORCE MONITORING DASHBOARD              [History ▼][Upload]│
├──────────────────────────────────────────────────────────────────┤
│ Total FTK │ Total Realisasi │ Pemenuhan FTK │ Gap FTK │ Insight │
├───────────────────────────────────┬──────────────────────────────┤
│ Sebaran & Pemenuhan per SH/AP     │ Status Prioritas Pemenuhan   │
├───────────────────┬───────────────┼──────────────────────────────┤
│ Gap Terbesar      │ Pemenuhan     │ Management Insight           │
│ per SH/AP         │ per Jenjang   │                              │
├──────────────────────────────────────────────────────────────────┤
│ DRILL-DOWN FTK TREE                                               │
└──────────────────────────────────────────────────────────────────┘
```

UI harus responsif tetapi desktop menjadi prioritas karena tabel drill-down lebar.

---

## 10.2 History Dropdown dan Upload Button

Bagian kanan atas:

```text
[ Realisasi SH/AP • Agustus 2026 ▼ ] [ Upload Realisasi SH/AP ]
```

### History Dropdown

Default:

```text
Realisasi SH/AP • <periode terbaru>
```

Dropdown menampilkan histori periode dan snapshot yang tersedia.

Konsep pilihan:

```text
Agustus 2026 — Terbaru
Juli 2026 — Terbaru
Juni 2026 — Terbaru

Histori Upload
PT PLN Indonesia Power — Agustus 2026 — Rev 3
PT PLN Indonesia Power — Agustus 2026 — Rev 2
PT PLN ICON PLUS — Agustus 2026 — Rev 2
...
```

Perilaku:

- pilihan **periode terbaru** = dashboard menggabungkan snapshot aktif/terbaru setiap SH/AP pada periode tersebut;
- pilihan **snapshot historis tertentu** = dashboard dapat menampilkan snapshot SH/AP tersebut sebagai mode inspeksi histori;
- semua KPI, chart, insight, dan drill-down wajib berubah mengikuti pilihan.

### Upload Button

Tombol `Upload Realisasi SH/AP` diletakkan tepat di kanan history dropdown.

Klik membuka halaman/modal upload dengan field SH/AP, periode, file, dan catatan.

---

# 11. KPI Dashboard

## 11.1 Total FTK

```text
SUM(ftk)
```

## 11.2 Total Realisasi

```text
SUM(realisasi_organik + realisasi_tugas_karya + realisasi_pihak_ketiga)
```

## 11.3 Pemenuhan FTK

```text
Total Realisasi / Total FTK × 100%
```

Jika Total FTK = 0 maka tampil `0%` atau `-` sesuai keputusan UI, tanpa division by zero.

## 11.4 Gap FTK Dashboard

Dashboard mengikuti arah angka pada mockup:

```text
Gap FTK = Total Realisasi - Total FTK
```

Contoh:

```text
FTK             = 12.210
Total Realisasi = 11.880
Gap Dashboard   = -330
```

Interpretasi:

- negatif = masih kurang;
- `0` = terpenuhi;
- positif = realisasi melebihi FTK.

> Catatan: kolom `Sisa/Delta` pada tabel drill-down tetap menggunakan `FTK - Total Realisasi` agar konsisten dengan template upload.

---

# 12. Sebaran & Pemenuhan per SH/AP

Setiap SH/AP memiliki card:

```text
PLN IP
98,0%
Gap -97
```

Per SH/AP dihitung:

```text
FTK SH/AP
Total Realisasi SH/AP
Pemenuhan = Realisasi / FTK × 100%
Gap       = Realisasi - FTK
```

Urutan card mengikuti `sort_order` master SH/AP.

Warna status mengikuti kategori prioritas.

---

# 13. Status Prioritas Pemenuhan

Kategori default mengikuti mockup:

| Pemenuhan | Status |
|---|---|
| `>= 100%` | Terpenuhi/lebih |
| `90% – 99,9%` | Perlu monitoring |
| `< 90%` | Prioritas pemenuhan |

Panel menampilkan:

- range;
- jumlah entitas;
- daftar SH/AP pada kategori tersebut.

Threshold disimpan di konfigurasi bisnis agar dapat diubah tanpa mengubah query utama.

---

# 14. Gap Terbesar per SH/AP

Panel menampilkan SH/AP dengan shortage terbesar.

Sorting:

```text
Gap Dashboard ASC
```

Contoh:

```text
PLN IP      -97
PLN ICON+   -88
PLN EPI     -54
```

Default tampil 5–10 entitas dengan gap paling negatif.

---

# 15. Pemenuhan FTK per Jenjang Jabatan

Jenjang menggunakan hasil mapping pada bagian 9.

Untuk setiap jenjang dihitung:

```text
FTK Jenjang
Realisasi Jenjang
Belum Dipenuhi = MAX(FTK - Realisasi, 0)
Kelebihan      = MAX(Realisasi - FTK, 0)
```

Visual mengikuti mockup horizontal bar.

Daftar jenjang:

1. gen 1-3
2. MD
3. MM
4. MA
5. spesialist
6. Senior Specialist
7. Junior Expert
8. Expert
9. Senior Expert

Jenjang tanpa data tidak wajib ditampilkan, atau dapat ditampilkan bernilai `0` sesuai keputusan final UI.

---

# 16. Management Insight

Management Insight pada MVP **rule-based**, bukan AI.

Contoh rule:

1. cari jenjang dengan `Sisa = FTK - Total Realisasi` terbesar positif;
2. urutkan tiga jenjang teratas;
3. cari SH/AP dengan gap dashboard paling negatif;
4. hasilkan teks ringkas.

Contoh:

```text
01 Generalist 1
Gap jenjang terbesar: 624 posisi.

02 Generalist 3
Gap 226 posisi; prioritas kedua.

03 Generalist 2
Gap 49 posisi.

04 SH/AP
PLN IP memiliki gap absolut terbesar (-97).
```

Insight harus sepenuhnya dihitung dari snapshot yang sedang dipilih.

---

# 17. Drill-down FTK Tree

## 17.1 Prinsip

Bagian paling bawah dashboard berupa tabel tree expandable, mengambil pola UX dari `ftk_tree` ORBIT tetapi disederhanakan untuk struktur SH/AP.

Header utama:

**UNIT / ORGANISASI / JABATAN**

Hierarki:

```text
▾ PT PLN INDONESIA POWER               ← Organisasi Level 1 / SH-AP
  ▾ UI / UP / UL                        ← Organisasi Level 2
    ▾ UNIT PELAKSANA / KANTOR PUSAT     ← Organisasi Level 3
      ▾ UNIT LAYANAN                    ← Organisasi Level 4
        ▸ JABATAN / SEBUTAN JABATAN     ← leaf/node terakhir
```

Contoh leaf:

```text
SENIOR SPECIALIST OPERASI PEMBANGKIT BATU BARA — PoG 18 / 19 / 20
```

## 17.2 Aturan Level Kosong

Node kosong tidak dibuat.

Contoh jika Unit Layanan kosong:

```text
PT PLN INDONESIA POWER
└── UP
    └── UBP TELLO
        └── SENIOR SPECIALIST ...
```

Jabatan otomatis naik dan menjadi child dari organisasi terakhir yang tersedia.

Aturan normalisasi value kosong:

```text
NULL
''
'-'
whitespace-only
```

semuanya dianggap **tidak memiliki node**.

## 17.3 Jabatan sebagai Leaf

Jabatan selalu:

- berada pada node paling bawah;
- tidak expandable;
- tidak mempunyai child;
- dapat menampilkan Position Grade pada label atau kolom detail.

---

# 18. Struktur Kolom Drill-down

Tabel dibuat dengan multi-level grouped header.

## 18.1 Group TOTAL

```text
TOTAL
├── FTK
├── Realisasi Organik
├── Realisasi Tugas Karya
├── Realisasi Pihak Ketiga
├── Total Realisasi
└── Sisa / Delta
```

## 18.2 Group per Jenjang

Setelah TOTAL, gunakan pola yang sama untuk setiap jenjang:

```text
GEN 1-3
├── FTK
├── Organik
├── Tugas Karya
├── Pihak Ketiga
├── Total Realisasi
└── Sisa / Delta

MD
├── FTK
├── Organik
├── Tugas Karya
├── Pihak Ketiga
├── Total Realisasi
└── Sisa / Delta

MM
...
```

Group jenjang:

- GEN 1-3
- MD
- MM
- MA
- SPECIALIST
- SENIOR SPECIALIST
- JUNIOR EXPERT
- EXPERT
- SENIOR EXPERT

### 18.2.1 Sisa / Delta

Di tabel:

```text
Sisa / Delta = FTK - Total Realisasi
```

Interpretasi:

- positif = posisi belum terpenuhi;
- `0` = tepat terpenuhi;
- negatif = realisasi melebihi FTK.

## 18.3 UX Tabel Lebar

Karena jumlah kolom besar:

- horizontal scroll wajib;
- kolom `UNIT / ORGANISASI / JABATAN` sticky kiri;
- header sticky atas;
- group TOTAL dapat dibuat sticky setelah kolom nama bila performa memungkinkan;
- zebra row ringan;
- indentation per level;
- expand/collapse icon konsisten;
- angka rata kanan;
- tooltip untuk header yang dipendekkan;
- tree child dimuat lazy jika dataset besar.

---

# 19. Agregasi Drill-down

Setiap node organisasi adalah agregasi seluruh descendant.

Contoh:

```text
PT PLN INDONESIA POWER = seluruh baris snapshot IP
UP                     = seluruh baris dengan org_level_2 = UP
UBP TELLO               = seluruh baris UP + UBP TELLO
UNIT LAYANAN X          = seluruh baris sampai level 4 tersebut
JABATAN                  = agregasi baris jabatan yang sama pada path tersebut
```

Query agregasi harus selalu dibatasi oleh:

```text
snapshot_id
```

agar data antar periode atau revisi tidak tercampur.

---

# 20. Filter/Pencarian Drill-down

Minimum:

- Search organisasi/jabatan;
- Filter SH/AP;
- Filter jenjang jabatan;
- Filter Position Grade;
- Filter status gap:
  - Kurang;
  - Terpenuhi;
  - Lebih.

Filter mengikuti snapshot/periode dashboard aktif.

---

# 21. Rancangan Database

Disarankan menggunakan schema PostgreSQL:

```text
forsa
```

## 21.1 ERD Konseptual

```text
forsa_roles
     │
     └──< forsa_user_roles >── forsa_users

forsa_users
     │
     └──< forsa_import_jobs

forsa_shap_entities
     │
     └──< forsa_ftk_snapshots
              │
              └──< forsa_ftk_snapshot_rows

forsa_import_jobs
     │
     └──< forsa_import_errors

forsa_users
     │
     └──< forsa_audit_logs
```

---

## 21.2 `forsa_users`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGSERIAL / UUID | PK |
| name | VARCHAR(150) | Nama user |
| email | VARCHAR(255) | Unique |
| password_hash | VARCHAR(255) | Hash password |
| is_active | BOOLEAN | Status akun |
| last_login_at | TIMESTAMPTZ | Login terakhir |
| created_at | TIMESTAMPTZ | Created |
| updated_at | TIMESTAMPTZ | Updated |

Index:

```text
UNIQUE(email)
```

---

## 21.3 `forsa_roles`

| Kolom | Tipe |
|---|---|
| id | SMALLSERIAL |
| code | VARCHAR(50) |
| name | VARCHAR(100) |
| is_active | BOOLEAN |

Seed awal:

```text
SUPER_ADMIN
```

---

## 21.4 `forsa_user_roles`

| Kolom | Tipe |
|---|---|
| user_id | FK → forsa_users |
| role_id | FK → forsa_roles |
| created_at | TIMESTAMPTZ |

Constraint:

```text
PRIMARY KEY(user_id, role_id)
```

---

## 21.5 `forsa_shap_entities`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGSERIAL | PK |
| code | VARCHAR(50) | Kode unique |
| name | VARCHAR(200) | Nama resmi |
| short_name | VARCHAR(100) | Nama dashboard |
| sort_order | INT | Urutan UI |
| is_active | BOOLEAN | Status |
| created_at | TIMESTAMPTZ | |
| updated_at | TIMESTAMPTZ | |

Constraint:

```text
UNIQUE(code)
```

---

## 21.6 `forsa_import_jobs`

Mencatat proses upload/parser.

| Kolom | Tipe |
|---|---|
| id | UUID |
| shap_id | FK |
| period_month | DATE |
| original_filename | VARCHAR(255) |
| stored_filename | VARCHAR(255) |
| stored_path | TEXT |
| file_hash | VARCHAR(64) |
| status | VARCHAR(30) |
| total_rows | INT |
| valid_rows | INT |
| warning_rows | INT |
| error_rows | INT |
| note | TEXT |
| uploaded_by | FK user |
| started_at | TIMESTAMPTZ |
| completed_at | TIMESTAMPTZ |
| created_at | TIMESTAMPTZ |

`period_month` disimpan sebagai tanggal hari pertama bulan, contoh `2026-08-01`.

---

## 21.7 `forsa_import_errors`

| Kolom | Tipe |
|---|---|
| id | BIGSERIAL |
| import_job_id | UUID FK |
| row_number | INT |
| column_name | VARCHAR(150) |
| severity | VARCHAR(20) |
| error_code | VARCHAR(100) |
| message | TEXT |
| raw_value | TEXT |
| created_at | TIMESTAMPTZ |

Severity:

```text
WARNING
ERROR
```

---

## 21.8 `forsa_ftk_snapshots`

Satu record = satu SH/AP + satu periode + satu revisi.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | UUID | PK |
| shap_id | FK | SH/AP |
| import_job_id | UUID FK | sumber upload |
| period_month | DATE | periode |
| revision_no | INT | revisi |
| is_active | BOOLEAN | snapshot aktif untuk SH/AP+periode |
| total_ftk | BIGINT | cache summary |
| total_realisasi_organik | BIGINT | cache |
| total_realisasi_tugas_karya | BIGINT | cache |
| total_realisasi_pihak_ketiga | BIGINT | cache |
| total_realisasi | BIGINT | cache |
| created_by | FK user | |
| created_at | TIMESTAMPTZ | |

Constraint:

```text
UNIQUE(shap_id, period_month, revision_no)
```

Partial unique index disarankan:

```sql
UNIQUE (shap_id, period_month)
WHERE is_active = TRUE
```

Tujuan: hanya satu snapshot aktif untuk satu SH/AP pada satu periode.

---

## 21.9 `forsa_ftk_snapshot_rows`

Tabel utama data detail upload.

| Kolom | Tipe |
|---|---|
| id | BIGSERIAL |
| snapshot_id | UUID FK |
| source_row_no | INT |
| position_name | TEXT |
| job_level_raw | VARCHAR(150) |
| job_level_group | VARCHAR(100) NULL |
| organization_level_2 | VARCHAR(255) NULL |
| organization_level_3 | VARCHAR(255) NULL |
| organization_level_4 | VARCHAR(255) NULL |
| position_grade | VARCHAR(100) NULL |
| ftk | INT |
| realisasi_organik | INT |
| realisasi_tugas_karya | INT |
| realisasi_pihak_ketiga | INT |
| total_realisasi | INT |
| sisa_delta | INT |
| rencana_pemenuhan | TEXT NULL |
| created_at | TIMESTAMPTZ |

### Formula penyimpanan

```text
total_realisasi = organik + tugas_karya + pihak_ketiga
sisa_delta      = ftk - total_realisasi
```

Nilai dapat dihitung saat import dan disimpan untuk mempercepat agregasi; parser tetap memvalidasi terhadap nilai formula pada file.

### Index utama

```text
(snapshot_id)
(snapshot_id, organization_level_2)
(snapshot_id, organization_level_2, organization_level_3)
(snapshot_id, organization_level_2, organization_level_3, organization_level_4)
(snapshot_id, job_level_group)
(snapshot_id, position_grade)
```

Search nama jabatan dapat menggunakan `pg_trgm` pada fase optimasi bila data semakin besar.

---

## 21.10 `forsa_audit_logs`

| Kolom | Tipe |
|---|---|
| id | BIGSERIAL |
| user_id | FK |
| action | VARCHAR(100) |
| entity_type | VARCHAR(100) |
| entity_id | TEXT |
| old_data | JSONB NULL |
| new_data | JSONB NULL |
| ip_address | INET NULL |
| user_agent | TEXT NULL |
| created_at | TIMESTAMPTZ |

Aktivitas minimum yang dicatat:

- login;
- logout;
- upload;
- import berhasil/gagal;
- aktivasi snapshot;
- tambah/edit/nonaktif user;
- reset password.

---

# 22. Keputusan Database untuk Hierarki Organisasi

Pada MVP **tidak diperlukan tabel master organisasi terpisah** untuk UI/UP/UL, Unit Pelaksana/Kantor Pusat, dan Unit Layanan.

Alasannya:

1. seluruh data organisasi berasal dari snapshot Excel;
2. histori harus mempertahankan bentuk organisasi sesuai file pada saat upload;
3. level organisasi pada FORSA sudah fixed menjadi tiga kolom setelah SH/AP;
4. tree dapat dibangun dengan `GROUP BY` terhadap path organisasi pada `forsa_ftk_snapshot_rows`;
5. menghindari masalah snapshot lama berubah karena master organisasi baru diedit.

Struktur tree konseptual:

```text
SH/AP (master)
  → organization_level_2
    → organization_level_3
      → organization_level_4
        → position_name
```

Jika ke depan FORSA membutuhkan master organisasi resmi lintas periode, dapat ditambahkan tabel self-referencing tanpa mengubah format snapshot historis.

---

# 23. Rancangan Arsitektur Aplikasi

Arsitektur mengikuti pola **PHP Native modular berdasarkan domain bisnis**, bukan MVC framework penuh.

Prinsip:

```text
request
  ↓
router / direct module page
  ↓
auth + authorization
  ↓
module
  ↓
query/service helper
  ↓
PostgreSQL
  ↓
render HTML / JSON
```

Setiap file halaman tetap sederhana:

```text
auth check → input validation → query → business rule → render
```

Business rule yang dipakai banyak halaman dipindahkan ke `config/` atau helper bersama agar tidak duplicate.

---

# 24. Struktur Folder FORSA

```text
forsa/
│
├── config/
│   ├── app.php
│   ├── database.example.php
│   ├── database.php
│   ├── auth.php
│   ├── role_access.php
│   ├── upload.php
│   └── ftk_rules.php
│
├── database/
│   ├── migrations/
│   │   ├── 001_create_auth_tables.sql
│   │   ├── 002_create_shap_master.sql
│   │   ├── 003_create_import_tables.sql
│   │   ├── 004_create_ftk_snapshot_tables.sql
│   │   └── 005_create_audit_logs.sql
│   └── seeds/
│       ├── seed_roles.sql
│       └── seed_shap_entities.sql
│
├── modules/
│   ├── auth/
│   │   ├── login.php
│   │   ├── login_submit.php
│   │   └── logout.php
│   │
│   ├── dashboard/
│   │   ├── dashboard.php
│   │   ├── dashboard_api.php
│   │   ├── history_api.php
│   │   └── insight_api.php
│   │
│   ├── import/
│   │   ├── upload.php
│   │   ├── upload_submit.php
│   │   ├── validation.php
│   │   ├── preview.php
│   │   ├── confirm.php
│   │   ├── history.php
│   │   ├── detail.php
│   │   └── errors_api.php
│   │
│   ├── ftk/
│   │   ├── ftk_tree.php
│   │   ├── ftk_tree_api.php
│   │   ├── ftk_search_api.php
│   │   └── ftk_detail.php
│   │
│   └── administrasi/
│       ├── users.php
│       ├── user_create.php
│       ├── user_edit.php
│       ├── user_toggle_status.php
│       └── user_reset_password.php
│
├── shared/
│   ├── menu.php
│   ├── layout.php
│   ├── csrf.php
│   ├── flash_message.php
│   ├── pagination.php
│   ├── validation.php
│   ├── response.php
│   └── audit.php
│
├── tests/
│   ├── ftk_rules_test.php
│   ├── job_level_mapping_test.php
│   ├── import_validation_test.php
│   └── snapshot_aggregation_test.php
│
├── tools/
│   ├── import_ftk_cli.php
│   └── verify_snapshot.php
│
├── automation/
│   └── README.md
│
├── storage/
│   ├── uploads/
│   ├── temp/
│   └── logs/
│
├── docs/
│   ├── README.md
│   ├── product/
│   │   ├── PRD.md
│   │   └── roadmap.md
│   ├── architecture/
│   │   └── overview.md
│   ├── features/
│   │   ├── dashboard/
│   │   ├── import/
│   │   ├── ftk-tree/
│   │   └── user-management/
│   ├── database/
│   │   ├── schema.md
│   │   └── migrations.md
│   ├── security/
│   │   ├── authentication.md
│   │   └── authorization.md
│   ├── deployment/
│   │   ├── installation.md
│   │   ├── upgrade.md
│   │   └── backup-restore.md
│   └── reports/
│       └── YYYY/MM/DD/<perubahan>.md
│
├── vendor/
├── .env.example
├── .htaccess
├── composer.json
├── route_map.php
└── router.php
```

---

# 25. Tanggung Jawab Modul

## 25.1 `modules/dashboard`

- render dashboard;
- mengambil KPI;
- agregasi SH/AP;
- agregasi jenjang;
- status prioritas;
- management insight;
- opsi histori snapshot.

## 25.2 `modules/import`

- upload file;
- validasi template;
- parsing Excel;
- normalisasi data;
- preview;
- simpan snapshot;
- histori upload;
- daftar error.

## 25.3 `modules/ftk`

- expandable tree;
- lazy load child;
- agregasi total;
- agregasi per jenjang;
- pencarian organisasi/jabatan;
- detail leaf jabatan.

## 25.4 `modules/administrasi`

- list user;
- create user;
- edit user;
- aktif/nonaktif;
- reset password.

---

# 26. Routing

Contoh `route_map.php`:

```php
<?php

return [
    'index.php'              => '/modules/auth/login.php',
    'login.php'              => '/modules/auth/login.php',
    'login_submit.php'       => '/modules/auth/login_submit.php',
    'logout.php'             => '/modules/auth/logout.php',

    'dashboard.php'          => '/modules/dashboard/dashboard.php',
    'dashboard_api.php'      => '/modules/dashboard/dashboard_api.php',
    'dashboard_history.php'  => '/modules/dashboard/history_api.php',

    'upload.php'             => '/modules/import/upload.php',
    'upload_submit.php'      => '/modules/import/upload_submit.php',
    'upload_history.php'     => '/modules/import/history.php',

    'ftk_tree.php'           => '/modules/ftk/ftk_tree.php',
    'ftk_tree_api.php'       => '/modules/ftk/ftk_tree_api.php',

    'users.php'              => '/modules/administrasi/users.php',
];
```

---

# 27. Strategi Query Dashboard

Dashboard tidak membaca file Excel secara langsung setiap page load.

Alur yang benar:

```text
Excel
  ↓ sekali saat upload
Parser
  ↓
PostgreSQL snapshot rows
  ↓
SQL aggregate
  ↓
Dashboard
```

Keuntungan:

- dashboard lebih cepat;
- histori aman;
- query/filter mudah;
- file Excel tidak perlu diparsing berulang kali;
- audit lebih jelas.

---

# 28. Strategi Query Tree

Contoh endpoint:

```text
GET ftk_tree_api.php
    ?snapshot_id=<uuid>
    &level=2
    &parent_l2=UP
```

Untuk level berikutnya:

```text
level=3
parent_l2=UP
parent_l3=UBP TELLO
```

Response contoh:

```json
{
  "data": [
    {
      "label": "UBP TELLO",
      "level": 3,
      "has_children": true,
      "total": {
        "ftk": 120,
        "organik": 80,
        "tugas_karya": 10,
        "pihak_ketiga": 20,
        "total_realisasi": 110,
        "sisa_delta": 10
      }
    }
  ]
}
```

Endpoint harus mengembalikan agregasi TOTAL dan agregasi seluruh `job_level_group` yang diperlukan tabel.

---

# 29. Performance Requirement

Target MVP:

- dashboard initial load <= 3 detik pada dataset normal;
- expand tree <= 1 detik untuk response query normal;
- pagination histori/user server-side;
- tree menggunakan lazy loading;
- agregasi tidak melakukan parse Excel saat request;
- index wajib mengikuti snapshot dan hierarchy path;
- transaksi database digunakan saat finalisasi import.

Jika data berkembang besar, optimasi lanjutan:

- summary table/materialized view per snapshot;
- Redis cache;
- background worker untuk import besar;
- `pg_trgm` untuk search;
- precomputed tree aggregation.

---

# 30. Security Requirement

Minimum:

- password menggunakan `password_hash()` / `password_verify()`;
- session ID regenerate setelah login;
- cookie `HttpOnly`, `Secure` di production, `SameSite=Lax/Strict`;
- CSRF token untuk request perubahan data;
- prepared statement PDO;
- output HTML di-escape;
- upload whitelist extension + MIME;
- nama file storage diganti random/UUID;
- file upload tidak dieksekusi sebagai PHP;
- authorization dicek di backend;
- `.env`, database config, dan storage private tidak boleh dapat diakses publik;
- audit log untuk operasi sensitif.

---

# 31. UI/UX Requirement

## Dashboard

- visual mengikuti mockup attachment sedekat mungkin;
- card KPI konsisten;
- hierarki informasi jelas;
- loading skeleton untuk request async;
- empty state jelas bila periode belum memiliki data;
- error state tidak hanya menggunakan `alert()` browser;
- responsive minimum laptop/desktop/tablet landscape.

## Upload

- asterisk pada field wajib;
- inline validation;
- drag & drop opsional;
- progress upload;
- preview sebelum commit;
- error menampilkan nomor row;
- konfirmasi sebelum import final.

## Manajemen User

- search;
- pagination;
- status aktif;
- modal/halaman create-edit sederhana;
- konfirmasi saat nonaktif/reset password.

---

# 32. Empty State

Jika periode belum memiliki data:

```text
Belum ada data Realisasi SH/AP untuk periode ini.
Upload template FTK/Realisasi untuk mulai menampilkan dashboard.

[Upload Realisasi SH/AP]
```

Jika hanya sebagian SH/AP telah upload:

- dashboard menghitung dari SH/AP yang tersedia;
- panel status menunjukkan jumlah entitas yang tersedia;
- tampilkan indikator coverage, contoh `7 dari 10 SH/AP sudah memiliki data`.

---

# 33. Audit dan Histori

Histori upload minimum menampilkan:

| Field | Contoh |
|---|---|
| SH/AP | PT PLN Indonesia Power |
| Periode | Agustus 2026 |
| Revision | Rev 3 |
| File | FTK_IP_Agustus_2026.xlsx |
| Total Row | 3.866 |
| Total FTK | ... |
| Total Realisasi | ... |
| Status | Imported |
| Uploaded By | Admin |
| Uploaded At | 21 Sep 2026 19:40 |
| Active | Ya |

File lama tidak dihapus otomatis.

---

# 34. Aturan Aktivasi Snapshot

Saat snapshot baru berhasil untuk SH/AP + periode yang sama:

```text
snapshot lama.is_active = false
snapshot baru.is_active = true
```

Proses harus dalam satu database transaction.

Jika import gagal:

- snapshot aktif lama tidak berubah;
- dashboard tetap membaca snapshot yang sebelumnya aktif.

---

# 35. Acceptance Criteria

## AC-01 Login

- user aktif dapat login dengan kredensial benar;
- user nonaktif ditolak;
- session dibuat dengan aman.

## AC-02 Upload Template

- Super Admin dapat memilih SH/AP + periode + `.xlsx`;
- struktur template divalidasi;
- preview muncul sebelum data disimpan;
- error row terlihat jelas.

## AC-03 Snapshot History

- upload revisi tidak menghapus data lama;
- histori dapat dipilih kembali;
- hanya satu snapshot aktif per SH/AP+periode.

## AC-04 Dashboard KPI

Untuk periode aktif, sistem dapat menampilkan:

- Total FTK;
- Total Realisasi;
- Pemenuhan FTK;
- Gap FTK.

Angka harus identik dengan hasil agregasi snapshot database.

## AC-05 Per SH/AP

- sistem menampilkan card SH/AP yang memiliki data;
- pemenuhan dan gap per SH/AP benar;
- data tidak tercampur dengan periode lain.

## AC-06 Per Jenjang

- mapping case-insensitive bekerja;
- Generalist 1/2/3 seluruhnya masuk `gen 1-3`;
- jenjang kosong tidak salah dimapping;
- agregasi FTK dan realisasi benar.

## AC-07 Drill-down

User dapat expand:

```text
SH/AP → Level 2 → Level 3 → Level 4 → Jabatan
```

Jika level 4 kosong:

```text
SH/AP → Level 2 → Level 3 → Jabatan
```

Tidak boleh dibuat node kosong atau `-`.

## AC-08 Grouped Metrics

Setiap node menampilkan:

- TOTAL;
- GEN 1-3;
- MD;
- MM;
- MA;
- Specialist;
- Senior Specialist;
- Junior Expert;
- Expert;
- Senior Expert.

Setiap group minimal memiliki:

```text
FTK | Organik | Tugas Karya | Pihak Ketiga | Total Realisasi | Sisa/Delta
```

## AC-09 Histori Dropdown

- berada di kanan atas sesuai mockup;
- default periode terbaru;
- user dapat memilih histori;
- seluruh dashboard berubah berdasarkan pilihan.

## AC-10 Upload Button

- tombol berada tepat di kanan dropdown `Realisasi SH/AP • <periode>`;
- tombol membuka flow upload.

## AC-11 Data Source

Seluruh angka dashboard dan drill-down berasal dari data snapshot hasil upload template, bukan angka hardcoded.

---

# 36. Tahapan Implementasi

## Tahap 1 — Foundation

- struktur folder;
- `.env`;
- PostgreSQL connection;
- migrations;
- login;
- role Super Admin;
- layout/menu.

**Output:** aplikasi dapat login dan membaca database.

## Tahap 2 — Upload & Snapshot

- master SH/AP;
- upload form;
- parser Excel;
- mapping kolom;
- mapping jenjang;
- validation;
- preview;
- snapshot + history.

**Output:** file template dapat disimpan menjadi snapshot database.

## Tahap 3 — Dashboard

- KPI;
- per SH/AP;
- status prioritas;
- gap terbesar;
- pemenuhan per jenjang;
- management insight;
- history dropdown.

**Output:** dashboard mengikuti mockup dan seluruh angka berasal dari snapshot.

## Tahap 4 — FTK Tree

- tree endpoint;
- expand/collapse;
- lazy loading;
- grouped TOTAL;
- grouped jenjang;
- level kosong otomatis dilewati;
- search/filter.

**Output:** drill-down berjalan sampai leaf jabatan.

## Tahap 5 — Manajemen User & Hardening

- list/create/edit user;
- reset password;
- active/nonactive;
- CSRF;
- audit log;
- security headers;
- pagination;
- performance check.

---

# 37. Dokumentasi Wajib

Dokumentasi mengikuti struktur attachment proyek PHP modular.

Minimum:

```text
docs/
├── README.md
├── product/PRD.md
├── architecture/overview.md
├── features/
│   ├── dashboard/
│   ├── import/
│   ├── ftk-tree/
│   └── user-management/
├── database/
│   ├── schema.md
│   └── migrations.md
├── security/
│   ├── authentication.md
│   └── authorization.md
├── deployment/
└── reports/
```

Setiap perubahan database wajib memiliki migration formal dan update `docs/database/schema.md` serta `docs/database/migrations.md`.

Setiap perubahan signifikan harus memiliki implementation report dengan:

- Summary
- Files Changed
- Database Changes
- API Changes
- Architecture Changes
- Documentation Updated
- Tests Performed
- Manual Test
- Known Limitations

---

# 38. Manual Test Utama

## Scenario A — Upload Pertama Indonesia Power

1. Login sebagai Super Admin.
2. Buka Upload Data.
3. Pilih `PT PLN Indonesia Power`.
4. Pilih `Agustus 2026`.
5. Upload template valid.
6. Pastikan preview sesuai data Excel.
7. Konfirmasi import.
8. Buka dashboard Agustus 2026.
9. Pastikan card Indonesia Power muncul.
10. Pastikan KPI mencakup snapshot aktif tersebut.
11. Expand drill-down Indonesia Power.
12. Verifikasi level UI/UP/UL → Unit Pelaksana/KP → Unit Layanan → Jabatan.

## Scenario B — Unit Layanan Kosong

1. Cari baris dengan Unit Layanan `-`/blank.
2. Expand organisasi sampai Unit Pelaksana.
3. Pastikan jabatan langsung berada di bawah Unit Pelaksana.
4. Pastikan tidak ada node `-`.

## Scenario C — Upload Revisi

1. Upload Rev 1 untuk SH/AP + periode.
2. Catat KPI.
3. Upload Rev 2 untuk scope yang sama.
4. Pastikan Rev 2 menjadi aktif.
5. Pastikan Rev 1 masih tersedia di histori.
6. Pilih Rev 1.
7. Pastikan data lama masih dapat ditampilkan.

## Scenario D — Mapping Jenjang

Upload sample berisi:

```text
Generalist 1
GENERALIST 2
Generalist 3
Manajemen Dasar
MANAJEMEN MENENGAH
Specialist
```

Expected:

```text
gen 1-3
gen 1-3
gen 1-3
MD
MM
spesialist
```

---

# 39. Definition of Done MVP

MVP dianggap selesai ketika:

- login berfungsi;
- Super Admin dapat dikelola;
- file FTK/realisasi dapat di-upload;
- struktur template divalidasi;
- setiap upload membentuk snapshot historis;
- dashboard mengikuti mockup utama;
- semua KPI berasal dari snapshot;
- history dropdown bekerja;
- tombol upload tersedia di kanan history dropdown;
- dashboard agregat per SH/AP bekerja;
- agregasi per jenjang bekerja;
- tree 4 level + jabatan bekerja;
- node organisasi kosong dilewati;
- grouped metrics TOTAL dan per jenjang bekerja;
- dokumentasi database/fitur/arsitektur tersedia;
- manual test inti telah dijalankan dan hasilnya dicatat.

---

# 40. Catatan Implementasi Penting

1. **Jangan hardcode angka dashboard.** Semua berasal dari snapshot upload.
2. **Jangan parse Excel setiap dashboard dibuka.** Parse hanya saat upload.
3. **Jangan overwrite histori.** Setiap upload = snapshot baru.
4. **Gunakan `snapshot_id` sebagai boundary utama query.**
5. **Normalisasi `-` sebagai null untuk organisasi.**
6. **Jabatan selalu leaf.**
7. **Gap dashboard dan Sisa tabel mempunyai arah formula berbeda dan harus diberi nama jelas.**
8. **Mapping jenjang case-insensitive.**
9. **Business rule dashboard diletakkan di satu helper/config agar tidak tersebar.**
10. **Tree ORBIT dipakai sebagai referensi UX/pola agregasi, bukan dicopy mentah karena model sumber data FORSA berbasis snapshot upload.**

---

## Lampiran A — Ringkasan Data Flow

```text
                      ┌─────────────────────┐
                      │   Excel SH/AP       │
                      └──────────┬──────────┘
                                 │
                                 ▼
                      ┌─────────────────────┐
                      │ Upload + Validation │
                      └──────────┬──────────┘
                                 │
                                 ▼
                      ┌─────────────────────┐
                      │ Normalization       │
                      │ - Jenjang           │
                      │ - Org blank / '-'   │
                      │ - Formula           │
                      └──────────┬──────────┘
                                 │
                                 ▼
                      ┌─────────────────────┐
                      │ FTK Snapshot        │
                      │ PostgreSQL          │
                      └─────┬────────┬──────┘
                            │        │
               ┌────────────┘        └────────────┐
               ▼                                  ▼
      ┌───────────────────┐              ┌─────────────────┐
      │ Dashboard Aggregate│              │ FTK Tree        │
      │ KPI / Insight      │              │ Drill-down      │
      └───────────────────┘              └─────────────────┘
```

## Lampiran B — Hierarki FORSA

```text
Level 1 : SH/AP
          PT PLN INDONESIA POWER

Level 2 : UI/UP/UL
          UP

Level 3 : UNIT PELAKSANA/KANTOR PUSAT
          UBP TELLO

Level 4 : UNIT LAYANAN
          <nama unit layanan bila tersedia>

Leaf    : JABATAN
          SENIOR SPECIALIST ... — PoG ...
```

