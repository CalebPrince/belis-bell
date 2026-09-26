<?php
declare(strict_types=1);

/**
 * Fails when a release folder contains anything that must not ship (GATE-005).
 * Usage: php bin/check-release.php [path]   (default: dist)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$dir = $argv[1] ?? dirname(__DIR__) . '/dist';
if (!is_dir($dir)) {
    fwrite(STDERR, "No release folder at {$dir}\n");
    exit(1);
}
$forbidden = ['database/seed', 'bin/seed.php', 'bin/purge-mock.php', 'tests', 'tools', '.opskeep', 'node_modules', '.git', '.env'];
$problems = [];
foreach ($forbidden as $rel) {
    if (file_exists($dir . '/' . $rel)) {
        $problems[] = "forbidden path in release: {$rel}";
    }
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
    if (preg_match('/\.(php|env|sql|md|json|yml|yaml)$/i', $rel) !== 1 && !str_starts_with(basename($rel), '.env')) {
        continue;
    }
    $text = (string) file_get_contents($f->getPathname());
    if (preg_match('/sk_(live|test)_[A-Za-z0-9]{10,}/', $text) === 1) {
        $problems[] = "Paystack key found in {$rel}";
    }
    if (str_contains($text, 'MOCK ' . 'DATA.')) {
        $problems[] = "mock data marker found in {$rel}";
    }
}
if (!is_file($dir . '/public/assets/css/app.css')) {
    $problems[] = 'built CSS is missing';
}
if ($problems !== []) {
    fwrite(STDERR, "RELEASE CHECK FAILED\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}
echo "release check passed\n";
