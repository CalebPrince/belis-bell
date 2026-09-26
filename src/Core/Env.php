<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Reads configuration from an environment file that lives above the web root.
 * Values are never evaluated as code. The file is never committed (see .gitignore).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $useProcessEnv = true;

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            $len = strlen($val);
            if ($len >= 2 && (($val[0] === '"' && $val[$len - 1] === '"') || ($val[0] === "'" && $val[$len - 1] === "'"))) {
                $val = substr($val, 1, -1);
            }
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) === 1) {
                self::$vars[$key] = $val;
            }
        }
    }

    /** Replace all values and ignore the process environment. Used by tests. */
    public static function fake(array $vars): void
    {
        self::$vars = array_map('strval', $vars);
        self::$useProcessEnv = false;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        if (self::$useProcessEnv) {
            $v = getenv($key);
            if ($v !== false) {
                return $v;
            }
        }
        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}
