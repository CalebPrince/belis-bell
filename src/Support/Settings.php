<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Secrets;

/**
 * Integration settings the owner manages in the admin Settings page (CTL-SET-001). A value saved there is stored
 * encrypted in the database and wins; if there is none, the environment file value is used. Secrets are write-only:
 * nothing here ever hands a secret back to a page. Values are checked for shape and for the live/test key rules.
 */
final class Settings
{
    /**
     * name => label, group, secret flag, help text. Adding an integration means adding a row here and a validator below.
     *
     * @var array<string,array{label:string,group:string,secret:bool,help:string}>
     */
    public const FIELDS = [
        'PAYSTACK_PUBLIC_KEY' => ['label' => 'Paystack public key', 'group' => 'Paystack', 'secret' => false, 'help' => 'Starts with pk_test_ or pk_live_.'],
        'PAYSTACK_SECRET_KEY' => ['label' => 'Paystack secret key', 'group' => 'Paystack', 'secret' => true, 'help' => 'Starts with sk_test_ or sk_live_. Live keys are refused until the site is in production with a recorded release approval.'],
        'MAIL_DRIVER' => ['label' => 'Email method', 'group' => 'Email', 'secret' => false, 'help' => 'log (local development only, nothing is sent) or smtp.'],
        'SMTP_HOST' => ['label' => 'SMTP server', 'group' => 'Email', 'secret' => false, 'help' => 'For example mail.yourdomain.com.'],
        'SMTP_PORT' => ['label' => 'SMTP port', 'group' => 'Email', 'secret' => false, 'help' => '587 with tls, or 465 with ssl.'],
        'SMTP_ENCRYPTION' => ['label' => 'SMTP encryption', 'group' => 'Email', 'secret' => false, 'help' => 'tls (STARTTLS) or ssl.'],
        'SMTP_USER' => ['label' => 'SMTP user name', 'group' => 'Email', 'secret' => false, 'help' => 'Usually the full mailbox address.'],
        'SMTP_PASSWORD' => ['label' => 'SMTP password', 'group' => 'Email', 'secret' => true, 'help' => 'The mailbox password. It is never shown again after saving.'],
        'MAIL_FROM_ADDRESS' => ['label' => 'Sender address', 'group' => 'Email', 'secret' => false, 'help' => 'The address emails come from.'],
        'MAIL_FROM_NAME' => ['label' => 'Sender name', 'group' => 'Email', 'secret' => false, 'help' => 'For example Belis Bell.'],
        'WHATSAPP_NUMBER' => ['label' => 'WhatsApp number', 'group' => 'Contact', 'secret' => false, 'help' => 'International format, digits only, for the click-to-chat button.'],
    ];

    /** @var array<string,string>|null decrypted values kept for this request only */
    private static ?array $cache = null;

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** The effective value: the saved setting, else the environment file, else the default. */
    public static function get(string $name, ?string $default = null): ?string
    {
        $v = self::stored()[$name] ?? null;
        return $v !== null && $v !== '' ? $v : Env::get($name, $default);
    }

    /** @return 'settings'|'environment'|'none' */
    public static function source(string $name): string
    {
        if ((self::stored()[$name] ?? '') !== '') {
            return 'settings';
        }
        return (Env::get($name, '') ?? '') !== '' ? 'environment' : 'none';
    }

    /** What the page may show for a value: nothing for a secret except its last 4 characters. */
    public static function display(string $name): string
    {
        $v = self::get($name, '') ?? '';
        if ($v === '') {
            return '';
        }
        if (!(self::FIELDS[$name]['secret'] ?? true)) {
            return $v;
        }
        return strlen($v) >= 12 ? 'ends in ' . substr($v, -4) : 'set';
    }

