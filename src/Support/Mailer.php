<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Env;

/**
 * Outgoing email. Only the log driver exists: it writes messages to storage/logs/mail.log so codes can be read
 * during local development and nothing leaves the machine. Real sending (SMTP) is NOT BUILT; the environment
 * guard refuses the log driver in production, and choosing any other driver fails closed.
 */
class Mailer
{
    private static ?self $override = null;

    /** Tests only. */
    public static function useForTests(?self $mailer): void
    {
        self::$override = $mailer;
    }

    public static function fromEnv(): self
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $driver = Env::get('MAIL_DRIVER', 'log') ?? 'log';
        if ($driver !== 'log') {
            throw new \RuntimeException('Mail driver is not built');
        }
        return new self();
    }

    public function send(string $to, string $subject, string $body): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $entry = sprintf("[%s]\nTo: %s\nSubject: %s\n\n%s\n\n---\n", gmdate('c'), $to, $subject, $body);
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
