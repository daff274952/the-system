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

## Deploy ke Vercel

Vercel tidak punya PHP dan MySQL bawaan. Proyek ini memakai runtime komunitas [`vercel-php`](https://github.com/vercel-community/php) (sudah diatur di `vercel.json`) dan database MySQL di layanan luar.

1. **Buat database MySQL gratis di cloud.** Yang disarankan adalah [TiDB Cloud](https://tidbcloud.com) (Serverless, kompatibel MySQL). Setelah cluster jadi, klik **Connect** dan catat host, port, user, dan password.
2. **Import repo ini di Vercel:** pilih **Add New → Project**, lalu pilih repo `the-system`. Framework Preset: **Other**. Tidak perlu build command.
3. **Isi Environment Variables** di Settings → Environment Variables:

   | Nama | Contoh | Keterangan |
   |---|---|---|
   | `DB_HOST` | `gateway01.ap-southeast-1.prod.aws.tidbcloud.com` | host dari TiDB Cloud |
   | `DB_PORT` | `4000` | TiDB memakai 4000 |
   | `DB_NAME` | `system_hunter` | dibuat otomatis kalau belum ada |
   | `DB_USER` | `xxxxxxxx.root` | user dari TiDB Cloud |
   | `DB_PASS` | `********` | password dari TiDB Cloud |
   | `DB_SSL` | `true` | wajib untuk database cloud |
   | `DB_SSL_CA` | *(kosongkan)* | isi path file CA (relatif dari folder proyek) hanya kalau penyedia database memberi file CA sendiri, misalnya Aiven |
   | `APP_DEBUG` | *(kosongkan)* | isi `true` sementara saja untuk melihat detail error |

4. **Redeploy** (Deployments → titik tiga → Redeploy) supaya environment variable terbaca, lalu buka domain `.vercel.app` kamu.

Catatan:
- Sesi login disimpan di tabel `sessions`, karena di serverless setiap request bisa berjalan di server yang berbeda.
- `vercel-php` adalah runtime komunitas, bukan buatan resmi Vercel. Kalau ada masalah, pindah ke hosting PHP biasa tetap bisa tanpa mengubah kode.

## Upload ke hosting

1. Di cPanel, buat database MySQL beserta user-nya, lalu beri user itu akses penuh ke database tersebut.
2. Upload semua file ke `public_html` (atau subfolder).
3. Salin `includes/config.example.php` menjadi `includes/config.php`, lalu isi data database dari hosting. Biarkan `'debug' => false`.
4. Buka domainnya. Tabel dibuat otomatis. Aplikasi tidak membutuhkan izin `CREATE DATABASE`.

Pesan error di halaman tidak menampilkan detail teknis. Detailnya tercatat di log error server. Cookie login otomatis hanya dikirim lewat HTTPS kalau situs memakai HTTPS.

## Struktur

Aplikasi berjalan sebagai **satu halaman (single-page app)**. Semua layar (login, daftar, profil, halaman utama) dirender oleh JavaScript, dan data dikirim lewat API JSON, jadi pindah layar tidak memuat ulang halaman. Data awal ikut di HTML, sehingga layar pertama tampil tanpa menunggu request tambahan.

```
index.php          satu-satunya halaman HTML + data awal
api.php            API JSON: boot, login, register, logout, profile, save, weight, reset
login.php          pengalihan ke #masuk / #daftar (link lama)
profile.php        pengalihan ke #profil (link lama)
schema.sql         struktur database
includes/          koneksi DB, sesi (disimpan di MySQL), validasi login & profil, helper state
api/index.php      pintu masuk untuk Vercel (memanggil index.php)
api/api.php        pintu masuk API untuk Vercel (memanggil api.php)
vercel.json        runtime PHP + aturan URL untuk Vercel
assets/core.js     helper, pemanggil API, notifikasi, router antar layar
assets/views.js    layar login/daftar dan profil
assets/app.js      halaman utama (status, quest, pengingat, riwayat)
assets/style.css   tampilan
.htaccess          cache CSS/JS, kompresi, blokir file privat
```

## Kebutuhan

PHP 8.1 ke atas dengan PDO MySQL, serta MySQL atau MariaDB.
