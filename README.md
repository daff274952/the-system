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

## Mode sekarang: tanpa database (sementara)

Untuk sementara aplikasi berjalan **tanpa database dan tanpa PHP**. Halaman `index.html` memakai [`assets/local-api.js`](assets/local-api.js) sebagai pengganti `api.php`, dan semua akun, profil, serta progres disimpan di `localStorage` browser.

- **Kelebihan:** bisa langsung di-deploy ke Vercel (atau GitHub Pages, Netlify, dll.) sebagai situs statis, tanpa pengaturan apa pun.
- **Batasan:** data hanya ada di browser dan perangkat itu. Kalau data browser dihapus atau kamu pindah perangkat, akun dan progres tidak ikut. Password disimpan sebagai hash SHA-256, tapi ini bukan pengamanan setara server.

**Deploy ke Vercel (mode ini):** import repo di Vercel, pilih Framework Preset **Other**, kosongkan build command, lalu Deploy. `.vercelignore` memastikan hanya `index.html` dan `assets/` yang ter-upload.

**Menjalankan lokal:** buka `http://localhost/system-hunter/` di XAMPP (cukup Apache), atau server statis apa pun.

### Mengaktifkan lagi versi database

Kode versi database (PHP + MySQL) tetap ada: `index.php`, `api.php`, `includes/`, dan `schema.sql`.

- **Di XAMPP:** buka `http://localhost/system-hunter/index.php`, atau hapus baris `DirectoryIndex` di `.htaccess`.
- **Di Vercel:** ganti isi `vercel.json` dengan `vercel.database.json`, hapus daftar file di `.vercelignore` (kecuali `includes/config.php`), lalu ikuti langkah **Deploy ke Vercel (versi database)** di bawah.

Data di mode tanpa database tidak otomatis pindah ke database.

## Menjalankan versi database di XAMPP

1. Salin folder ini ke `C:\xampp\htdocs\system-hunter`.
2. Di XAMPP Control Panel, klik **Start** pada **Apache** dan **MySQL**.
3. Buka `http://localhost/system-hunter/index.php`.

Database `system_hunter` dan semua tabelnya dibuat otomatis saat aplikasi pertama dibuka.

## Konfigurasi database

Salin `includes/config.example.php` menjadi `includes/config.php`, lalu isi host, nama database, user, dan password. `config.php` tidak ikut di-commit, jadi passwordmu tidak masuk ke GitHub. Kalau `config.php` tidak ada, aplikasi memakai default XAMPP (user `root` tanpa password).

Struktur tabel ada di [`schema.sql`](schema.sql).

## Deploy ke Vercel (versi database)

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
index.html         halaman versi tanpa database (dipakai sekarang)
index.php          halaman versi database + data awal
api.php            API JSON: boot, login, register, logout, profile, save, weight, reset
login.php          pengalihan ke #masuk / #daftar (link lama)
profile.php        pengalihan ke #profil (link lama)
schema.sql         struktur database
includes/          koneksi DB, sesi (disimpan di MySQL), validasi login & profil, helper state
api/index.php      pintu masuk untuk Vercel (memanggil index.php)
api/api.php        pintu masuk API untuk Vercel (memanggil api.php)
vercel.json        pengaturan Vercel untuk situs statis (mode tanpa database)
vercel.database.json  pengaturan Vercel versi database (runtime PHP)
assets/core.js     helper, pemanggil API, notifikasi, router antar layar
assets/local-api.js  pengganti api.php di browser (mode tanpa database)
assets/views.js    layar login/daftar dan profil
assets/app.js      halaman utama (status, quest, pengingat, riwayat)
assets/style.css   tampilan
.htaccess          cache CSS/JS, kompresi, blokir file privat
```

## Kebutuhan

PHP 8.1 ke atas dengan PDO MySQL, serta MySQL atau MariaDB.
