<?php
// Halaman lama; sekarang semua layar ada di index.php.
header('Location: ./#' . (($_GET['mode'] ?? '') === 'register' ? 'daftar' : 'masuk'));
