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

/** Adds a fixed boolean attribute. Only names on the allowlist are accepted, so this can never print user text. */
function flag(bool $on, string $attribute): string
{
    $allowed = ['selected', 'checked', 'aria-current="page"'];
    if (!in_array($attribute, $allowed, true)) {
        throw new InvalidArgumentException('Attribute not allowed');
    }
    return $on ? ' ' . $attribute : '';
}

/** Path plus query string. Null values are dropped. The caller wraps the result in e(). */
function query_url(string $path, array $params): string
{
    $params = array_filter($params, static fn ($v): bool => $v !== null && $v !== '');
    return $params === [] ? $path : $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/** Responsive image or neutral placeholder for an owner-supplied photo (PG-035). Output is escaped. */
function image_html(string $slot, string $alt, string $sizes = '100vw', array $opts = []): string
{
    return Belis\Support\Images::html($slot, $alt, $sizes, $opts);
}

/** Path of the current request, for marking the active navigation link. */
function current_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

/**
 * Inline SVG icon from a fixed allowlist (drawn in the Lucide style). Output is static markup, so it is
 * safe to print. The class is escaped.
 */
function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'cart' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
        'menu' => '<path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'headset' => '<path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/>',
        'award' => '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>',
        'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'house' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    ];
    if (!isset($paths[$name])) {
        throw new InvalidArgumentException('Unknown icon');
    }
    return '<svg class="' . e($class) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/** @return list<array{label:string,href:string}> */
function site_nav(): array
{
    return Belis\Support\Site::nav();
}

/** @return array{address:?string,phone:?string,email:?string,hours:?string,sample:bool} */
function site_contact(): array
{
    return Belis\Support\Site::contact();
}

/** @return list<array{label:string,url:string}> */
function site_socials(): array
{
    return Belis\Support\Site::socials();
}

function image_exists(string $slot): bool
{
    return Belis\Support\Images::exists($slot);
}

function is_mock_mode(): bool
{
    return Env::bool('MOCK_DATA');
}
