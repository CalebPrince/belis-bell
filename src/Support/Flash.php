<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Session;

/** One-time messages shown on the next page ("Added to your cart"). Plain text only; templates escape it. */
final class Flash
{
    public static function add(string $message): void
    {
        Session::start();
        $_SESSION['flash'][] = mb_substr($message, 0, 200);
    }

    /** @return list<string> */
    public static function take(): array
    {
        if (!Session::hasCookie()) {
            return [];
        }
        Session::start();
        $all = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($all) ? array_values(array_filter($all, 'is_string')) : [];
    }
}
