<?php
declare(strict_types=1);

use Belis\Core\Csrf;
use Belis\Core\Env;

/** Escape for HTML text and attributes. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Output that is already safe HTML. Allowed only in templates/layout.php. */
function raw(string $html): string
{
    return $html;
}

function csrf_field(): string
{
    return Csrf::field();
}

/** URL of a built asset with a cache-busting version, escaped for output. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = dirname(__DIR__) . '/public/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return e('/assets/' . $path . '?v=' . $v);
}

/** Ghana cedis from whole pesewas. No floating point is used for money. */
function money(int $pesewas): string
{
    $cedis = intdiv($pesewas, 100);
    $rest = str_pad((string) ($pesewas % 100), 2, '0', STR_PAD_LEFT);
    return 'GH₵ ' . number_format($cedis, 0, '.', ',') . '.' . $rest;
}

function is_mock_mode(): bool
{
    return Env::bool('MOCK_DATA');
}
