<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Session;

/**
 * "Confirmed with a fresh emailed code" for sensitive staff actions (price changes, settings). The confirmation
 * belongs to one person and lasts 10 minutes. It is kept in the server-side session and dies at sign-out.
 */
final class StepUp
{
    public const FRESH_SECONDS = 600;

    public static function fresh(int $uid): bool
    {
        Session::start();
        $s = $_SESSION['stepup'] ?? null;
        return is_array($s) && (int) ($s['uid'] ?? 0) === $uid && time() - (int) ($s['at'] ?? 0) <= self::FRESH_SECONDS;
    }

    public static function mark(int $uid): void
    {
        Session::start();
        $_SESSION['stepup'] = ['uid' => $uid, 'at' => time()];
    }

    /** Only admin pages that ask for a confirmation may be returned to. */
    public static function safeNext(mixed $next): string
    {
        return is_string($next) && preg_match('#^/admin/(products(/[0-9]+|/new)?|settings|staff)$#', $next) === 1 ? $next : '/admin';
    }
}
