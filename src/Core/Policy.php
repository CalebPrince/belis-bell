<?php
declare(strict_types=1);

namespace Belis\Core;

use Belis\Payments\PaystackWebhook;

/**
 * Deny by default (CTL-AUTHZ-001, CTL-FW-001). Every route declares one policy.
 * An unknown policy name is a 403, never an allow.
 */
final class Policy
{
    public const KNOWN = ['public', 'customer', 'staff', 'owner', 'webhook:paystack'];

    /** @return int 200 allowed, 401 sign in needed, 403 forbidden */
    public static function evaluate(string $policy, Request $request): int
    {
        return match ($policy) {
            'public' => 200,
            'customer' => Auth::customer() !== null ? 200 : 401,
            'staff' => Auth::staff() !== null ? 200 : 401,
            'owner' => Auth::owner() !== null ? 200 : 401,
            'webhook:paystack' => PaystackWebhook::verifySignature(
                $request->rawBody(),
                $request->header('x-paystack-signature'),
                Env::get('PAYSTACK_SECRET_KEY', '') ?? '',
            ) ? 200 : 401,
            default => 403,
        };
    }
}
