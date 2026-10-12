<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/state.php';
require __DIR__ . '/includes/profile.php';
require __DIR__ . '/includes/auth.php';

// Satu halaman untuk semuanya. Data awal ikut di HTML, jadi layar pertama tampil tanpa menunggu request.
$boot = build_boot(current_user());
$v = fn(string $f) => 'assets/' . $f . '?v=' . substr(md5_file(__DIR__ . '/assets/' . $f), 0, 10); // versi berubah kalau isi file berubah
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#05070f">
<title>The System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="<?= $v('style.css') ?>">
<script defer src="<?= $v('core.js') ?>"></script>
<script defer src="<?= $v('views.js') ?>"></script>
<script defer src="<?= $v('app.js') ?>"></script>
<script defer src="<?= $v('bg.js') ?>"></script>
</head>
<body>
<canvas id="bg" aria-hidden="true"></canvas>

<main class="auth" id="view-auth" hidden></main>
<div class="wrap profile-page" id="view-profile" hidden></div>

<div class="wrap" id="view-app" hidden>
  <header class="top">
    <div class="brand">[ The System ]</div>
    <div class="who">
      <span>Login sebagai <b id="whoName"></b></span>
      <span class="sync" id="sync" aria-live="polite"></span>
      <button class="btn" type="button" id="logoutBtn">Keluar</button>
    </div>
  </header>
  <div class="clock" id="clock"></div>

  <section class="win" aria-labelledby="st">
    <div class="win-title"><span id="st">Status</span></div>
    <div class="status-head">
      <div class="lvl"><b id="lvl">1</b><small>LEVEL</small></div>
      <dl class="kv">
        <dt>Nama</dt><dd><button class="name-btn" id="nameBtn" title="Ganti nama"></button></dd>
        <dt>Rank</dt><dd><span class="rank" id="rank"></span> <span class="num" id="rankHint"></span></dd>
        <dt>Job</dt><dd id="job"></dd>
        <dt>Title</dt><dd id="title"></dd>
      </dl>
    </div>
    <div class="bars">
      <div class="bar hp"><span class="lab">HP</span><div class="track"><div class="fill" id="hpF"></div></div><span class="num" id="hpN"></span></div>
      <div class="bar mp"><span class="lab">MP</span><div class="track"><div class="fill" id="mpF"></div></div><span class="num" id="mpN"></span></div>
      <div class="bar xp"><span class="lab">EXP</span><div class="track"><div class="fill" id="xpF"></div></div><span class="num" id="xpN"></span></div>
    </div>
    <div class="stats" id="stats"></div>
    <div class="points"><span>Poin stat tersedia: <b id="pts">0</b></span><span>Streak daily quest: <b id="streak">0</b> hari</span></div>
  </section>

  <section class="win" aria-labelledby="pf">
    <div class="win-title"><span id="pf">Profil</span></div>
    <dl class="prof" id="prof"></dl>
    <div class="target-rank">
      <div class="row"><span>Target rank: <b id="tRank"></b></span><span class="num" id="tRankN"></span></div>
      <div class="track"><div class="fill" id="tRankF"></div></div>
    </div>
    <div class="wt">
      <div>
        <svg id="wtChart" viewBox="0 0 300 70" preserveAspectRatio="none" aria-label="Grafik berat badan"></svg>
        <div class="wt-cap" id="wtCap"></div>
      </div>
      <form id="wtForm">
        <label for="wtInput">Catat berat hari ini</label>
        <span class="unit"><input id="wtInput" type="number" inputmode="decimal" min="25" max="300" step="0.1" required><i>kg</i></span>
        <button class="btn" type="submit">Simpan</button>
      </form>
    </div>
    <div class="prof-actions"><span id="goalTxt"></span><a class="btn" href="#profil">Edit profil</a></div>
  </section>

  <section class="win" aria-labelledby="dq">
    <div class="win-title"><span id="dq">Daily Quest</span></div>
    <p class="q-intro">Daily Quest: Persiapan Menjadi Kuat</p>
    <p class="q-sub">Selesaikan semua target sebelum tengah malam.</p>
    <div class="goals" id="goals"></div>
    <div id="dqState"></div>
    <div class="q-foot"><span>Reset dalam <b id="countdown">--:--:--</b></span><span>Hadiah: +250 EXP · +3 poin stat · Full Recovery</span></div>
  </section>

  <section class="win" aria-labelledby="sq">
    <div class="win-title"><span id="sq">Quest Tambahan</span></div>
    <form class="add" id="addForm">
      <input id="qTitle" placeholder="Contoh: Baca buku 20 halaman" maxlength="80" required aria-label="Nama quest">
      <select id="qTier" aria-label="Tingkat kesulitan">
        <option value="E">E · 20</option><option value="D">D · 40</option><option value="C" selected>C · 70</option>
        <option value="B">B · 110</option><option value="A">A · 160</option><option value="S">S · 250</option>
      </select>
      <select id="qStat" aria-label="Stat yang dilatih">
        <option value="str">STR</option><option value="agi">AGI</option><option value="vit">VIT</option>
        <option value="int" selected>INT</option><option value="per">PER</option>
      </select>
      <button class="btn" type="submit">Tambah</button>
    </form>
    <ul class="list" id="quests"></ul>
  </section>

  <section class="win" aria-labelledby="nt">
    <div class="win-title"><span id="nt">Notifikasi</span></div>
    <div class="rem-row">
      <label><input type="checkbox" id="remOn"> Ingatkan daily quest yang belum selesai</label>
    </div>
    <div class="times" id="times"></div>
    <form class="rem-row" id="timeForm">
      <input type="time" id="newTime" value="21:00" aria-label="Jam pengingat baru" required>
      <button class="btn" type="submit">Tambah jam</button>
      <button class="btn" type="button" id="testNotif">Tes notifikasi</button>
    </form>
    <div class="rem-row">
      <button class="btn" type="button" id="permBtn">Aktifkan notifikasi browser</button>
      <p class="perm" id="permState"></p>
    </div>
  </section>

  <section class="win" aria-labelledby="hs">
    <div class="win-title"><span id="hs">Riwayat 14 Hari</span></div>
    <div class="history" id="history"></div>
    <p class="hist-sum" id="histSum"></p>
  </section>

  <section class="win" aria-labelledby="lg">
    <div class="win-title"><span id="lg">Log Sistem</span></div>
    <ul class="log" id="log"></ul>
  </section>

  <div class="foot" id="foot">
    <button class="btn danger" id="resetBtn">Reset Player</button>
  </div>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>

<div class="modal" id="modal" hidden role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="win">
    <div class="win-title"><span id="mTag">Notifikasi</span></div>
    <h3 id="mTitle"></h3>
    <p id="mBody"></p>
    <div class="rew" id="mRew"></div>
    <div id="mExtra"></div>
    <button class="btn" id="mOk">Terima</button>
  </div>
</div>

<script>window.BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
</body>
</html>
