<?php
declare(strict_types=1);

namespace Belis\Payments;

use Belis\Core\Env;
use Belis\Support\Settings;

/** Chooses the payment adapter from the environment. Fails closed: with no key, Paystack is not available. */
final class Payments
{
    private static ?PaymentAdapter $override = null;

    /** Tests only. */
    public static function useForTests(?PaymentAdapter $adapter): void
    {
        self::$override = $adapter;
    }

    public static function adapter(): PaymentAdapter
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $name = Env::get('PAYMENTS_ADAPTER', 'paystack') ?? 'paystack';
        if ($name === 'mock') {
            if ((Env::get('APP_ENV', 'production') ?? 'production') !== 'local') {
                throw new \RuntimeException('The mock payment adapter only runs locally');
            }
            return new MockAdapter();
        }
        $key = Settings::get('PAYSTACK_SECRET_KEY', '') ?? '';
        if ($name !== 'paystack' || $key === '') {
            throw new \RuntimeException('No payment provider is configured');
        }
        if (Settings::violations() !== []) {
            throw new \RuntimeException('The payment key is not allowed in this environment');
        }
        return new PaystackAdapter($key);
    }

    public static function isMock(): bool
    {
        return (Env::get('PAYMENTS_ADAPTER', 'paystack') ?? 'paystack') === 'mock' && (Env::get('APP_ENV', 'production') ?? 'production') === 'local';
    }
}
