<?php
declare(strict_types=1);

use Belis\Support\Site;
use Belis\Core\App;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Core\View;
use Belis\Domain\Catalogue;

// Category and product pages: input handling, escaping and routing (THR-007, CTL-INP-001).

test('sort falls back to the default for anything not on the allowlist', function (): void {
    assert_same('featured', Catalogue::normaliseSort("price-asc'; DROP TABLE products;--"));
    assert_same('featured', Catalogue::normaliseSort(['price-asc']));
    assert_same('featured', Catalogue::normaliseSort(null));
    assert_same('price-desc', Catalogue::normaliseSort('price-desc'));
    assert_same('name', Catalogue::normaliseSort('name'));
});

test('page numbers are clamped and non-numeric input becomes page 1', function (): void {
    assert_same(1, Catalogue::normalisePage(null));
    assert_same(1, Catalogue::normalisePage('abc'));
    assert_same(1, Catalogue::normalisePage('-5'));
    assert_same(1, Catalogue::normalisePage('0'));
    assert_same(3, Catalogue::normalisePage('3'));
    assert_same(500, Catalogue::normalisePage('99999999999'));
    assert_same(1, Catalogue::normalisePage(['2']));
});

test('catalogue routes exist, are public and are GET only', function (): void {
    $paths = [];
    foreach (App::router()->inventory() as $r) {
        $paths[$r['path']] = $r;
    }
    foreach (['/shop', '/c/{slug}', '/p/{slug}'] as $p) {
        assert_true(isset($paths[$p]), "$p is not routed");
        assert_same('public', $paths[$p]['policy']);
        assert_same('GET', $paths[$p]['method']);
    }
});

test('slugs with unsafe characters do not match a route', function (): void {
    Env::fake(['APP_URL' => 'http://localhost']);
    $router = App::router();
    foreach (['/p/a b', '/p/<script>', "/p/x'y", '/c/../etc', '/p/a/b'] as $path) {
        assert_same(404, $router->dispatch(new Request('GET', $path))->status, $path);
    }
});

test('a listing page escapes hostile category and product text', function (): void {
    $bad = '<script>alert(1)</script>';
    $html = View::render('pages/listing', [
        'title' => 'x',
        'category' => ['id' => 1, 'slug' => 'c', 'name' => $bad, 'blurb' => '"><img src=x onerror=alert(1)>'],
        'categories' => [['slug' => 'c', 'name' => $bad, 'blurb' => '']],
        'items' => [['slug' => 'p"><script>', 'name' => $bad, 'pack_size' => '<b>5L</b>', 'price_pesewas' => 500, 'stock_status' => 'in_stock', 'category' => $bad, 'category_slug' => 'c']],
        'total' => 1, 'pages' => 1, 'page' => 1, 'sort' => 'featured', 'inStock' => false,
    ]);
    assert_not_contains('<script>alert(1)</script>', $html);
    assert_not_contains('<img src=x', $html);
    assert_not_contains('<b>5L</b>', $html);
    assert_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
});

test('pagination links keep sort and stock filter but drop empty values', function (): void {
    $html = View::render('pages/listing', [
        'title' => 'x', 'category' => null, 'categories' => [],
        'items' => [['slug' => 'p', 'name' => 'n', 'pack_size' => '1', 'price_pesewas' => 100, 'stock_status' => 'in_stock', 'category' => 'c', 'category_slug' => 'c']],
        'total' => 20, 'pages' => 3, 'page' => 2, 'sort' => 'price-asc', 'inStock' => true,
    ]);
    assert_contains('/shop?sort=price-asc&amp;stock=in" rel="prev"', $html);
    assert_contains('/shop?sort=price-asc&amp;stock=in&amp;page=3" rel="next"', $html);
});

test('a product page escapes hostile text and shows the WhatsApp link only when one is given', function (): void {
    $bad = '<script>alert(1)</script>';
    $product = ['id' => 1, 'category_id' => 1, 'slug' => 's', 'name' => $bad, 'pack_size' => $bad, 'description' => $bad, 'usage_notes' => '"><img src=x onerror=alert(1)>',
        'price_pesewas' => 1234, 'currency' => 'GHS', 'stock_status' => 'low', 'category' => $bad, 'category_slug' => 'c'];
    $with = View::render('pages/product', ['title' => 'x', 'product' => $product, 'related' => [], 'whatsapp' => 'https://wa.me/233200000000?text=Hi']);
    $without = View::render('pages/product', ['title' => 'x', 'product' => $product, 'related' => [], 'whatsapp' => null]);
    assert_not_contains('<script>alert(1)</script>', $with);
    assert_not_contains('<img src=x', $with);
    assert_contains('GH₵ 12.34', $with);
    assert_contains('https://wa.me/233200000000', $with);
    assert_contains('rel="noopener noreferrer"', $with);
    assert_not_contains('wa.me', $without);
});

