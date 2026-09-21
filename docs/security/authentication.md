# Autentikasi

- Password disimpan dengan `password_hash()` (bcrypt/argon default PHP) dan diverifikasi dengan `password_verify()`.
- Session ID di-regenerate (`session_regenerate_id(true)`) setiap login berhasil (`shared/auth_guard.php::login_user`).
- Cookie sesi: `HttpOnly`, `SameSite=Lax`, `Secure` otomatis aktif saat `APP_ENV=production` (`shared/bootstrap.php`).
- Login gagal & sukses dicatat ke `forsa_audit_logs`.
- User nonaktif (`is_active=false`) ditolak saat login meski password benar.
- Semua halaman/endpoint terproteksi memanggil `require_login()` (`shared/auth_guard.php`) yang redirect ke `/login.php` (halaman) atau mengembalikan HTTP 401 JSON (endpoint `*_api.php`).
