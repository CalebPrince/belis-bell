<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * NOT BUILT. Sign-in, accounts, roles and MFA are still to be designed and tested
 * (CTL-AUTH-001, CTL-AUTH-002, CTL-ORG-001). Until then every check denies, so any
 * route that asks for a signed-in user is unreachable. Authentication never
 * implies authorisation: real policies must also check the specific object.
 */
final class Auth
{
    public static function customer(): ?array
    {
        return null;
    }

    public static function staff(): ?array
    {
        return null;
    }

    public static function owner(): ?array
    {
        return null;
    }
}