test('the WhatsApp link is built only from digits and is off without a number', function (): void {
    Env::fake(['WHATSAPP_NUMBER' => '']);
    assert_same(null, Site::whatsappLink('Soap'));
    Env::fake(['WHATSAPP_NUMBER' => '12']);
    assert_same(null, Site::whatsappLink('Soap'));
    Env::fake(['WHATSAPP_NUMBER' => '+233 20 000 0000"><script>']);
    $link = Site::whatsappLink('Soap & <b>bleach</b>');
    assert_true(is_string($link) && str_starts_with($link, 'https://wa.me/233200000000?text='));
    assert_not_contains('<', $link);
    assert_not_contains('"', $link);
    assert_contains('%3Cb%3Ebleach%3C%2Fb%3E', $link);
});

test('flag() refuses attributes that are not on the allowlist', function (): void {
    assert_same(' selected', flag(true, 'selected'));
    assert_same('', flag(false, 'selected'));
    $threw = false;
    try {
        flag(true, 'onclick="x()"');
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    assert_true($threw);
});

test('every navigation link points at a routed page', function (): void {
    $routes = array_column(App::router()->inventory(), 'path');
    foreach (Site::nav() as $link) {
        assert_true(in_array($link['href'], $routes, true), $link['href'] . ' has no route');
    }
});

test('contact details are hidden unless set, and shown as samples only in mock mode', function (): void {
    Env::fake(['MOCK_DATA' => '0']);
    $c = Site::contact();
    assert_same([null, null, null, null, false], [$c['address'], $c['phone'], $c['email'], $c['hours'], $c['sample']]);
    Env::fake(['MOCK_DATA' => '1']);
    $c = Site::contact();
    assert_true($c['sample'] && $c['phone'] !== null);
    Env::fake(['MOCK_DATA' => '1', 'CONTACT_PHONE' => '+233 30 000 0000', 'CONTACT_EMAIL' => 'a@b.example']);
    assert_same('+233 30 000 0000', Site::contact()['phone']);
    assert_same(false, Site::contact()['sample']);
});

test('phone links accept only digits and a leading plus', function (): void {
    assert_same('tel:+233240000000', Site::phoneHref('+233 24 000 0000'));
    assert_same(null, Site::phoneHref('call me'));
    assert_same(null, Site::phoneHref('javascript:alert(1)'));
});

test('social links need a real https address', function (): void {
    Env::fake(['SOCIAL_FACEBOOK' => 'https://facebook.com/x', 'SOCIAL_INSTAGRAM' => 'javascript:alert(1)', 'SOCIAL_LINKEDIN' => 'http://insecure.example', 'SOCIAL_TIKTOK' => '']);
    $labels = array_column(Site::socials(), 'label');
    assert_same(['Facebook'], $labels);
    Env::fake([]);
    assert_same([], Site::socials());
});

test('the secure payments promise stays hidden until live payments are approved', function (): void {
    Env::fake([]);
    assert_true(!in_array('Secure payments', array_column(Site::trust(), 'title'), true));
    Env::fake(['PAYMENTS_LIVE_APPROVED' => '1']);
    assert_true(in_array('Secure payments', array_column(Site::trust(), 'title'), true));
});

test('icon() only draws names on the allowlist', function (): void {
    assert_contains('<svg class="icon"', icon('search'));
    $threw = false;
    try {
        icon('<script>');
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    assert_true($threw);
});

test('the home page renders every section from the mockup, with placeholders when photos are missing', function (): void {
    Env::fake(['MOCK_DATA' => '1']);
    // Point at an empty folder so the result does not depend on which real photos have been built.
    $empty = sys_get_temp_dir() . '/belis-noimg-' . bin2hex(random_bytes(4));
    mkdir($empty);
    Belis\Support\Images::useRoot($empty);
    $html = View::render('pages/home', [
        'title' => 'x',
        'categories' => [['slug' => 'cleaning-products', 'name' => 'Cleaning Products', 'blurb' => '']],
        'products' => [['slug' => 'p', 'name' => 'Item', 'pack_size' => '1', 'price_pesewas' => 100, 'stock_status' => 'in_stock', 'category' => 'c']],
        'trust' => Site::trust(), 'why' => Site::why(), 'audiences' => Site::audiences(),
    ]);
    foreach (['Everything You', 'Explore Our Range', 'Reliable Supplies.', 'Solutions for Every Space', 'Popular Products', 'More Than Just Products', 'Clean Spaces'] as $heading) {
        assert_contains($heading, $html);
    }
    assert_contains('class="ph bleed-ph"', $html);
    assert_true(preg_match('/<script(?![^>]*\ssrc=)/', $html) !== 1, 'inline script in the rendered page');
    Belis\Support\Images::useRoot(null);
    @rmdir($empty);
});
