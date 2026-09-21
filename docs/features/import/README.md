# Fitur: Upload & Snapshot

Implementasi: `modules/import/FtkParser.php`, `SnapshotWriter.php`, `upload_submit.php`, `history_api.php`.

## Alur Dua Tahap

1. **Preview** (`upload_submit.php?action=preview`) — file diunggah ke `storage/temp/`, di-parse dengan `FtkParser`, hasil (summary, contoh baris, error, warning) dikembalikan sebagai JSON + `token` yang disimpan di session (`$_SESSION['_upload_tokens']`), merujuk path file temp. File belum disimpan permanen dan snapshot belum ditulis.
2. **Confirm** (`upload_submit.php?action=confirm`) — mem-parse ulang file dari token, menolak bila masih ada error kritis, lalu:
   - menyalin file ke `storage/uploads/` (nama file di-random-kan),
   - insert `forsa_import_jobs`,
   - insert `forsa_import_errors` (warning + error yang tercatat),
   - `SnapshotWriter::write()` — transaksi tunggal: nonaktifkan snapshot lama (shap+periode yang sama), insert snapshot + rows baru, tandai aktif.

## Validasi (PRD §8)

- Ekstensi `.xlsx` saja, ukuran maksimum dikonfigurasi (`config/upload.php`).
- Header wajib divalidasi persis terhadap template (case/space-insensitive) — `FtkParser::assertHeader()`.
- Per baris: `SEBUTAN JABATAN` wajib, FTK/realisasi harus numerik ≥ 0, formula `Total Realisasi = Organik+TugasKarya+PihakKetiga` dan `Sisa = FTK-TotalRealisasi` divalidasi ulang (mismatch → warning, sistem tetap memakai hasil formula).
- Baris `Total` / `% Realisasi ...` pada footer template otomatis diabaikan.

## Mapping Jenjang (PRD §9)

`shared/JobLevelMapper.php` + `config/ftk_rules.php` — case-insensitive setelah trim; jenjang tak dikenal masuk sebagai warning dan tetap dihitung di TOTAL namun tidak masuk grup manapun.

## Snapshot Immutability (PRD §7, §34)

`SnapshotWriter` selalu membuat baris baru (revision_no += 1) dan tidak pernah menimpa data lama; aktivasi snapshot baru + deaktivasi snapshot lama terjadi dalam satu transaksi database.
