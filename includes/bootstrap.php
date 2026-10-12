<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('system_hunter');
session_start();

set_exception_handler(function (Throwable $e) {
    if ($e instanceof PDOException) {
        fatal_db($e);
    }
    throw $e;
});

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $c = require __DIR__ . (is_file(__DIR__ . '/config.php') ? '/config.php' : '/config.example.php');
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
        $pdo = new PDO($base, $c['db_user'], $c['db_pass'], $opts);
        run_schema($pdo, $c['db_name']);
    }
    // Versi schema.sql berubah (tabel baru) -> jalankan lagi; semua perintahnya "IF NOT EXISTS".
    $version = (string) filemtime(dirname(__DIR__) . '/schema.sql');
    if (($_SESSION['schema_v'] ?? '') !== $version) {
        run_schema($pdo, $c['db_name']);
        $_SESSION['schema_v'] = $version;
    }
    return $pdo;
}

function run_schema(PDO $pdo, string $dbName): void
{
    $sql = file_get_contents(dirname(__DIR__) . '/schema.sql');
    $sql = str_replace('system_hunter', $dbName, $sql);
    foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
        $pdo->exec($stmt);
    }
}

function fatal_db(PDOException $e): never
{
    http_response_code(503);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', 'api.php')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database tidak bisa dihubungi. Pastikan MySQL di XAMPP sudah Start.']);
        exit;
    }
    $msg = htmlspecialchars($e->getMessage());
    echo "<!doctype html><meta charset=utf-8><title>Database Error</title>
<body style='background:#05070f;color:#dbe9ff;font-family:system-ui;padding:40px'>
<h2 style='color:#ff4d6d'>Sistem tidak bisa terhubung ke database</h2>
<p>Buka <b>XAMPP Control Panel</b>, lalu klik <b>Start</b> pada <b>MySQL</b>. Setelah itu muat ulang halaman ini.</p>
<p>Kalau MySQL kamu memakai password, isi di <code>includes/config.php</code>.</p>
<p style='color:#7f93b8;font-size:13px'>Detail: {$msg}</p></body>";
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
