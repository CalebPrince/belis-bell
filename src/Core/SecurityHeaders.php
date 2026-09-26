<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Response security headers (CTL-SC-002). No inline script or style is allowed and
 * no third-party origin is listed: fonts and CSS are self-hosted. Adding a third-party
 * script (analytics, chat, a payment pop-up) needs owner approval and a baseline change.
 */
final class SecurityHeaders
{
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; "
        . "connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    /** @return array<string,string> */
    public static function all(bool $https): array
    {
        $h = [
            'Content-Security-Policy' => self::CSP,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($https) {
            $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return $h;
    }

    public static function apply(Response $response, bool $https): Response
    {
        foreach (self::all($https) as $name => $value) {
            $response->headers[$name] = $value;
        }
        $response->headers['Cache-Control'] ??= 'no-store';
        return $response;
    }
}
