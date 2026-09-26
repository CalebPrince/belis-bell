<?php
declare(strict_types=1);

namespace Belis\Support;

/**
 * Outgoing email. Two methods, chosen in the owner Settings page (or the environment file):
 * "log" writes messages to storage/logs/mail.log for local development, so codes can be read and nothing leaves the
 * machine (the guard refuses it in production); "smtp" sends through the configured mail server (see Smtp, which has
 * not yet been run against a real server).
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
        $driver = Settings::get('MAIL_DRIVER', 'log') ?? 'log';
        if ($driver === 'smtp') {
            $host = Settings::get('SMTP_HOST', '') ?? '';
            $user = Settings::get('SMTP_USER', '') ?? '';
            $pass = Settings::get('SMTP_PASSWORD', '') ?? '';
            $from = Settings::get('MAIL_FROM_ADDRESS', '') ?? '';
            if ($host === '' || $user === '' || $pass === '' || $from === '') {
                throw new \RuntimeException('SMTP is not fully configured');
            }
            $port = (int) (Settings::get('SMTP_PORT', '587') ?? '587');
            $enc = Settings::get('SMTP_ENCRYPTION', 'tls') ?? 'tls';
            return new SmtpMailer(new Smtp($host, $port, $enc === 'ssl' ? 'ssl' : 'tls', $user, $pass, $from, Settings::get('MAIL_FROM_NAME', 'Belis Bell') ?? 'Belis Bell'));
        }
        if ($driver !== 'log') {
            throw new \RuntimeException('Unknown mail method');
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
