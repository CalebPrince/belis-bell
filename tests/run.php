<?php
declare(strict_types=1);

/**
 * Dependency-free test runner (no Composer needed). Run: php tests/run.php
 * Each *Test.php file registers tests with test('name', fn () => ...).
 * Exit code 1 when anything fails, so CI blocks the change (GATE-002).
 */

define('BASE_PATH', dirname(__DIR__));
ini_set('display_errors', '1');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Belis\\')) {
        $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require BASE_PATH . '/src/helpers.php';
// Tests never call the real breached-password service. Tests that need it set their own fake.
Belis\Support\BreachCheck::useForTests(static fn (string $prefix): string => '');

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function assert_true(mixed $cond, string $message = 'expected true'): void
{
    if ($cond !== true) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . "expected to find '{$needle}'");
    }
}

function assert_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException(($message !== '' ? $message . ': ' : '') . "did not expect '{$needle}'");
    }
}

/** @return list<string> all files under a directory with one of the extensions */
function files_under(string $dir, array $extensions): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), $extensions, true)) {
            $out[] = str_replace('\\', '/', $f->getPathname());
        }
    }
    sort($out);
    return $out;
}

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$pass = 0;
$fail = 0;
foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    try {
        $fn();
        echo "  ok    {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  FAIL  {$name}\n        " . $e->getMessage() . "\n";
        $fail++;
    }
}
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
