<?php
declare(strict_types=1);

// CTL-ENV-001, CTL-SC-001: nothing secret or mock may ship, and there are no runtime packages.

test('.env.example holds no real keys or passwords', function (): void {
    $t = (string) file_get_contents(BASE_PATH . '/.env.example');
    assert_true(preg_match('/^(DB_PASS|PAYSTACK_SECRET_KEY|PAYSTACK_PUBLIC_KEY)=\S+/m', $t) !== 1, 'a secret value is set in .env.example');
    assert_true(preg_match('/sk_(live|test)_[A-Za-z0-9]+/', $t) !== 1, 'a Paystack key is present');
});

test('source files contain no committed secrets', function (): void {
    foreach (array_merge(files_under(BASE_PATH . '/src', ['php']), files_under(BASE_PATH . '/config', ['php']), files_under(BASE_PATH . '/bin', ['php'])) as $file) {
        $code = (string) file_get_contents($file);
        assert_true(preg_match('/sk_(live|test)_[A-Za-z0-9]{10,}/', $code) !== 1, "$file contains a Paystack key");
        assert_true(preg_match('/(password|secret)\s*=\s*[\'"][^\'"]{6,}[\'"]/i', $code) !== 1, "$file may contain a hard-coded secret");
    }
});

test('the web root contains only the front controller and static assets', function (): void {
    foreach (files_under(BASE_PATH . '/public', ['php', 'sql', 'env', 'log']) as $file) {
        assert_true(str_ends_with($file, '/public/index.php'), "unexpected file in the web root: $file");
    }
});

test('the app has no Composer runtime packages', function (): void {
    assert_true(!is_dir(BASE_PATH . '/vendor') || glob(BASE_PATH . '/vendor/*') === [], 'vendor/ is present: review it against CTL-SC-001');
});

test('the release builder leaves out seed, tests and tooling', function (): void {
    $script = (string) file_get_contents(BASE_PATH . '/bin/build-release.php');
    foreach (['database/seed', 'tests', 'tools', '.opskeep', 'seed.php', 'purge-mock.php'] as $excluded) {
        assert_true(!str_contains(explode('// ALLOWLIST END', explode('// ALLOWLIST START', $script)[1] ?? '')[0], $excluded), "$excluded is in the release allowlist");
    }
});
