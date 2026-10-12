<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/profile.php';

$user = require_login();
$uid = (int) $user['id'];
$existing = load_profile($uid);
$isSetup = $existing === null;

$st = db()->prepare('SELECT name FROM hunters WHERE user_id = ?');
$st->execute([$uid]);
$hunterName = $st->fetchColumn() ?: $user['username'];

// Nilai awal form: data tersimpan, atau preset pemula untuk Player baru.
$f = [
    'name' => $hunterName,
    'gender' => $existing['gender'] ?? '',
    'birth_date' => $existing['birthDate'] ?? '',
    'height' => $existing['height'] ?? '',
    'weight' => $existing['weight'] ?? '',
    'target_weight' => $existing['targetWeight'] ?? '',
    'target_rank' => $existing['targetRank'] ?? 'C',
    'fitness' => $existing['fitness'] ?? 'pemula',
    'goal' => $existing['goal'] ?? 'disiplin',
    'push' => $existing['targets']['push'] ?? FITNESS_PRESETS['pemula']['push'],
    'sit' => $existing['targets']['sit'] ?? FITNESS_PRESETS['pemula']['sit'],
    'squat' => $existing['targets']['squat'] ?? FITNESS_PRESETS['pemula']['squat'],
    'run' => $existing['targets']['run'] ?? FITNESS_PRESETS['pemula']['run'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) {
        $f[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $errors['form'] = 'Sesi kedaluwarsa. Coba simpan lagi.';
    }
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
    if ($f['target_weight'] !== '') {
        $targetWeight = valid_weight($f['target_weight']);
        if ($targetWeight === null) {
            $errors['target_weight'] = 'Target berat 25–300 kg, atau kosongkan.';
        }
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

    if (!$errors) {
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
        if ($isSetup || abs($weight - (float) $existing['weight']) > 0.01) {
            log_weight($uid, $weight);
        }
        $pdo->prepare('UPDATE hunters SET name = ? WHERE user_id = ?')->execute([$f['name'], $uid]);
        $pdo->commit();
        header('Location: index.php');
        exit;
    }
}
$csrf = csrf_token();
$err = fn($k) => isset($errors[$k]) ? '<span class="field-err">' . e($errors[$k]) . '</span>' : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>The System</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<canvas id="bg" aria-hidden="true"></canvas>
<div class="wrap profile-page">
  <header class="top">
    <div class="brand">[ The System ]</div>
    <?php if (!$isSetup): ?><a class="btn" href="index.php">Kembali</a><?php endif; ?>
  </header>

  <form method="post" action="profile.php" class="win pf" novalidate>
    <div class="win-title"><span><?= $isSetup ? 'Registrasi Profil' : 'Edit Profil' ?></span></div>
    <?php if ($isSetup): ?>
      <p class="q-intro">Sistem membutuhkan data tubuhmu.</p>
      <p class="q-sub">Data ini dipakai untuk menyesuaikan daily quest dan melacak perkembanganmu. Bisa diubah kapan saja.</p>
    <?php endif; ?>
    <?php if (isset($errors['form'])): ?><div class="warn" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

    <fieldset>
      <legend>Identitas</legend>
      <div class="pf-grid">
        <label class="pf-wide" for="name">Nama Player
          <input id="name" name="name" value="<?= e((string) $f['name']) ?>" maxlength="30" required>
          <?= $err('name') ?>
        </label>
        <div class="pf-field">
          <span class="pf-label">Jenis kelamin</span>
          <div class="seg">
            <?php foreach (['L' => 'Laki-laki', 'P' => 'Perempuan', '' => 'Tidak disebut'] as $v => $l): ?>
              <label><input type="radio" name="gender" value="<?= $v ?>" <?= $f['gender'] === $v ? 'checked' : '' ?>><span><?= $l ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <label for="birth_date"><span>Tanggal lahir <small>(opsional)</small></span>
          <input id="birth_date" name="birth_date" type="date" value="<?= e((string) $f['birth_date']) ?>" max="<?= date('Y-m-d') ?>">
          <?= $err('birth_date') ?>
        </label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Tubuh</legend>
      <div class="pf-grid three">
        <label for="height">Tinggi badan
          <span class="unit"><input id="height" name="height" type="number" inputmode="numeric" min="100" max="250" step="1" value="<?= e((string) $f['height']) ?>" placeholder="170" required><i>cm</i></span>
          <?= $err('height') ?>
        </label>
        <label for="weight">Berat badan
          <span class="unit"><input id="weight" name="weight" type="number" inputmode="decimal" min="25" max="300" step="0.1" value="<?= e((string) $f['weight']) ?>" placeholder="65" required><i>kg</i></span>
          <?= $err('weight') ?>
        </label>
        <label for="target_weight"><span>Target berat <small>(opsional)</small></span>
          <span class="unit"><input id="target_weight" name="target_weight" type="number" inputmode="decimal" min="25" max="300" step="0.1" value="<?= e((string) $f['target_weight']) ?>" placeholder="60"><i>kg</i></span>
          <?= $err('target_weight') ?>
        </label>
      </div>
      <div class="bmi" id="bmi" aria-live="polite">Isi tinggi dan berat untuk melihat BMI.</div>
    </fieldset>

    <fieldset>
      <legend>Target</legend>
      <div class="pf-field">
        <span class="pf-label">Target rank</span>
        <div class="ranks">
          <?php foreach (RANK_LEVELS as $r => $lv): ?>
            <label class="rank-opt r-<?= $r ?>"><input type="radio" name="target_rank" value="<?= $r ?>" <?= $f['target_rank'] === $r ? 'checked' : '' ?>><span><b><?= $r ?></b><small>Lv <?= $lv ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <?= $err('target_rank') ?>
      </div>
      <label for="goal">Tujuan utama
        <select id="goal" name="goal">
          <?php foreach (MAIN_GOALS as $v => $l): ?>
            <option value="<?= $v ?>" <?= $f['goal'] === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </fieldset>

    <fieldset>
      <legend>Daily Quest</legend>
      <div class="pf-field">
        <span class="pf-label">Tingkat kebugaran</span>
        <div class="levels">
          <?php foreach (FITNESS_PRESETS as $v => $p): ?>
            <label class="lvl-opt"><input type="radio" name="fitness" value="<?= $v ?>" <?= $f['fitness'] === $v ? 'checked' : '' ?>
              data-push="<?= $p['push'] ?>" data-sit="<?= $p['sit'] ?>" data-squat="<?= $p['squat'] ?>" data-run="<?= $p['run'] ?>">
              <span><b><?= $p['label'] ?></b><small><?= $p['desc'] ?></small><em><?= $p['push'] ?> rep · <?= $p['run'] ?> km</em></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="pf-grid four">
        <label for="push">Push-up<span class="unit"><input id="push" name="push" type="number" min="1" max="1000" value="<?= e((string) $f['push']) ?>"><i>×</i></span></label>
        <label for="sit">Sit-up<span class="unit"><input id="sit" name="sit" type="number" min="1" max="1000" value="<?= e((string) $f['sit']) ?>"><i>×</i></span></label>
        <label for="squat">Squat<span class="unit"><input id="squat" name="squat" type="number" min="1" max="1000" value="<?= e((string) $f['squat']) ?>"><i>×</i></span></label>
        <label for="run">Lari<span class="unit"><input id="run" name="run" type="number" min="0.5" max="50" step="0.5" value="<?= e((string) $f['run']) ?>"><i>km</i></span></label>
      </div>
      <?= $err('targets') ?>
      <p class="q-sub pf-note">Target terisi otomatis dari tingkat kebugaran. Kamu boleh mengubah angkanya sendiri.</p>
    </fieldset>

    <button class="btn pf-submit" type="submit"><?= $isSetup ? 'Simpan & Mulai' : 'Simpan Profil' ?></button>
  </form>
</div>
<script src="assets/bg.js"></script>
<script>
(function(){
  const $=id=>document.getElementById(id);
  function bmi(){
    const h=parseFloat($("height").value)/100,w=parseFloat($("weight").value),t=parseFloat($("target_weight").value);
    if(!(h>=1&&h<=2.5&&w>=25&&w<=300)){$("bmi").textContent="Isi tinggi dan berat untuk melihat BMI.";$("bmi").dataset.cat="";return}
    const b=w/(h*h);
    // Klasifikasi IMT Kemenkes RI
    const cat=b<18.5?["kurus","Kurus"]:b<=25?["normal","Normal"]:b<=27?["gemuk","Gemuk"]:["obesitas","Obesitas"];
    const lo=(18.5*h*h).toFixed(1),hi=(25*h*h).toFixed(1);
    let txt="BMI "+b.toFixed(1)+" · "+cat[1]+". Berat ideal untuk tinggimu: "+lo+"–"+hi+" kg.";
    if(t>=25){const d=(t-w);txt+=" Target: "+(d<0?"turun "+(-d).toFixed(1):d>0?"naik "+d.toFixed(1):"pertahankan")+(d?" kg.":".")}
    $("bmi").textContent=txt;$("bmi").dataset.cat=cat[0];
  }
  ["height","weight","target_weight"].forEach(id=>$(id).addEventListener("input",bmi));
  document.querySelectorAll('input[name="fitness"]').forEach(r=>r.addEventListener("change",()=>{
    ["push","sit","squat","run"].forEach(k=>$(k).value=r.dataset[k]);
  }));
  bmi();
})();
</script>
</body>
</html>
