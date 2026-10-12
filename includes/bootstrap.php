<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

function config(): array
{
    static $c = null;
    return $c ??= require __DIR__ . (is_file(__DIR__ . '/config.php') ? '/config.php' : '/config.example.php');
}

/** Detail error hanya ditampilkan kalau 'debug' => true di config. Di hosting biarkan false. */
function is_debug(): bool
{
    return (bool) (config()['debug'] ?? false);
}

ini_set('display_errors', is_debug() ? '1' : '0');
ini_set('log_errors', '1');

$https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https, // cookie login hanya dikirim lewat HTTPS saat situs memakai HTTPS
]);
session_name('system_hunter');
session_start();

set_exception_handler(function (Throwable $e) {
    if ($e instanceof PDOException) {
        fatal_db($e);
    }
    error_log('[The System] ' . $e);
    http_response_code(500);
    $detail = is_debug() ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null;
    if (wants_json()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Terjadi kesalahan di server. Coba lagi sebentar lagi.', 'detail' => $detail]);
    } else {
        error_page('Terjadi kesalahan di server', '<p>Coba muat ulang halaman ini sebentar lagi.</p>', $detail);
    }
    exit;
});

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', 'api.php');
}

function error_page(string $title, string $bodyHtml, ?string $detail): void
{
    echo "<!doctype html><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'><title>The System</title>
<body style='background:#05070f;color:#dbe9ff;font-family:system-ui;padding:40px 16px;max-width:640px;margin:0 auto'>
<h2 style='color:#ff4d6d'>" . e($title) . "</h2>{$bodyHtml}"
        . ($detail !== null ? "<p style='color:#7f93b8;font-size:13px'>Detail: " . e($detail) . '</p>' : '')
        . '</body>';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $c = config();
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $base = "mysql:host={$c['db_host']};port={$c['db_port']};charset=utf8mb4";
    try {
        $pdo = new PDO($base . ";dbname={$c['db_name']}", $c['db_user'], $c['db_pass'], $opts);
    } catch (PDOException $e) {
        if ((int) $e->getCode() !== 1049) { // 1049 = database belum ada
            fatal_db($e);
        }
        // Database belum ada (XAMPP lokal): buat sekalian dengan tabelnya.
        $pdo = new PDO($base, $c['db_user'], $c['db_pass'], $opts);
        run_schema($pdo, $c['db_name'], true);
    }
    // Versi schema.sql berubah (tabel baru) -> jalankan lagi; semua perintahnya "IF NOT EXISTS".
    $version = (string) filemtime(dirname(__DIR__) . '/schema.sql');
    if (($_SESSION['schema_v'] ?? '') !== $version) {
        run_schema($pdo, $c['db_name'], false);
        $_SESSION['schema_v'] = $version;
    }
    return $pdo;
}

/**
 * Jalankan schema.sql. CREATE DATABASE / USE hanya dipakai saat database belum ada;
 * di shared hosting user database biasanya tidak punya izin CREATE DATABASE.
 */
function run_schema(PDO $pdo, string $dbName, bool $createDatabase): void
{
    $sql = file_get_contents(dirname(__DIR__) . '/schema.sql');
    $sql = str_replace('system_hunter', $dbName, $sql);
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
        if (!$createDatabase && preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $stmt)) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

function fatal_db(PDOException $e): never
{
    error_log('[The System] Database: ' . $e->getMessage());
    http_response_code(503);
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $hint = $local
        ? 'Pastikan MySQL di XAMPP Control Panel sudah Start, lalu muat ulang halaman.'
        : 'Server sedang bermasalah. Coba lagi beberapa saat lagi.';
    $detail = is_debug() ? $e->getMessage() : null;
    if (wants_json()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database tidak bisa dihubungi. ' . $hint, 'detail' => $detail]);
        exit;
    }
    error_page(
        'Sistem tidak bisa terhubung ke database',
        '<p>' . e($hint) . '</p>' . ($local ? '<p>Kalau MySQL kamu memakai password, isi di <code>includes/config.php</code>.</p>' : ''),
        $detail
    );
    exit;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = db()->prepare('SELECT id, username FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    return $st->fetch() ?: null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}
