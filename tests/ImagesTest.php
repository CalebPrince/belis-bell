<?php
declare(strict_types=1);

use Belis\Support\Images;

// PG-035: owner-supplied images. Slots may come from the database, so they are validated, and
// alt text is escaped. A missing photo renders a neutral placeholder and never an empty img.

const TINY_WEBP = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

function image_fixture(array $files): string
{
    $root = sys_get_temp_dir() . '/belis-img-' . bin2hex(random_bytes(4));
    foreach ($files as $rel) {
        @mkdir(dirname($root . '/' . $rel), 0777, true);
        file_put_contents($root . '/' . $rel, base64_decode(TINY_WEBP));
    }
    @mkdir($root, 0777, true);
    Images::useRoot($root);
    return $root;
}

function image_cleanup(string $root): void
{
    foreach (glob($root . '/*/*') ?: [] as $f) {
        @unlink($f);
    }
    foreach (glob($root . '/*/') ?: [] as $d) {
        @rmdir($d);
    }
    foreach (glob($root . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($root);
    Images::useRoot(null);
}

test('slot names reject path tricks and odd characters', function (): void {
    foreach (['home/hero', 'products/sample-bleach-5l/main', 'a'] as $ok) {
        assert_true(Images::isValidSlot($ok), $ok);
    }
    foreach (['../etc/passwd', 'home/../x', '/abs', 'Home/Hero', 'home//hero', 'home/hero.jpg', 'home/hero-', '-x', "a\0b", 'a b', '', str_repeat('a', 200)] as $bad) {
        assert_true(!Images::isValidSlot($bad), 'accepted: ' . json_encode($bad));
    }
});

test('a missing image renders a placeholder with the alt text as its label', function (): void {
    $root = image_fixture([]);
    $html = Images::html('home/hero', 'Cleaning products on a table');
    assert_contains('class="ph', $html);
    assert_contains('role="img" aria-label="Cleaning products on a table"', $html);
    assert_not_contains('<img', $html);
    image_cleanup($root);
});

test('a decorative placeholder is hidden from screen readers', function (): void {
    $root = image_fixture([]);
    $html = Images::html('home/hero', 'ignored', '100vw', ['decorative' => true]);
    assert_contains('aria-hidden="true"', $html);
    assert_not_contains('aria-label', $html);
    image_cleanup($root);
});

test('available sizes become a srcset with width and height attributes', function (): void {
    $root = image_fixture(['home/hero-400.webp', 'home/hero-800.webp', 'home/hero-1600.webp']);
    $html = Images::html('home/hero', 'Hero', '(min-width: 1024px) 50vw, 100vw');
    assert_contains('<img ', $html);
    assert_contains('/assets/img/home/hero-400.webp?v=', $html);
    assert_contains(' 400w', $html);
    assert_contains(' 800w', $html);
    assert_contains(' 1600w', $html);
    assert_contains('sizes="(min-width: 1024px) 50vw, 100vw"', $html);
    assert_contains('width="1" height="1"', $html);
    assert_contains('loading="lazy" decoding="async"', $html);
    assert_contains('alt="Hero"', $html);
    image_cleanup($root);
});

test('a priority image loads eagerly and a decorative image has empty alt', function (): void {
    $root = image_fixture(['home/hero-800.webp']);
    $html = Images::html('home/hero', 'x', '100vw', ['priority' => true, 'decorative' => true]);
    assert_contains('loading="eager"', $html);
    assert_contains('fetchpriority="high"', $html);
    assert_contains('alt=""', $html);
    image_cleanup($root);
});

test('alt text and classes are escaped', function (): void {
    $root = image_fixture(['products/p/main-800.webp']);
    $html = Images::html('products/p/main', '"><script>alert(1)</script>', '100vw', ['class' => '"><b>']);
    assert_not_contains('<script>', $html);
    assert_not_contains('"><b>', $html);
    assert_contains('&quot;&gt;&lt;script&gt;', $html);
    image_cleanup($root);
});

test('a hostile slot from the database never reaches the file system', function (): void {
    $root = image_fixture(['home/hero-800.webp']);
    $html = Images::html('../../etc/passwd', 'x');
    assert_not_contains('<img', $html);
    assert_not_contains('passwd', $html);
    assert_true(!Images::exists('home/../home/hero'));
    assert_true(Images::exists('home/hero'));
    image_cleanup($root);
});

test('image_html() is an approved template output helper that escapes', function (): void {
    $root = image_fixture([]);
    assert_not_contains('<script>', image_html('home/hero', '<script>x</script>'));
    image_cleanup($root);
});