    /** @return array<string,string> */
    private static function stored(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            if (!Secrets::keyIsValid(Env::get('SETTINGS_KEY', ''))) {
                return self::$cache;
            }
            foreach (Db::fromEnv()->all('SELECT name, value_enc FROM settings') as $row) {
                try {
                    self::$cache[(string) $row['name']] = Secrets::decrypt((string) $row['value_enc'], (string) $row['name']);
                } catch (\Throwable) {
                    Logger::error('A saved setting could not be decrypted', ['setting' => (string) $row['name']]);
                }
            }
        } catch (\Throwable) {
            // Database or table not available: the environment file values are used.
        }
        return self::$cache;
    }

    /** @return string|null a message when the value is not acceptable */
    public static function validate(string $name, string $value): ?string
    {
        if (!isset(self::FIELDS[$name])) {
            return 'Unknown setting.';
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $value) === 1 || strlen($value) > 200) {
            return 'That value has characters that are not allowed or is too long.';
        }
        $env = Env::get('APP_ENV', 'production') ?? 'production';
        switch ($name) {
            case 'PAYSTACK_PUBLIC_KEY':
                if (preg_match('/^pk_(test|live)_[A-Za-z0-9]{10,100}$/', $value) !== 1) {
                    return 'That does not look like a Paystack public key (pk_test_... or pk_live_...).';
                }
                return self::modeProblem($value, 'pk_live_', $env);
            case 'PAYSTACK_SECRET_KEY':
                if (preg_match('/^sk_(test|live)_[A-Za-z0-9]{10,100}$/', $value) !== 1) {
                    return 'That does not look like a Paystack secret key (sk_test_... or sk_live_...).';
                }
                return self::modeProblem($value, 'sk_live_', $env);
            case 'MAIL_DRIVER':
                return in_array($value, ['log', 'smtp'], true) ? ($value === 'log' && $env === 'production' ? 'The log method sends nothing and is not allowed in production.' : null) : 'Choose log or smtp.';
            case 'SMTP_HOST':
                return preg_match('/^(?=.{1,190}$)([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/', $value) === 1 ? null : 'Enter a server name such as mail.example.com.';
            case 'SMTP_PORT':
                return preg_match('/^[0-9]{1,5}$/', $value) === 1 && (int) $value >= 1 && (int) $value <= 65535 ? null : 'Enter a port number from 1 to 65535.';
            case 'SMTP_ENCRYPTION':
                return in_array($value, ['tls', 'ssl'], true) ? null : 'Choose tls or ssl.';
            case 'SMTP_USER':
            case 'SMTP_PASSWORD':
                return $value === '' ? 'This cannot be empty.' : null;
            case 'MAIL_FROM_ADDRESS':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Enter a valid email address.';
            case 'MAIL_FROM_NAME':
                return preg_match('/^[^<>"]{1,80}$/u', $value) === 1 ? null : 'Use up to 80 characters, without < > or quotes.';
            case 'WHATSAPP_NUMBER':
                $digits = preg_replace('/\D+/', '', $value) ?? '';
                return strlen($digits) >= 8 && strlen($digits) <= 15 ? null : 'Enter 8 to 15 digits in international format.';
        }
        return null;
    }

    /** Live keys only in production with a recorded release approval; test keys never in production. */
    private static function modeProblem(string $value, string $livePrefix, string $env): ?string
    {
        $live = str_starts_with($value, $livePrefix);
        if ($live && $env !== 'production') {
            return 'Live keys are refused outside production.';
        }
        if ($live && trim(Env::get('RELEASE_APPROVAL_REF', '') ?? '') === '') {
            return 'Live keys need RELEASE_APPROVAL_REF in the environment file (GATE-006).';
        }
        if (!$live && $env === 'production') {
            return 'Test keys are refused in production.';
        }
        return null;
    }

    /**
     * Save one value, encrypted. Also checks that the public and secret Paystack keys are the same mode.
     *
     * @return string|null a message when it was refused
     */
    public static function save(Db $db, string $name, string $value, ?int $userId, ?int $now = null): ?string
    {
        $value = trim($value);
        $problem = self::validate($name, $value);
        if ($problem === null && in_array($name, ['PAYSTACK_PUBLIC_KEY', 'PAYSTACK_SECRET_KEY'], true)) {
            $other = self::get($name === 'PAYSTACK_PUBLIC_KEY' ? 'PAYSTACK_SECRET_KEY' : 'PAYSTACK_PUBLIC_KEY', '') ?? '';
            if ($other !== '' && (str_contains($other, '_live_')) !== (str_contains($value, '_live_'))) {
                $problem = 'The public and secret keys must both be test keys or both be live keys.';
            }
        }
        if ($problem !== null) {
            return $problem;
        }
        $enc = Secrets::encrypt($value, $name);
        $t = $now ?? time();
        if ($db->one('SELECT name FROM settings WHERE name = ?', [$name]) === null) {
            $db->run('INSERT INTO settings (name, value_enc, updated_by, updated_at) VALUES (?, ?, ?, ?)', [$name, $enc, $userId, $t]);
        } else {
            $db->run('UPDATE settings SET value_enc = ?, updated_by = ?, updated_at = ? WHERE name = ?', [$enc, $userId, $t, $name]);
        }
        self::forget();
        return null;
    }

    public static function clear(Db $db, string $name): void
    {
        if (isset(self::FIELDS[$name])) {
            $db->run('DELETE FROM settings WHERE name = ?', [$name]);
            self::forget();
        }
    }

    /**
     * Rules that must hold for the values actually in use, whether they came from the file or from Settings.
     *
     * @return list<string>
     */
    public static function violations(): array
    {
        $env = Env::get('APP_ENV', 'production') ?? 'production';
        $key = self::get('PAYSTACK_SECRET_KEY', '') ?? '';
        $pub = self::get('PAYSTACK_PUBLIC_KEY', '') ?? '';
        $v = [];
        if ($env !== 'production' && (str_starts_with($key, 'sk_live_') || str_starts_with($pub, 'pk_live_'))) {
            $v[] = 'A live Paystack key is in use outside production';
        }
        if ($env === 'production') {
            if (str_starts_with($key, 'sk_test_') || str_starts_with($pub, 'pk_test_')) {
                $v[] = 'A test Paystack key is in use in production';
            }
            if ((str_starts_with($key, 'sk_live_') || str_starts_with($pub, 'pk_live_')) && trim(Env::get('RELEASE_APPROVAL_REF', '') ?? '') === '') {
                $v[] = 'A live Paystack key needs RELEASE_APPROVAL_REF (GATE-006)';
            }
            if ((self::get('MAIL_DRIVER', 'log') ?? 'log') === 'log') {
                $v[] = 'The log mail method is in use in production, so no email would be sent';
            }
        }
        return $v;
    }
}
