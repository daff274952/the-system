<?php
// Salin file ini menjadi config.php lalu isi sesuai database kamu.
// config.php tidak ikut di-commit, jadi password database tetap aman.
// Kalau config.php tidak ada, nilai di file ini yang dipakai (default XAMPP: root tanpa password).
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'system_hunter',
    'db_user' => 'root',
    'db_pass' => '',
];
