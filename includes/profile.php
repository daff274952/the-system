<?php
// Profil fisik, target, dan riwayat berat badan Player.

const FITNESS_PRESETS = [
    'pemula'   => ['label' => 'Pemula',   'desc' => 'Jarang olahraga',         'push' => 20,  'sit' => 20,  'squat' => 20,  'run' => 2],
    'menengah' => ['label' => 'Menengah', 'desc' => 'Olahraga 1–3x seminggu',  'push' => 50,  'sit' => 50,  'squat' => 50,  'run' => 5],
    'lanjut'   => ['label' => 'Lanjut',   'desc' => 'Menu asli Sung Jin-Woo',  'push' => 100, 'sit' => 100, 'squat' => 100, 'run' => 10],
];

const MAIN_GOALS = [
    'turun_berat' => 'Menurunkan berat badan',
    'bangun_otot' => 'Membangun otot',
    'stamina'     => 'Meningkatkan stamina',
    'disiplin'    => 'Membangun disiplin',
];

// Level minimum tiap rank; sama dengan rankOf() di assets/app.js.
const RANK_LEVELS = ['E' => 1, 'D' => 10, 'C' => 20, 'B' => 35, 'A' => 50, 'S' => 70];

function load_profile(int $uid): ?array
{
    $st = db()->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $st->execute([$uid]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    return [
        'gender' => $p['gender'],
        'birthDate' => $p['birth_date'],
        'height' => (int) $p['height_cm'],
        'weight' => (float) $p['weight_kg'],
        'targetWeight' => $p['target_weight_kg'] === null ? null : (float) $p['target_weight_kg'],
        'targetRank' => $p['target_rank'],
        'fitness' => $p['fitness_level'],
        'goal' => $p['main_goal'],
        'goalLabel' => MAIN_GOALS[$p['main_goal']] ?? '',
        'targets' => [
            'push' => (int) $p['target_push'],
            'sit' => (int) $p['target_sit'],
            'squat' => (int) $p['target_squat'],
            'run' => (float) $p['target_run_km'],
        ],
    ];
}

function load_weights(int $uid): array
{
    $st = db()->prepare('SELECT log_date, weight_kg FROM weight_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 30');
    $st->execute([$uid]);
    return array_reverse(array_map(fn($r) => ['date' => $r['log_date'], 'kg' => (float) $r['weight_kg']], $st->fetchAll()));
}

function log_weight(int $uid, float $kg, ?string $date = null): void
{
    $date ??= date('Y-m-d');
    db()->prepare('INSERT INTO weight_logs (user_id, log_date, weight_kg) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)')->execute([$uid, $date, $kg]);
    db()->prepare('UPDATE profiles SET weight_kg = ? WHERE user_id = ?')->execute([$kg, $uid]);
}

function valid_weight($v): ?float
{
    if (!is_numeric($v)) {
        return null;
    }
    $kg = round((float) $v, 1);
    return $kg >= 25 && $kg <= 300 ? $kg : null;
}
