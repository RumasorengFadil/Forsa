# Kunci resolusi Composer ke PHP 8.2 (fix error di server Windows)

## Summary

Instalasi di server Windows (PHP 8.2.12) gagal dengan:

```
maennchen/zipstream-php 3.2.2 requires php-64bit ^8.3 -> your php-64bit version (8.2.12) does not satisfy that requirement.
```

`composer.json` proyek sendiri sudah `"php": ">=8.1"` dan tidak pernah
mensyaratkan 8.3 — masalahnya ada di `composer.lock`: file itu terakhir
di-generate di mesin dengan PHP 8.4, sehingga Composer bebas memilih versi
terbaru `maennchen/zipstream-php` (3.2.2, dependency transitif dari
`phpoffice/phpspreadsheet`), yang mensyaratkan PHP 8.3+. Tanpa batasan
eksplisit, ini bisa terulang di masa depan setiap kali `composer update`
dijalankan di mesin dev yang PHP-nya lebih baru dari target server.

## Files Changed

- `composer.json` — tambah `"config.platform.php": "8.2"` supaya Composer
  **selalu** meresolusi/mengunci versi dependency yang kompatibel dengan
  PHP 8.2, terlepas dari versi PHP aktual di mesin yang menjalankan
  `composer update` (fix permanen, bukan cuma sekali downgrade manual).
  `require.php` diselaraskan dari `>=8.1` jadi `>=8.2`, karena
  `maennchen/zipstream-php` versi tertinggi yang masih kompatibel (3.1.2)
  sendiri mensyaratkan `^8.2` — jadi `>=8.1` sebelumnya sudah tidak akurat.
- `composer.lock` — regenerasi via `composer update maennchen/zipstream-php
  --with-all-dependencies` dengan platform terkunci di atas: turun dari
  `3.2.2` ke `3.1.2` (satu-satunya paket yang berubah).
- `docs/deployment/installation.md` — prasyarat PHP diselaraskan ke `≥ 8.2`.

## Database Changes

None.

## API Changes

None.

## Architecture Changes

None — hanya dependency lock & konfigurasi Composer.

## Documentation Updated

- `docs/deployment/installation.md`

## Tests Performed

- `composer validate` — valid (hanya warning "no license", tidak relevan).
- `composer check-platform-reqs --lock` — seluruh package & ekstensi lolos
  terhadap PHP yang aktif di mesin ini (8.4.22); tidak ada lagi entri yang
  butuh >8.2 di `composer.lock` (dicek manual: `zipstream-php` kini `^8.2`,
  `phpspreadsheet` `>=8.1.0 <8.6.0`, sisanya `^7.x || ^8.x`).
- `php -r "require 'vendor/autoload.php'; class_exists(...Spreadsheet::class)"`
  → berhasil load setelah downgrade `zipstream-php`.

## Manual Test

Belum ditest langsung di PHP 8.2 sungguhan (mesin ini PHP 8.4) atau di
server Windows yang melaporkan error tersebut — perbaikan diverifikasi
lewat `composer check-platform-reqs`/`validate` dan smoke-test autoload,
bukan dengan menjalankan aplikasi penuh di interpreter 8.2. Disarankan user
menjalankan `composer install` ulang di server Windows (hapus `vendor/`
lama dulu kalau ada) untuk konfirmasi akhir.

## Known Limitations

- `config.platform.php` mengunci *lantai* (floor) resolusi ke 8.2 — kalau
  nanti proyek butuh naik ke PHP yang lebih baru, nilai ini harus diupdate
  manual bersamaan supaya Composer mau memilih versi dependency yang lebih
  baru lagi.
