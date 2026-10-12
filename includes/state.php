<?php
// Mengubah data di tabel menjadi bentuk "state" yang dipakai assets/app.js, dan sebaliknya.

const STAT_KEYS = ['str' => 'str', 'agi' => 'agi', 'vit' => 'vit', 'int' => 'intel', 'per' => 'per'];

function load_state(int $uid): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM hunters WHERE user_id = ?');
    $st->execute([$uid]);
    $h = $st->fetch();
    if (!$h) {
        $pdo->prepare('INSERT INTO hunters (user_id, name) SELECT id, username FROM users WHERE id = ?')->execute([$uid]);
        $st->execute([$uid]);
        $h = $st->fetch();
    }

    // Baris daily terakhir; kalau tanggalnya bukan hari ini, app.js yang menilai penalti.
    $st = $pdo->prepare('SELECT * FROM daily_quests WHERE user_id = ? ORDER BY quest_date DESC LIMIT 1');
    $st->execute([$uid]);
    $d = $st->fetch();
    $daily = $d ? [
        'date' => $d['quest_date'],
        'counts' => ['push' => (int) $d['pushup'], 'sit' => (int) $d['situp'], 'squat' => (int) $d['squat'], 'run' => (float) $d['run_km']],
        'done' => (bool) $d['done'],
    ] : null;

    $st = $pdo->prepare('SELECT client_id, title, tier, stat, done FROM side_quests WHERE user_id = ? ORDER BY id');
    $st->execute([$uid]);
    $quests = array_map(fn($q) => [
        'id' => (int) $q['client_id'], 'title' => $q['title'], 'tier' => $q['tier'], 'stat' => $q['stat'], 'done' => (bool) $q['done'],
    ], $st->fetchAll());

    $st = $pdo->prepare('SELECT logged_at, message FROM system_logs WHERE user_id = ? ORDER BY logged_at DESC, id DESC LIMIT 60');
    $st->execute([$uid]);
    $log = array_map(fn($l) => ['t' => (int) $l['logged_at'], 'm' => $l['message']], $st->fetchAll());

    $st = $pdo->prepare('SELECT remind_time FROM reminder_times WHERE user_id = ? ORDER BY remind_time');
    $st->execute([$uid]);
    $times = $st->fetchAll(PDO::FETCH_COLUMN);

    $stats = [];
    foreach (STAT_KEYS as $k => $col) {
        $stats[$k] = (int) $h[$col];
    }

    return [
        'name' => $h['name'],
        'level' => (int) $h['level'],
        'xp' => (int) $h['xp'],
        'points' => (int) $h['points'],
        'streak' => (int) $h['streak'],
        'hp' => (int) $h['hp'],
        'mp' => (int) $h['mp'],
        'stats' => $stats,
        'daily' => $daily,
        'quests' => $quests,
        'log' => $log,
        'rem' => [
            'on' => (bool) $h['reminders_on'],
            'times' => $times,
            'fired' => ['date' => $h['fired_date'] ?? '', 'list' => $h['fired_list'] === '' ? [] : explode(',', $h['fired_list'])],
        ],
    ];
}

