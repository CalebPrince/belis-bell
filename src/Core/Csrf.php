<?php
declare(strict_types=1);

namespace Belis\Core;

/** One CSRF layer for every state-changing request (CTL-SESS-001, CTL-FW-001). */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(Request $request): bool
    {
        Session::start();
        $expected = $_SESSION['_csrf'] ?? null;
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $given = $request->post['_csrf'] ?? $request->header('x-csrf-token');
        return is_string($given) && hash_equals($expected, $given);
    }
}
