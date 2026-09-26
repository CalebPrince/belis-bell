<?php
declare(strict_types=1);

namespace Belis\Support;

/**
 * Application log written above the web root (CTL-DATA-001). Emails, phone numbers
 * and long digit runs are redacted, and secrets must never be passed in.
 */
final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function redact(string $text): string
    {
        $text = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $text) ?? $text;
        $text = preg_replace('/\+?\d[\d\s().-]{7,}\d/', '[number]', $text) ?? $text;
        return $text;
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $line = sprintf(
            "[%s] %s %s %s\n",
            gmdate('c'),
            $level,
            self::redact($message),
            $context === [] ? '' : self::redact((string) json_encode($context, JSON_UNESCAPED_SLASHES)),
        );
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
