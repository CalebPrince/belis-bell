<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Hardened session handling (CTL-SESS-001): HttpOnly, Secure, SameSite cookies,
 * strict mode, idle and absolute timeouts, and id rotation on privilege change.
 */
final class Session
{
    public const IDLE_SECONDS = 1800;      // 30 minutes
    public const ABSOLUTE_SECONDS = 43200; // 12 hours

    public static function start(): void
    {
        if (PHP_SAPI === 'cli') {
            // Command-line runs (tests, scripts) have no cookies: keep the session in memory.
            $_SESSION ??= [];
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = str_starts_with(Env::get('APP_URL', '') ?? '', 'https://');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name($secure ? '__Host-belis' : 'belis');
        session_save_path(self::savePath());
        session_start();

        $now = time();
        $started = (int) ($_SESSION['_started'] ?? 0);
        $seen = (int) ($_SESSION['_seen'] ?? 0);
        if ($started === 0) {
            $_SESSION['_started'] = $now;
        } elseif ($now - $started > self::ABSOLUTE_SECONDS || $now - $seen > self::IDLE_SECONDS) {
            self::destroy();
            session_start();
            $_SESSION['_started'] = $now;
        }
        $_SESSION['_seen'] = $now;
    }

    /** Call after sign-in and after any role change. */
    public static function rotate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    private static function savePath(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return is_dir($dir) && is_writable($dir) ? $dir : (string) session_save_path();
    }
}
