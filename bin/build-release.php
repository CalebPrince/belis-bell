<?php
declare(strict_types=1);

/**
 * Builds dist/ from an explicit allowlist (CTL-ENV-001, CTL-DEP-001). Seed data, the seed
 * and purge scripts, tests, tools, design records and node_modules are never copied.
 * Run after: npm run build:css. Then run: php bin/check-release.php
 */
require __DIR__ . '/_boot.php';

$root = dirname(__DIR__);
$dist = $root . '/dist';

// ALLOWLIST START
$include = [
    'public',
    'src',
    'templates',
    'config',
    'database/migrations',
    'bin/_boot.php',
    'bin/migrate.php',
    'bin/check-release.php',
    '.htaccess',
    '.env.example',
];
// ALLOWLIST END

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function copy_path(string $from, string $to): void
{
    if (is_dir($from)) {
        @mkdir($to, 0755, true);
        foreach (new DirectoryIterator($from) as $item) {
            if ($item->isDot()) {
                continue;
            }
            copy_path($item->getPathname(), $to . '/' . $item->getFilename());
        }
        return;
    }
    @mkdir(dirname($to), 0755, true);
    copy($from, $to);
}

if (!is_file($root . '/public/assets/css/app.css')) {
    fwrite(STDERR, "Build the CSS first: npm run build:css\n");
    exit(1);
}
rrmdir($dist);
foreach ($include as $path) {
    copy_path($root . '/' . $path, $dist . '/' . $path);
}
foreach (['storage/logs', 'storage/cache', 'storage/sessions'] as $dir) {
    @mkdir($dist . '/' . $dir, 0700, true);
}
echo "release built in dist/\n";
