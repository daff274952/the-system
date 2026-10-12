# The System

Aplikasi pelacak kebiasaan dan olahraga bergaya "Sistem" dari *Solo Leveling*. Selesaikan daily quest, naikkan level dan rank dari E sampai S, bagi poin stat, dan pantau berat badan.

## Fitur

- **Akun Player**: daftar, login, dan logout dengan password terenkripsi dan proteksi CSRF.
- **Profil**: tinggi, berat, BMI (klasifikasi Kemenkes), target berat, target rank, dan tingkat kebugaran.
- **Daily Quest**: push-up, sit-up, squat, dan lari. Targetnya mengikuti tingkat kebugaran (Pemula, Menengah, Lanjut). Kalau tidak selesai sebelum tengah malam, Player kena penalti.
- **Status**: level, EXP, HP/MP, rank E–S, job, title, dan 5 stat (STR, AGI, VIT, INT, PER).
- **Quest tambahan** dengan tingkat kesulitan E–S.
- **Pengingat**: notifikasi di halaman dan notifikasi browser pada jam yang bisa diatur.
- **Riwayat**: 14 hari terakhir daily quest dan grafik berat badan.

## Menjalankan di XAMPP

1. Salin folder ini ke `C:\xampp\htdocs\system-hunter`.
2. Di XAMPP Control Panel, klik **Start** pada **Apache** dan **MySQL**.
3. Buka `http://localhost/system-hunter/`.

Database `system_hunter` dan semua tabelnya dibuat otomatis saat aplikasi pertama dibuka.

## Konfigurasi database

Salin `includes/config.example.php` menjadi `includes/config.php`, lalu isi host, nama database, user, dan password. `config.php` tidak ikut di-commit, jadi passwordmu tidak masuk ke GitHub. Kalau `config.php` tidak ada, aplikasi memakai default XAMPP (user `root` tanpa password).

Struktur tabel ada di [`schema.sql`](schema.sql).

## Struktur

```
index.php        halaman utama (status, quest, profil, riwayat)
login.php        login & daftar
profile.php      isi / edit profil
logout.php
api.php          simpan & muat progres (JSON)
schema.sql       struktur database
includes/        koneksi DB, sesi, helper state & profil
assets/          CSS, JavaScript aplikasi, efek latar
```

## Kebutuhan

PHP 8.1 ke atas dengan PDO MySQL, serta MySQL atau MariaDB.
