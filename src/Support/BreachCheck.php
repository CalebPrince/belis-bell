<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Env;

/**
 * Breached-password check (CTL-AUTH-003) with the Have I Been Pwned range service. Only the first 5 characters of the
 * password's SHA-1 hash leave the server, over verified TLS, with a 3 second timeout, no cookies and no other data.
 * The password and the rest of the hash never leave. If the service cannot be reached or answers oddly, the answer
 * is "not known to be breached" and the failure is logged, so the built-in common-password list still applies and
 * nobody is blocked. NOT YET RUN against the real service: it is tested against a fake, and the current service
 * documentation should be re-checked at first use.
 */
final class BreachCheck
{
    private const HOST = 'https://api.pwnedpasswords.com/range/';

    /** @var (callable(string):string)|null returns the response body for a 5 character prefix, or throws */
    private static $fetch = null;

    /** Tests only. */
    public static function useForTests(?callable $fetch): void
    {
        self::$fetch = $fetch;
    }

    public static function enabled(): bool
    {
        return (Env::get('BREACH_CHECK', 'on') ?? 'on') !== 'off';
    }

    /** True only when the service says this password appears in known breaches. */
    public static function isBreached(string $password): bool
    {
        if ($password === '' || !self::enabled()) {
            return false;
        }
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);
        try {
            $body = (self::$fetch ?? [self::class, 'fetchRange'])($prefix);
        } catch (\Throwable $e) {
            Logger::error('Breached-password check unavailable', ['type' => $e::class]);
            return false;
        }
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $parts = explode(':', trim($line));
            // The service pads its answer with fake rows that have a count of 0: they never count.
            if (count($parts) === 2 && strtoupper($parts[0]) === $suffix && (int) $parts[1] > 0) {
                return true;
            }
        }
        return false;
    }

    public static function fetchRange(string $prefix): string
    {
        if (preg_match('/^[0-9A-F]{5}$/', $prefix) !== 1) {
            throw new \InvalidArgumentException('Bad prefix');
        }
        $ch = curl_init(self::HOST . $prefix);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Add-Padding: true', 'User-Agent: BelisBell-password-check'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_COOKIEFILE => '',
        ]);
        $out = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($out) || $status !== 200) {
            throw new \RuntimeException('Range service answered ' . $status);
        }
        return $out;
    }
}
