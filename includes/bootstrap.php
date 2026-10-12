<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

require __DIR__ . '/session.php';

/**
 * Konfigurasi database, urutan prioritas:
 * 1. includes/config.php (XAMPP / hosting biasa)
 * 2. environment variable DB_HOST, DB_USER, ... (Vercel)
 * 3. includes/config.example.php (default XAMPP)
 */
function config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    if (is_file(__DIR__ . '/config.php')) {
        return $c = require __DIR__ . '/config.php';
    }
    if (getenv('DB_HOST')) {
        $flag = fn(string $k) => in_array(strtolower((string) getenv($k)), ['1', 'true', 'yes', 'on'], true);
        return $c = [
            'db_host' => getenv('DB_HOST'),
            'db_port' => (int) (getenv('DB_PORT') ?: 3306),
            'db_name' => getenv('DB_NAME') ?: 'system_hunter',
            'db_user' => (string) getenv('DB_USER'),
            'db_pass' => (string) getenv('DB_PASS'),
            'db_ssl' => $flag('DB_SSL'),
            'db_ssl_ca' => getenv('DB_SSL_CA') ?: null,
            'debug' => $flag('APP_DEBUG'),
        ];
    }
    return $c = require __DIR__ . '/config.example.php';
}

/** Detail error hanya ditampilkan kalau 'debug' => true di config. Di hosting biarkan false. */
function is_debug(): bool
{
    return (bool) (config()['debug'] ?? false);
}

ini_set('display_errors', is_debug() ? '1' : '0');
ini_set('log_errors', '1');

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
    if (!empty($c['db_ssl'])) {
        // Database cloud (TiDB Cloud, Aiven, dll.) mewajibkan koneksi terenkripsi.
        $ca = $c['db_ssl_ca'] ?? null;
        if ($ca && !str_starts_with($ca, '/') && !preg_match('/^[A-Za-z]:/', $ca)) {
            $ca = dirname(__DIR__) . '/' . $ca; // path relatif dari folder proyek
        }
        $ca ??= current(array_filter([
            '/etc/pki/tls/certs/ca-bundle.crt',   // Vercel / Amazon Linux
            '/etc/ssl/certs/ca-certificates.crt', // Debian / Ubuntu
            '/etc/ssl/cert.pem',                  // macOS / Alpine
        ], 'is_file')) ?: null;
        if ($ca) {
            $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
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
    ensure_schema($pdo, $c['db_name']);
    return $pdo;
}

/**
 * Jalankan schema.sql lagi kalau isinya berubah (mis. ada tabel baru).
 * Versi yang sudah dijalankan dicatat di tabel meta; semua perintahnya "IF NOT EXISTS".
 */
function ensure_schema(PDO $pdo, string $dbName): void
{
    $hash = sha1_file(dirname(__DIR__) . '/schema.sql');
    try {
        $current = $pdo->query("SELECT v FROM meta WHERE k = 'schema'")->fetchColumn();
    } catch (PDOException $e) {
        $current = null; // tabel meta belum ada
    }
    if ($current !== $hash) {
        run_schema($pdo, $dbName, false);
        $pdo->prepare("REPLACE INTO meta (k, v) VALUES ('schema', ?)")->execute([$hash]);
    }
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

start_session();
