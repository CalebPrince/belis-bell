<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Sign-in is NOT BUILT. Accounts, emailed one-time codes, roles and sessions are still to be built and
 * tested (CTL-AUTH-001, CTL-AUTH-002, CTL-ORG-001). Until then every check denies, so any route that asks
 * for a signed-in user is unreachable. Authentication never implies authorisation: real policies must
 * also check the specific object.
 *
 * PREVIEW MODE: so the signed-in pages can be looked at while sign-in does not exist, a developer can set
 * PREVIEW_LOGIN=customer|staff|owner in a LOCAL environment with mock data. The environment guard refuses to
 * start with PREVIEW_LOGIN set anywhere else, and tests cover both. The person shown is a mock person.
 */
final class Auth
{
    public const MOCK_CUSTOMER = ['name' => 'Prince Caleb', 'email' => 'you@example.test', 'phone' => '+233 24 123 4567', 'member_since' => '2026-08-02', 'type' => 'Individual'];
    public const MOCK_STAFF = ['name' => 'Prince Caleb', 'email' => 'admin@example.test', 'role' => 'Owner'];

    /** The preview role, or null when preview mode is off. Only ever set in local mock mode. */
    public static function previewRole(): ?string
    {
        $role = Env::get('PREVIEW_LOGIN', '') ?? '';
        if ($role === '' || (Env::get('APP_ENV', 'production') ?? 'production') !== 'local' || !Env::bool('MOCK_DATA')) {
            return null;
        }
        return in_array($role, ['customer', 'staff', 'owner'], true) ? $role : null;
    }

    /** @return array<string,string>|null */
    public static function customer(): ?array
    {
        return self::previewRole() !== null ? self::MOCK_CUSTOMER : null;
    }

    /** @return array<string,string>|null */
    public static function staff(): ?array
    {
        $r = self::previewRole();
        return $r === 'staff' || $r === 'owner' ? self::MOCK_STAFF : null;
    }

    /** @return array<string,string>|null */
    public static function owner(): ?array
    {
        return self::previewRole() === 'owner' ? self::MOCK_STAFF : null;
    }
}
