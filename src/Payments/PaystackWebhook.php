<?php
declare(strict_types=1);

namespace Belis\Payments;

/**
 * Paystack webhook signature check (CTL-PAY-002). Paystack signs the raw request
 * body with HMAC SHA-512 using the account secret key and sends it in the
 * x-paystack-signature header. Confirm this against the current Paystack docs at
 * build time. A valid signature is necessary but not sufficient: the server must
 * still verify the transaction through the Paystack API and match amount,
 * currency and reference before an order is marked paid.
 */
final class PaystackWebhook
{
    public static function verifySignature(string $rawBody, ?string $signature, string $secretKey): bool
    {
        if ($secretKey === '' || $signature === null || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha512', $rawBody, $secretKey);
        return hash_equals($expected, strtolower(trim($signature)));
    }
}
