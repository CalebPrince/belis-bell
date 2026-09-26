<?php
declare(strict_types=1);

namespace Belis\Support;

/**
 * Responsive image markup for the owner's photos (PG-035).
 * A slot is a path without extension, for example "home/hero" or "products/sample-bleach-5l/main".
 * scripts/build-images.mjs writes public/assets/img/<slot>-<width>.webp. When no file exists the
 * slot renders a neutral placeholder, so pages keep their structure until the photo arrives.
 * Slot names may come from the database, so they are validated before touching the file system.
 */
final class Images
{
    private static ?string $root = null;
    /** @var array<string,array{int,int}> */
    private static array $dimensionCache = [];

    /** Tests point this at a temporary folder. */
    public static function useRoot(?string $root): void
    {
        self::$root = $root;
        self::$dimensionCache = [];
    }

    public static function isValidSlot(string $slot): bool
    {
        return preg_match('#^[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*$#', $slot) === 1 && strlen($slot) <= 190;
    }

    /**
     * @param array{class?:string,priority?:bool,decorative?:bool,placeholder_class?:string,placeholder_text?:string} $opts
     * @return string HTML with every attribute escaped
     */
    public static function html(string $slot, string $alt, string $sizes = '100vw', array $opts = []): string
    {
        $variants = self::isValidSlot($slot) ? self::variants($slot) : [];
        $class = $opts['class'] ?? '';
        $decorative = $opts['decorative'] ?? false;

        if ($variants === []) {
            $label = $decorative ? ' aria-hidden="true"' : ' role="img" aria-label="' . self::esc($alt) . '"';
            $text = $opts['placeholder_text'] ?? '';
            return '<div class="ph ' . self::esc($opts['placeholder_class'] ?? '') . '"' . $label . '>'
                . ($text === '' ? '' : '<span>' . self::esc($text) . '</span>') . '</div>';
        }

        ksort($variants);
        $widths = array_keys($variants);
        $default = $widths[(int) floor((count($widths) - 1) / 2)];
        [$w, $h] = self::dimensions($variants[end($widths)], end($widths));
        $srcset = [];
        foreach ($variants as $width => $file) {
            $srcset[] = self::url($slot, $width, $file) . ' ' . $width . 'w';
        }
        $priority = ($opts['priority'] ?? false) === true;
        return '<img src="' . self::url($slot, $default, $variants[$default]) . '"'
            . ' srcset="' . implode(', ', $srcset) . '"'
            . ' sizes="' . self::esc($sizes) . '"'
            . ' width="' . $w . '" height="' . $h . '"'
            . ' alt="' . ($decorative ? '' : self::esc($alt)) . '"'
            . ' loading="' . ($priority ? 'eager' : 'lazy') . '" decoding="async"'
            . ($priority ? ' fetchpriority="high"' : '')
            . ($class === '' ? '' : ' class="' . self::esc($class) . '"') . '>';
    }

    public static function exists(string $slot): bool
    {
        return self::isValidSlot($slot) && self::variants($slot) !== [];
    }

    /** @return array<int,string> width => absolute file path */
    private static function variants(string $slot): array
    {
        $found = [];
        foreach (glob(self::root() . '/' . $slot . '-*.webp') ?: [] as $file) {
            if (preg_match('/-(\d{2,4})\.webp$/', $file, $m) === 1 && is_file($file)) {
                $found[(int) $m[1]] = $file;
            }
        }
        return $found;
    }

    /** @return array{int,int} */
    private static function dimensions(string $file, int $width): array
    {
        if (!isset(self::$dimensionCache[$file])) {
            $info = @getimagesize($file);
            self::$dimensionCache[$file] = is_array($info) ? [(int) $info[0], (int) $info[1]] : [$width, (int) round($width * 0.75)];
        }
        return self::$dimensionCache[$file];
    }

    private static function url(string $slot, int $width, string $file): string
    {
        return self::esc('/assets/img/' . $slot . '-' . $width . '.webp?v=' . (string) @filemtime($file));
    }

    private static function root(): string
    {
        return self::$root ?? dirname(__DIR__, 2) . '/public/assets/img';
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
