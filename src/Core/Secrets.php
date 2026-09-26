<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Authenticated encryption for settings kept in the database (CTL-SET-001): AES-256-GCM through OpenSSL, a fresh
 * random IV for every value, and the setting's name bound in as associated data so a stored value cannot be moved
 * to a different setting. The master key comes only from SETTINGS_KEY in the environment file (64 hex characters).
 */
final class Secrets
{
    private const PREFIX = 'v1:';

    public static function encrypt(string $plain, string $context, ?string $keyHex = null): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key($keyHex), OPENSSL_RAW_DATA, $iv, $tag, $context, 16);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored, string $context, ?string $keyHex = null): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            throw new \RuntimeException('Unknown format');
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Damaged value');
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key($keyHex), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $context);
        if ($plain === false) {
            throw new \RuntimeException('Could not decrypt');
        }
        return $plain;
    }

    public static function keyIsValid(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^[0-9a-f]{64}$/i', $hex) === 1;
    }

    private static function key(?string $hex): string
    {
        $hex ??= Env::get('SETTINGS_KEY', '') ?? '';
        if (!self::keyIsValid($hex)) {
            throw new \RuntimeException('SETTINGS_KEY is missing or not 64 hex characters');
        }
        return (string) hex2bin($hex);
    }
}
