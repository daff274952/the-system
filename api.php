<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/state.php';
require __DIR__ . '/includes/profile.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
if (!$user) {
    out(['error' => 'Sesi berakhir. Silakan login lagi.'], 401);
}
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    out(['state' => load_state($uid), 'history' => load_history($uid)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['error' => 'Metode tidak didukung.'], 405);
}
if (!check_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    out(['error' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    out(['error' => 'Data tidak valid.'], 400);
}

switch ($body['action'] ?? 'save') {
    case 'save':
        if (!is_array($body['state'] ?? null)) {
            out(['error' => 'State kosong.'], 400);
        }
        save_state($uid, $body['state']);
        out(['ok' => true, 'history' => load_history($uid)]);
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
