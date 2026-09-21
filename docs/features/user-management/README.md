# Fitur: Manajemen User

Implementasi: `modules/administrasi/users.php` + `user_create.php`, `user_edit.php`, `user_toggle_status.php`, `user_reset_password.php`, `public/assets/js/users.js`.

- List dengan pencarian nama/email dan pagination server-side (10/halaman).
- Tambah/edit user via modal (create mewajibkan password ≥ 6 karakter, semua user baru diberi role `SUPER_ADMIN` — role tunggal fase MVP).
- Nonaktifkan/aktifkan user (user tidak dapat menonaktifkan akunnya sendiri).
- Reset password menghasilkan password acak yang ditampilkan sekali ke admin (tidak dikirim email — sesuai scope MVP tanpa integrasi email).
- Semua aksi mutasi tercatat ke `forsa_audit_logs`.
