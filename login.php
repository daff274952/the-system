<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $lockedUntil = $_SESSION['lock_until'] ?? 0;

    if (!check_csrf($_POST['csrf'] ?? null)) {
        $error = 'Sesi kedaluwarsa. Coba kirim lagi.';
    } elseif ($mode === 'login' && $lockedUntil > time()) {
        $error = 'Terlalu banyak percobaan gagal. Tunggu ' . ($lockedUntil - time()) . ' detik lagi.';
    } elseif ($mode === 'register') {
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $error = 'Username 3–30 karakter: huruf, angka, atau garis bawah.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak sama.';
        } else {
            $pdo = db();
            $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
            $st->execute([$username]);
            if ($st->fetch()) {
                $error = 'Username sudah dipakai Player lain.';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO users (username, password_hash, last_login_at) VALUES (?, ?, NOW())')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO hunters (user_id, name) VALUES (?, ?)')->execute([$uid, $username]);
                $ins = $pdo->prepare('INSERT INTO reminder_times (user_id, remind_time) VALUES (?, ?)');
                foreach (['07:00', '12:00', '19:00', '22:00'] as $t) {
                    $ins->execute([$uid, $t]);
                }
                $pdo->prepare('INSERT INTO system_logs (user_id, logged_at, message) VALUES (?, ?, ?)')
                    ->execute([$uid, (int) (microtime(true) * 1000), 'Kamu telah memperoleh syarat untuk menjadi Player.']);
                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['uid'] = $uid;
                $_SESSION['new_player'] = true;
                header('Location: profile.php');
                exit;
            }
        }
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $st->execute([$username]);
        $row = $st->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
            session_regenerate_id(true);
            unset($_SESSION['fails'], $_SESSION['lock_until']);
            $_SESSION['uid'] = (int) $row['id'];
            header('Location: index.php');
            exit;
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['lock_until'] = time() + 30;
            $_SESSION['fails'] = 0;
        }
        $error = 'Username atau password salah.';
    }
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>The System</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="assets/style.css">
<style>
.auth{min-height:100vh;display:grid;place-items:center;padding:24px 16px}
.auth .win{width:100%;max-width:400px}
.auth h1{font-family:var(--display);font-size:20px;letter-spacing:.06em;text-align:center;margin:0 0 6px;text-wrap:balance}
.auth .sub{text-align:center;color:var(--muted);font-size:13px;margin:0 0 18px}
.auth form{display:grid;gap:12px}
.auth label{display:grid;gap:4px;font-family:var(--display);letter-spacing:.12em;text-transform:uppercase;font-size:12px;color:var(--muted)}
.auth input{width:100%;font-family:var(--body);letter-spacing:0;text-transform:none}
.auth .btn{padding:11px 14px;margin-top:4px}
.auth .err{border:1px solid rgba(255,77,109,.55);background:rgba(255,77,109,.08);padding:10px 12px;font-size:13px}
.auth .switch{text-align:center;font-size:13px;color:var(--muted);margin:16px 0 0}
.auth .switch a{color:var(--glow)}
</style>
</head>
<body>
<canvas id="bg" aria-hidden="true"></canvas>
<main class="auth">
  <section class="win">
    <div class="win-title"><span><?= $mode === 'register' ? 'Registrasi Player' : 'Autentikasi Player' ?></span></div>
    <?php if ($mode === 'register'): ?>
      <h1>Kamu telah memperoleh syarat untuk menjadi Player.</h1>
      <p class="sub">Daftarkan dirimu untuk menerimanya.</p>
    <?php else: ?>
      <h1>Selamat datang kembali, Player.</h1>
      <p class="sub">Masuk untuk melanjutkan daily quest-mu.</p>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="on">
      <?php if ($error): ?><div class="err" role="alert"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="mode" value="<?= $mode ?>">
      <label for="username">Username
        <input id="username" name="username" value="<?= e($username) ?>" required maxlength="30" autocomplete="username" autofocus>
      </label>
      <label for="password">Password
        <input id="password" name="password" type="password" required minlength="<?= $mode === 'register' ? 6 : 1 ?>" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>">
      </label>
      <?php if ($mode === 'register'): ?>
      <label for="confirm">Ulangi password
        <input id="confirm" name="confirm" type="password" required minlength="6" autocomplete="new-password">
      </label>
      <?php endif; ?>
      <button class="btn" type="submit"><?= $mode === 'register' ? 'Terima & Daftar' : 'Masuk' ?></button>
    </form>

    <p class="switch">
      <?php if ($mode === 'register'): ?>
        Sudah punya akun? <a href="login.php">Masuk</a>
      <?php else: ?>
        Belum jadi Player? <a href="login.php?mode=register">Daftar sekarang</a>
      <?php endif; ?>
    </p>
  </section>
</main>
<script src="assets/bg.js"></script>
</body>
</html>
