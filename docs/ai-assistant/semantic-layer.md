# FORSA AI Assistant — Semantic Layer & Tool Schema

**Status: IMPLEMENTED** (disetujui user, dikerjakan di Tahap 4 — lihat `docs/reports/2026/09/23/ai_assistant_tahap4_semantic_layer_tool.md`). Dokumen ini sekarang menjadi referensi desain yang mencerminkan kode sungguhan di `modules/ai/semantic/`, `modules/ai/query/`, `modules/ai/tools/QueryForsaTool.php` — bukan lagi draft.

## Semantic Model: `ftk_workforce`

```yaml
model: ftk_workforce
source: forsa_ftk_snapshot_rows
joins:
  - forsa_ftk_snapshots ON forsa_ftk_snapshot_rows.snapshot_id = forsa_ftk_snapshots.id
  - forsa_shap_entities ON forsa_ftk_snapshots.shap_id = forsa_shap_entities.id

metrics:
  total_ftk:
    type: sum
    field: ftk

  total_realisasi_organik:
    type: sum
    field: realisasi_organik

  total_realisasi_tugas_karya:
    type: sum
    field: realisasi_tugas_karya

  total_realisasi_pihak_ketiga:
    type: sum
    field: realisasi_pihak_ketiga

  total_realisasi:
    type: sum
    field: total_realisasi

  gap:
    type: formula
    # Konvensi TAMPILAN dashboard (Total Realisasi - FTK), BUKAN sisa_delta
    # mentah (konvensi tersimpan/PRD-validasi: FTK - Total Realisasi).
    # Wajib sama dengan yang dilihat user di layar — lihat CLAUDE.md.
    expression: SUM(total_realisasi) - SUM(ftk)

  fulfillment_rate:
    type: formula
    expression: SUM(total_realisasi) / NULLIF(SUM(ftk), 0) * 100

dimensions:
  company:
    field: forsa_shap_entities.short_name
    relation: forsa_shap_entities

  period:
    field: forsa_ftk_snapshots.period_month
    relation: forsa_ftk_snapshots
    # "latest" = MAX(period_month) WHERE is_active = true untuk company
    # terkait — resolver yang sama dengan DashboardService::resolveSnapshots(),
    # bukan query baru.

  job_level:
    field: job_level_group
    # label ramah-manusia via config/ftk_rules.php job_level_labels — reuse,
    # bukan didefinisikan ulang.

  position_grade:
    field: position_grade

  organization:
    field: [organization_level_2, organization_level_3, organization_level_4]

  gap_status:
    # derivasi dari sisa_delta MENTAH (bukan `gap` metric di atas) —
    # reuse ambang batas yang sudah ada di TreeService, jangan didefinisikan
    # ulang di semantic layer supaya tidak ada 2 sumber kebenaran.
    derived: true
```

## Alias & Business Vocabulary (reuse, bukan baru)

| User bilang | → semantic |
|---|---|
| "FTK", "formasi", "kebutuhan pegawai" | `total_ftk` |
| "realisasi", "jumlah pegawai" | `total_realisasi` |
| "gap", "selisih", "kekurangan", "surplus" | `gap` |
| "SH/AP", "anak perusahaan", "subholding" | `company` |
| nama SH/AP (PLN IP, PLN NP, Indonesia Power, dst.) | filter `company` = `forsa_shap_entities.short_name`/`name` yang cocok |

## Proposed Tool: `query_forsa`

```json
{
  "name": "query_forsa",
  "description": "Mengambil data FTK/realisasi FORSA yang sudah divalidasi & tersimpan (snapshot), diagregasi sesuai metric/dimension/filter yang diminta. Tidak pernah mengembalikan baris mentah per jabatan individual — selalu teragregasi.",
  "parameters": {
    "metrics": {
      "type": "array",
      "items": { "enum": ["total_ftk", "total_realisasi_organik", "total_realisasi_tugas_karya", "total_realisasi_pihak_ketiga", "total_realisasi", "gap", "fulfillment_rate"] },
      "minItems": 1
    },
    "dimensions": {
      "type": "array",
      "items": { "enum": ["company", "period", "job_level", "position_grade", "organization", "gap_status"] }
    },
    "filters": {
      "type": "array",
      "items": {
        "dimension": "string (salah satu dimension di atas)",
        "operator": "enum: =, !=, in",
        "value": "string atau array of string"
      }
    },
    "sort": { "metric": "string", "direction": "enum: asc, desc" },
    "limit": { "type": "integer", "default": 20, "max": 100 },
    "comparison": { "type": "string enum: previous_period, null", "description": "untuk follow-up seperti 'kalau bulan sebelumnya?'" }
  }
}
```

Validasi wajib di `ToolValidator` (belum diimplementasikan) sebelum tool ini didaftarkan ke `ToolRegistry`:
- `metrics`/`dimensions` harus persis salah satu yang terdaftar di atas (whitelist, bukan string bebas dari LLM).
- `limit` di-cap keras di 100 (PRD §15.1 "maximum result rows").
- `filters.dimension` juga divalidasi terhadap whitelist yang sama.
- Tidak ada parameter `raw_sql`, `table`, atau `column` bebas — sesuai PRD §14.

## Security Scope (RBAC) — proposal awal

Sesuai PRD §16: `SUPER_ADMIN` (satu-satunya role yang ada saat ini di `forsa_roles`, dikonfirmasi lewat `shared/auth_guard.php`) melihat semua SH/AP tanpa filter tambahan. Belum ada role lain di sistem, jadi row-level scope per-company **belum relevan untuk diimplementasikan sekarang** — akan ditambahkan begitu ada role baru yang memang dibatasi per SH/AP (PRD §16 menyebutnya "future role A/B").

## Yang TIDAK Termasuk Proposal Ini (deliberately out of scope)

- Materialized view — `database-sources.md` §6 merekomendasikan base table (volume data terlalu kecil untuk MV).
- Caching (PRD §20) — akan dinilai lagi di Phase 8 (Performance) setelah tool sungguhan berjalan dan ada pola pemakaian nyata untuk diukur.
- Comparison logic ("kalau bulan sebelumnya?") — parameternya sudah dicantumkan di schema di atas supaya tidak perlu tool terpisah nanti, tapi resolusinya (mencari period aktif sebelumnya) baru diimplementasikan saat Query Planner dibangun (Phase 6), bukan bagian dari proposal skema ini.

## Langkah Selanjutnya

Phase 5/6 selesai. Langkah berikutnya: Phase 7 (Security hardening — dedicated `forsa_ai_reader` read-only DB role, lihat known limitation di `QueryExecutor.php`), Phase 8 (Performance/caching), Phase 9 (Evaluation test set).
