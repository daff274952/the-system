<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/state.php';
require __DIR__ . '/includes/profile.php';
require __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body = [];
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        out(['error' => 'Data tidak valid.'], 400);
    }
    if (!check_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        out(['error' => 'Token keamanan tidak valid. Muat ulang halaman.', 'csrf' => csrf_token()], 419);
    }
} elseif ($method !== 'GET') {
    out(['error' => 'Metode tidak didukung.'], 405);
}
$action = $method === 'GET' ? ($_GET['action'] ?? 'boot') : ($body['action'] ?? '');

// Aksi yang boleh tanpa login.
switch ($action) {
    case 'boot':
        out(['boot' => build_boot(current_user())]);
    case 'login':
        $errors = attempt_login(trim((string) ($body['username'] ?? '')), (string) ($body['password'] ?? ''));
        $errors ? out(['errors' => $errors], 422) : out(['boot' => build_boot(current_user())]);
    case 'register':
        $errors = attempt_register(trim((string) ($body['username'] ?? '')), (string) ($body['password'] ?? ''), (string) ($body['confirm'] ?? ''));
        $errors ? out(['errors' => $errors], 422) : out(['boot' => build_boot(current_user())]);
    case 'logout':
        $_SESSION = [];
        session_regenerate_id(true);
        out(['csrf' => csrf_token()]);
}

$user = current_user();
if (!$user) {
    out(['error' => 'Sesi berakhir. Silakan login lagi.'], 401);
}
$uid = (int) $user['id'];

switch ($action) {
    case 'save':
        if (!is_array($body['state'] ?? null)) {
            out(['error' => 'State kosong.'], 400);
        }
        save_state($uid, $body['state']);
        out(['ok' => true, 'history' => load_history($uid)]);
    case 'profile':
        $errors = save_profile($uid, is_array($body['profile'] ?? null) ? $body['profile'] : []);
        if ($errors) {
            out(['errors' => $errors], 422);
        }
        $st = db()->prepare('SELECT name FROM hunters WHERE user_id = ?');
        $st->execute([$uid]);
        out(['profile' => load_profile($uid), 'weights' => load_weights($uid), 'name' => $st->fetchColumn(), 'newPlayer' => consume_new_player()]);
    case 'weight':
        $kg = valid_weight($body['kg'] ?? null);
        if ($kg === null) {
            out(['error' => 'Berat badan harus 25–300 kg.'], 422);
        }
        log_weight($uid, $kg);
        out(['ok' => true, 'profile' => load_profile($uid), 'weights' => load_weights($uid)]);
    case 'reset':
        reset_player($uid, $user['username']);
        out(['ok' => true]);
    default:
        out(['error' => 'Aksi tidak dikenal.'], 400);
}
