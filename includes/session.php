<?php
// Sesi login disimpan di tabel `sessions`, bukan file.
// Di serverless (Vercel) tiap request bisa jalan di server berbeda, jadi sesi berbasis file akan hilang.

const SESSION_LIFETIME = 60 * 60 * 24 * 30; // tetap login 30 hari

final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** Tolak ID sesi yang tidak pernah dibuat server (strict mode). */
    public function validateId(string $id): bool
    {
        $st = db()->prepare('SELECT 1 FROM sessions WHERE id = ? AND expires_at > ?');
        $st->execute([$id, time()]);
        return (bool) $st->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $st = db()->prepare('SELECT data FROM sessions WHERE id = ? AND expires_at > ?');
        $st->execute([$id, time()]);
        return (string) ($st->fetchColumn() ?: '');
    }

    public function write(string $id, string $data): bool
    {
        db()->prepare('INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)')
            ->execute([$id, $data, time() + SESSION_LIFETIME]);
        return true;
    }

    public function destroy(string $id): bool
    {
        db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = db()->prepare('DELETE FROM sessions WHERE expires_at < ?');
        $st->execute([time()]);
        return $st->rowCount();
    }
}

function start_session(): void
{
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https, // cookie login hanya dikirim lewat HTTPS saat situs memakai HTTPS
    ]);
    session_name('system_hunter');
    session_set_save_handler(new DbSessionHandler(), true);
    session_start();
}
