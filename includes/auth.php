<?php
// Login, daftar, dan simpan profil. Dipakai oleh api.php; mengembalikan daftar error per field.

const DEFAULT_REMINDERS = ['07:00', '12:00', '19:00', '22:00'];

function attempt_login(string $username, string $password): array
{
    $lockedUntil = $_SESSION['lock_until'] ?? 0;
    if ($lockedUntil > time()) {
        return ['form' => 'Terlalu banyak percobaan gagal. Tunggu ' . ($lockedUntil - time()) . ' detik lagi.'];
    }
    $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['lock_until'] = time() + 30;
            $_SESSION['fails'] = 0;
        }
        return ['form' => 'Username atau password salah.'];
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
    session_regenerate_id(true);
    unset($_SESSION['fails'], $_SESSION['lock_until']);
    $_SESSION['uid'] = (int) $row['id'];
    return [];
}

function attempt_register(string $username, string $password, string $confirm): array
{
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        return ['username' => 'Username 3–30 karakter: huruf, angka, atau garis bawah.'];
    }
    if (strlen($password) < 6) {
        return ['password' => 'Password minimal 6 karakter.'];
    }
    if ($password !== $confirm) {
        return ['confirm' => 'Konfirmasi password tidak sama.'];
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
    $st->execute([$username]);
    if ($st->fetch()) {
        return ['username' => 'Username sudah dipakai Player lain.'];
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO users (username, password_hash, last_login_at) VALUES (?, ?, NOW())')
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO hunters (user_id, name) VALUES (?, ?)')->execute([$uid, $username]);
    $ins = $pdo->prepare('INSERT INTO reminder_times (user_id, remind_time) VALUES (?, ?)');
    foreach (DEFAULT_REMINDERS as $t) {
        $ins->execute([$uid, $t]);
    }
    $pdo->prepare('INSERT INTO system_logs (user_id, logged_at, message) VALUES (?, ?, ?)')
        ->execute([$uid, (int) (microtime(true) * 1000), 'Kamu telah memperoleh syarat untuk menjadi Player.']);
    $pdo->commit();
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['new_player'] = true;
    return [];
}

function save_profile(int $uid, array $in): array
{
    $f = [];
    foreach (['name', 'gender', 'birth_date', 'height', 'weight', 'target_weight', 'target_rank', 'fitness', 'goal', 'push', 'sit', 'squat', 'run'] as $k) {
        $f[$k] = trim((string) ($in[$k] ?? ''));
    }
    $errors = [];
    if ($f['name'] === '' || mb_strlen($f['name']) > 30) {
        $errors['name'] = 'Isi nama Player, maksimal 30 karakter.';
    }
    if (!in_array($f['gender'], ['', 'L', 'P'], true)) {
        $f['gender'] = '';
    }
    if ($f['birth_date'] !== '') {
        $bd = DateTime::createFromFormat('!Y-m-d', $f['birth_date']);
        $age = $bd ? $bd->diff(new DateTime('today'))->y : -1;
        if (!$bd || $bd > new DateTime('today') || $age < 7 || $age > 100) {
            $errors['birth_date'] = 'Tanggal lahir tidak valid (umur 7–100 tahun).';
        }
    }
    if (!ctype_digit($f['height']) || (int) $f['height'] < 100 || (int) $f['height'] > 250) {
        $errors['height'] = 'Tinggi badan 100–250 cm.';
    }
    $weight = valid_weight($f['weight']);
    if ($weight === null) {
        $errors['weight'] = 'Berat badan 25–300 kg.';
    }
    $targetWeight = null;
    if ($f['target_weight'] !== '' && ($targetWeight = valid_weight($f['target_weight'])) === null) {
        $errors['target_weight'] = 'Target berat 25–300 kg, atau kosongkan.';
    }
    if (!isset(RANK_LEVELS[$f['target_rank']])) {
        $errors['target_rank'] = 'Pilih target rank.';
    }
    if (!isset(FITNESS_PRESETS[$f['fitness']])) {
        $errors['fitness'] = 'Pilih tingkat kebugaran.';
    }
    if (!isset(MAIN_GOALS[$f['goal']])) {
        $errors['goal'] = 'Pilih tujuan utama.';
    }
    foreach (['push', 'sit', 'squat'] as $k) {
        if (!ctype_digit($f[$k]) || (int) $f[$k] < 1 || (int) $f[$k] > 1000) {
            $errors['targets'] = 'Target repetisi 1–1000, lari 0,5–50 km.';
        }
    }
    if (!is_numeric($f['run']) || (float) $f['run'] < 0.5 || (float) $f['run'] > 50) {
        $errors['targets'] = 'Target repetisi 1–1000, lari 0,5–50 km.';
    }
    if ($errors) {
        return $errors;
    }

    $existing = load_profile($uid);
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO profiles (user_id, gender, birth_date, height_cm, weight_kg, target_weight_kg, target_rank,
                     fitness_level, main_goal, target_push, target_sit, target_squat, target_run_km)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE gender=VALUES(gender), birth_date=VALUES(birth_date), height_cm=VALUES(height_cm),
                     weight_kg=VALUES(weight_kg), target_weight_kg=VALUES(target_weight_kg), target_rank=VALUES(target_rank),
                     fitness_level=VALUES(fitness_level), main_goal=VALUES(main_goal), target_push=VALUES(target_push),
                     target_sit=VALUES(target_sit), target_squat=VALUES(target_squat), target_run_km=VALUES(target_run_km)')
        ->execute([
            $uid, $f['gender'] ?: null, $f['birth_date'] ?: null, (int) $f['height'], $weight, $targetWeight,
            $f['target_rank'], $f['fitness'], $f['goal'],
            (int) $f['push'], (int) $f['sit'], (int) $f['squat'], round((float) $f['run'], 1),
        ]);
    if (!$existing || abs($weight - $existing['weight']) > 0.01) {
        log_weight($uid, $weight);
    }
    $pdo->prepare('UPDATE hunters SET name = ? WHERE user_id = ?')->execute([$f['name'], $uid]);
    $pdo->commit();
    return [];
}

/** Semua data yang dibutuhkan halaman untuk tampil tanpa request tambahan. */
function build_boot(?array $user): array
{
    $boot = [
        'csrf' => csrf_token(),
        'user' => null,
        'meta' => ['presets' => FITNESS_PRESETS, 'goals' => MAIN_GOALS, 'rankLevels' => RANK_LEVELS],
    ];
    if (!$user) {
        return $boot;
    }
    $uid = (int) $user['id'];
    $profile = load_profile($uid);
    $boot['user'] = ['username' => $user['username']];
    $boot['profile'] = $profile;
    $boot['state'] = load_state($uid);
    $boot['history'] = load_history($uid);
    $boot['weights'] = load_weights($uid);
    // Sambutan Player baru tampil sekali, setelah profilnya terisi.
    $boot['newPlayer'] = $profile && consume_new_player();
    return $boot;
}

function consume_new_player(): bool
{
    $v = !empty($_SESSION['new_player']);
    unset($_SESSION['new_player']);
    return $v;
}
