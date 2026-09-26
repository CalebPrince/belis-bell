<?php
declare(strict_types=1);

use Belis\Core\Env;
use Belis\Core\Guard;

// CTL-ENV-001, THR-011: mock data and test keys must never run in production.

function prod(array $over = []): array
{
    return $over + [
        'APP_ENV' => 'production',
        'APP_DEBUG' => '0',
        'APP_URL' => 'https://example.test',
        'MOCK_DATA' => '0',
        'PAYSTACK_SECRET_KEY' => '',
        'PAYMENTS_ADAPTER' => 'paystack',
        'AUTH_PEPPER' => 'a-long-random-value-for-tests-0123456789',
        'SETTINGS_KEY' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    ];
}

test('a clean production configuration passes', function (): void {
    Env::fake(prod());
    assert_same([], Guard::violations());
});

test('production refuses mock data', function (): void {
    Env::fake(prod(['MOCK_DATA' => '1']));
    assert_true(Guard::violations() !== []);
});

test('production refuses debug output', function (): void {
    Env::fake(prod(['APP_DEBUG' => '1']));
    assert_true(Guard::violations() !== []);
});

test('production refuses a Paystack test key', function (): void {
    Env::fake(prod(['PAYSTACK_SECRET_KEY' => 'sk_test_abc']));
    assert_true(Guard::violations() !== []);
});

test('production refuses the mock payment adapter', function (): void {
    Env::fake(prod(['PAYMENTS_ADAPTER' => 'mock']));
    assert_true(Guard::violations() !== []);
});

test('production refuses a non-https URL', function (): void {
    Env::fake(prod(['APP_URL' => 'http://example.test']));
    assert_true(Guard::violations() !== []);
});

test('a live key in production needs a release approval reference', function (): void {
    Env::fake(prod(['PAYSTACK_SECRET_KEY' => 'sk_live_abc']));
    assert_true(Guard::violations() !== [], 'live key allowed without approval reference');
    Env::fake(prod(['PAYSTACK_SECRET_KEY' => 'sk_live_abc', 'RELEASE_APPROVAL_REF' => 'GATE-006-2026']));
    assert_same([], Guard::violations());
});

test('a live key is refused outside production', function (): void {
    Env::fake(['APP_ENV' => 'staging', 'PAYSTACK_SECRET_KEY' => 'sk_live_abc']);
    assert_true(Guard::violations() !== []);
});

test('an unknown APP_ENV is refused', function (): void {
    Env::fake(['APP_ENV' => 'prod']);
    assert_true(Guard::violations() !== []);
});

test('local with mock data and a test key is allowed', function (): void {
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PAYSTACK_SECRET_KEY' => 'sk_test_abc']);
    assert_same([], Guard::violations());
});

test('a missing APP_ENV is treated as production, not local', function (): void {
    Env::fake([]);
    assert_true(Guard::violations() !== [], 'default must be the strictest environment');
});

test('preview login works only in local with mock data', function (): void {
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'customer']);
    assert_same([], Guard::violations());
    assert_same('customer', Belis\Core\Auth::previewRole());
    assert_true(Belis\Core\Auth::customer() !== null && Belis\Core\Auth::staff() === null);
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'owner']);
    assert_true(Belis\Core\Auth::owner() !== null && Belis\Core\Auth::staff() !== null);
    foreach ([['APP_ENV' => 'staging', 'MOCK_DATA' => '1'], ['APP_ENV' => 'production', 'MOCK_DATA' => '0', 'APP_URL' => 'https://x.test'], ['APP_ENV' => 'local', 'MOCK_DATA' => '0']] as $env) {
        Env::fake($env + ['PREVIEW_LOGIN' => 'owner']);
        assert_true(Guard::violations() !== [], 'preview login allowed in ' . json_encode($env));
        assert_same(null, Belis\Core\Auth::previewRole(), 'preview role active outside local mock mode');
        assert_same(null, Belis\Core\Auth::owner(), 'a mock person was signed in outside local mock mode');
    }
});

test('with preview login off nobody is signed in', function (): void {
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '']);
    assert_same(null, Belis\Core\Auth::customer());
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'admin']);
    assert_same(null, Belis\Core\Auth::previewRole(), 'unknown roles are ignored');
});

test('production refuses a missing pepper and a missing or bad settings key', function (): void {
    Env::fake(prod(['AUTH_PEPPER' => 'short']));
    assert_true(Guard::violations() !== []);
    Env::fake(prod(['SETTINGS_KEY' => 'nope']));
    assert_true(Guard::violations() !== []);
});
