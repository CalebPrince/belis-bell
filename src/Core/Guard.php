<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Environment guard (CTL-ENV-001, THR-011).
 * Mock data, test payment keys and debug output must never run in production,
 * and live payment keys must never run anywhere else.
 */
final class Guard
{
    public const ENVIRONMENTS = ['local', 'staging', 'production'];

    /** @return list<string> reasons the application must refuse to start */
    public static function violations(): array
    {
        $v = [];
        $env = Env::get('APP_ENV', 'production');
        $key = Env::get('PAYSTACK_SECRET_KEY', '') ?? '';

        if (!in_array($env, self::ENVIRONMENTS, true)) {
            $v[] = 'APP_ENV must be local, staging or production';
        }
        if ((Env::get('PREVIEW_LOGIN', '') ?? '') !== '' && ($env !== 'local' || !Env::bool('MOCK_DATA'))) {
            $v[] = 'PREVIEW_LOGIN is only allowed in a local environment with mock data';
        }
        if ($env !== 'production' && str_starts_with($key, 'sk_live_')) {
            $v[] = 'A live Paystack key is configured outside production';
        }
        if ($env === 'production') {
            if (Env::bool('MOCK_DATA')) {
                $v[] = 'MOCK_DATA is enabled in production';
            }
            if (Env::bool('APP_DEBUG')) {
                $v[] = 'APP_DEBUG is enabled in production';
            }
            if (str_starts_with($key, 'sk_test_')) {
                $v[] = 'A test Paystack key is configured in production';
            }
            if (str_starts_with($key, 'sk_live_') && trim(Env::get('RELEASE_APPROVAL_REF', '') ?? '') === '') {
                $v[] = 'A live Paystack key needs RELEASE_APPROVAL_REF (GATE-006)';
            }
            if ((Env::get('PAYMENTS_ADAPTER', 'paystack') ?? 'paystack') === 'mock') {
                $v[] = 'The mock payment adapter is enabled in production';
            }
            if (strlen(Env::get('AUTH_PEPPER', '') ?? '') < 32) {
                $v[] = 'AUTH_PEPPER must be set to at least 32 random characters';
            }
            if ((Env::get('MAIL_DRIVER', 'log') ?? 'log') === 'log') {
                $v[] = 'The log mail driver is enabled in production, so no email would be sent';
            }
            if (!str_starts_with(Env::get('APP_URL', '') ?? '', 'https://')) {
                $v[] = 'APP_URL must start with https:// in production';
            }
        }
        return $v;
    }

    /** Any row flagged as mock data means a production release is unsafe. */
    public static function mockRowsPresent(Db $db): bool
    {
        $cats = $db->one('SELECT COUNT(*) AS n FROM categories WHERE is_mock = 1');
        $prods = $db->one('SELECT COUNT(*) AS n FROM products WHERE is_mock = 1');
        return ((int) ($cats['n'] ?? 0)) > 0 || ((int) ($prods['n'] ?? 0)) > 0;
    }
}
