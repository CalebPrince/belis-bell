<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Session;

/**
 * One-time messages shown on the next page. Plain text only; templates escape it.
 * add() is a cart message (the page offers a View cart link); notice() is a plain message.
 */
final class Flash
{
    public static function add(string $message): void
    {
        self::push($message, true);
    }

    public static function notice(string $message): void
    {
        self::push($message, false);
    }

    /** @return list<array{text:string,cart:bool}> */
    public static function take(): array
    {
        if (!Session::hasCookie()) {
            return [];
        }
        Session::start();
        $all = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        $out = [];
        foreach (is_array($all) ? $all : [] as $m) {
            if (is_array($m) && is_string($m['text'] ?? null)) {
                $out[] = ['text' => $m['text'], 'cart' => (bool) ($m['cart'] ?? false)];
            }
        }
        return $out;
    }

    private static function push(string $message, bool $cart): void
    {
        Session::start();
        $_SESSION['flash'][] = ['text' => mb_substr($message, 0, 200), 'cart' => $cart];
    }
}
