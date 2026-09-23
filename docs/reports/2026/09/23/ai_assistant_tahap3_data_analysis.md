# FORSA AI Assistant — Tahap 3: Database Source Selection (Gate) & Data Analysis

**Tidak ada kode yang ditulis/diubah di tahap ini.** Sesuai PRD §5.3, urutan wajibnya adalah Schema Analysis → ... → **Human Review** → baru Semantic Layer Generation/Tool Schema Generation/Activation. Tahap ini mengerjakan bagian sebelum Human Review; menunggu persetujuan sebelum Tahap 4 menulis kode.

## Summary

1. **Phase 3 gate dijawab user**: sumber data AI Assistant yang disetujui adalah `forsa_ftk_snapshot_rows`, `forsa_ftk_snapshots`, `forsa_shap_entities`.
2. **Phase 4 (Data Analysis) selesai**: schema analysis, relationship analysis, security classification, metric/dimension candidates, dan MV analysis — seluruhnya berdasarkan introspeksi database sungguhan (`information_schema`, `pg_indexes`, `pg_stat_user_tables`, sampling data), bukan asumsi. Ditulis di [`docs/ai-assistant/database-sources.md`](../../../ai-assistant/database-sources.md).
3. **Proposal semantic layer + tool schema** (draft, belum kode) ditulis di [`docs/ai-assistant/semantic-layer.md`](../../../ai-assistant/semantic-layer.md), menunggu review.

## Temuan Penting

- Volume `forsa_ftk_snapshot_rows` hanya ~11.589 baris — jauh di bawah skala yang menjustifikasi Materialized View (PRD §7.3 menyebut skala jutaan baris). **Rekomendasi: base table**, bukan MV, bertentangan dengan asumsi umum "AI analytics selalu butuh MV" — keputusan ini didukung data, bukan default otomatis (sesuai PRD §7.4 "Keputusan MV Tidak Otomatis").
- Index yang sudah ada (`snapshot_id` + kombinasi `job_level_group`/`position_grade`/`organization_level_2/3/4`) sudah mencakup seluruh pola filter yang realistis untuk `query_forsa` — tidak perlu index baru untuk tahap ini.
- **Tidak ada data personal karyawan** di ketiga tabel (dikonfirmasi lewat sampling): `position_name` adalah judul jabatan (mis. "SENIOR SPECIALIST OPERASI PEMBANGKIT BATU BARA"), bukan nama orang.
- Dua kolom diklasifikasikan **internal, tidak boleh diekspos ke AI**: `rencana_pemenuhan` (teks bebas dari upload Excel — risiko prompt injection kalau ikut masuk context LLM) dan `created_by` (identitas user pengunggah).
- Metric `gap` di semantic layer **wajib** memakai konvensi tampilan dashboard (`Total Realisasi − FTK`), bukan `sisa_delta` mentah — supaya jawaban AI konsisten dengan angka yang dilihat user di layar (lihat catatan dua-konvensi di `CLAUDE.md`).
- Alias dimension (`job_level`, dsb.) dan ambang batas `gap_status` **direncanakan reuse** dari `config/ftk_rules.php`/`TreeService` yang sudah ada — tidak ada logic bisnis baru yang didefinisikan ulang di semantic layer.

## Files Changed

- **Baru:**
  - [docs/ai-assistant/database-sources.md](../../../ai-assistant/database-sources.md) — Schema Analysis Report, Relationship Analysis, Security Classification, MV Analysis Report.
  - [docs/ai-assistant/semantic-layer.md](../../../ai-assistant/semantic-layer.md) — proposal model semantic `ftk_workforce`, alias, tool schema `query_forsa`, RBAC scope awal.
- **Diubah:**
  - [docs/ai-assistant/README.md](../../../ai-assistant/README.md) — status Phase 3 (dijawab) & Phase 4 (selesai) diperbarui, Phase 5 ditandai "menunggu human review".

## Database Changes
None — tahap ini murni analisis terhadap skema yang sudah ada, tidak ada migration/DDL baru.

## API Changes
None.

## Architecture Changes
None (proposal, belum kode).

## Tests Performed
Bukan pengujian kode (tidak ada kode baru), melainkan verifikasi analisis terhadap database sungguhan:
- `information_schema.columns`/`information_schema.table_constraints` untuk struktur & FK ketiga tabel.
- `pg_indexes` untuk index yang sudah ada.
- `pg_stat_user_tables` untuk jumlah baris aktual.
- Sampling data langsung (`SELECT ... LIMIT`) untuk memverifikasi `position_name` bukan data personal dan `job_level_group` cocok dengan mapping di `config/ftk_rules.php`.

## Manual Test
Tidak ada UI/API yang berubah untuk diuji manual pada tahap ini.

## Known Limitations
- Proposal `gap_status` derivation dan `period` comparison (`previous_period`) belum divalidasi terhadap edge case nyata (mis. SH/AP yang baru pertama kali upload, tanpa periode sebelumnya) — akan ditangani saat Query Planner sungguhan ditulis di Phase 6.

## Next Step

**Menunggu persetujuan proposal** di [`semantic-layer.md`](../../../ai-assistant/semantic-layer.md) sebelum lanjut ke Phase 5 (menulis `SemanticRegistry`/`MetricResolver`/`DimensionResolver`/`AliasResolver` sungguhan) dan Phase 6 (`QueryForsaTool`, `QueryBuilder`, mendaftarkan tool nyata pertama ke `ToolRegistry`).