function load_history(int $uid): array
{
    $st = db()->prepare('SELECT quest_date, done, pushup, situp, squat, run_km FROM daily_quests
                         WHERE user_id = ? ORDER BY quest_date DESC LIMIT 14');
    $st->execute([$uid]);
    return array_map(fn($r) => [
        'date' => $r['quest_date'], 'done' => (bool) $r['done'],
        'push' => (int) $r['pushup'], 'sit' => (int) $r['situp'], 'squat' => (int) $r['squat'], 'run' => (float) $r['run_km'],
    ], $st->fetchAll());
}

function int_in($v, int $min, int $max): int
{
    return max($min, min($max, (int) $v));
}

function valid_date($v): ?string
{
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function save_state(int $uid, array $s): void
{
    $pdo = db();
    $pdo->beginTransaction();

    $name = mb_substr(trim((string) ($s['name'] ?? '')), 0, 30) ?: 'Pemain';
    $stats = is_array($s['stats'] ?? null) ? $s['stats'] : [];
    $rem = is_array($s['rem'] ?? null) ? $s['rem'] : [];
    $fired = is_array($rem['fired'] ?? null) ? $rem['fired'] : [];
    $firedList = array_values(array_filter((array) ($fired['list'] ?? []), fn($t) => is_string($t) && preg_match('/^\d{2}:\d{2}$/', $t)));

    $pdo->prepare('UPDATE hunters SET name=?, level=?, xp=?, points=?, streak=?, hp=?, mp=?,
                   str=?, agi=?, vit=?, intel=?, per=?, reminders_on=?, fired_date=?, fired_list=? WHERE user_id=?')
        ->execute([
            $name,
            int_in($s['level'] ?? 1, 1, 9999),
            int_in($s['xp'] ?? 0, 0, 10_000_000),
            int_in($s['points'] ?? 0, 0, 100_000),
            int_in($s['streak'] ?? 0, 0, 100_000),
            int_in($s['hp'] ?? 100, 0, 10_000_000),
            int_in($s['mp'] ?? 10, 0, 10_000_000),
            int_in($stats['str'] ?? 10, 0, 100_000),
            int_in($stats['agi'] ?? 10, 0, 100_000),
            int_in($stats['vit'] ?? 10, 0, 100_000),
            int_in($stats['int'] ?? 10, 0, 100_000),
            int_in($stats['per'] ?? 10, 0, 100_000),
            !empty($rem['on']) ? 1 : 0,
            valid_date($fired['date'] ?? null),
            substr(implode(',', $firedList), 0, 255),
            $uid,
        ]);

    $d = is_array($s['daily'] ?? null) ? $s['daily'] : null;
    if ($d && ($date = valid_date($d['date'] ?? null))) {
        $c = is_array($d['counts'] ?? null) ? $d['counts'] : [];
        $done = !empty($d['done']) ? 1 : 0;
        $pdo->prepare('INSERT INTO daily_quests (user_id, quest_date, pushup, situp, squat, run_km, done, completed_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))
                       ON DUPLICATE KEY UPDATE pushup=VALUES(pushup), situp=VALUES(situp), squat=VALUES(squat),
                         run_km=VALUES(run_km), done=VALUES(done),
                         completed_at=IF(VALUES(done)=1, COALESCE(completed_at, NOW()), NULL)')
            ->execute([
                $uid, $date,
                int_in($c['push'] ?? 0, 0, 1000), int_in($c['sit'] ?? 0, 0, 1000), int_in($c['squat'] ?? 0, 0, 1000),
                max(0, min(50, round((float) ($c['run'] ?? 0), 1))), // batas = maksimum target di profil
                $done, $done,
            ]);
    }

    // Quest tambahan & jam pengingat: ganti seluruhnya dengan isi dari app.
    $pdo->prepare('DELETE FROM side_quests WHERE user_id = ?')->execute([$uid]);
    $ins = $pdo->prepare('INSERT IGNORE INTO side_quests (user_id, client_id, title, tier, stat, done) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (array_slice((array) ($s['quests'] ?? []), 0, 200) as $q) {
        if (!is_array($q)) continue;
        $title = mb_substr(trim((string) ($q['title'] ?? '')), 0, 80);
        $tier = in_array($q['tier'] ?? '', ['E', 'D', 'C', 'B', 'A', 'S'], true) ? $q['tier'] : null;
        $stat = array_key_exists($q['stat'] ?? '', STAT_KEYS) ? $q['stat'] : null;
        if ($title === '' || !$tier || !$stat) continue;
        $ins->execute([$uid, int_in($q['id'] ?? 0, 0, PHP_INT_MAX), $title, $tier, $stat, !empty($q['done']) ? 1 : 0]);
    }

    $pdo->prepare('DELETE FROM reminder_times WHERE user_id = ?')->execute([$uid]);
    $ins = $pdo->prepare('INSERT IGNORE INTO reminder_times (user_id, remind_time) VALUES (?, ?)');
    foreach (array_slice((array) ($rem['times'] ?? []), 0, 24) as $t) {
        if (is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
            $ins->execute([$uid, $t]);
        }
    }

    $pdo->prepare('DELETE FROM system_logs WHERE user_id = ?')->execute([$uid]);
    $ins = $pdo->prepare('INSERT INTO system_logs (user_id, logged_at, message) VALUES (?, ?, ?)');
    foreach (array_slice((array) ($s['log'] ?? []), 0, 60) as $l) {
        if (!is_array($l) || !is_string($l['m'] ?? null)) continue;
        $ins->execute([$uid, int_in($l['t'] ?? 0, 0, PHP_INT_MAX), mb_substr($l['m'], 0, 255)]);
    }

    $pdo->commit();
}

function reset_player(int $uid, string $username): void
{
    $pdo = db();
    $pdo->beginTransaction();
    foreach (['daily_quests', 'side_quests', 'system_logs', 'reminder_times', 'hunters'] as $t) {
        $pdo->prepare("DELETE FROM $t WHERE user_id = ?")->execute([$uid]);
    }
    $pdo->prepare('INSERT INTO hunters (user_id, name) VALUES (?, ?)')->execute([$uid, $username]);
    $ins = $pdo->prepare('INSERT INTO reminder_times (user_id, remind_time) VALUES (?, ?)');
    foreach (['07:00', '12:00', '19:00', '22:00'] as $t) {
        $ins->execute([$uid, $t]);
    }
    $pdo->prepare('INSERT INTO system_logs (user_id, logged_at, message) VALUES (?, ?, ?)')
        ->execute([$uid, (int) (microtime(true) * 1000), 'Kamu telah memperoleh syarat untuk menjadi Player.']);
    $pdo->commit();
}
