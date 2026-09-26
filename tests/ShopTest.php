<?php
declare(strict_types=1);

use Belis\Controllers\CartController;
use Belis\Core\Env;
use Belis\Core\View;
use Belis\Domain\Cart;
use Belis\Domain\Catalogue;
use Belis\Support\Images;
use Belis\Support\Site;

// Shop and category pages (PG-045, PG-046, PG-050) and the cart (PG-052).

test('filters from the query string are checked and odd values are dropped', function (): void {
    $f = Catalogue::normaliseFilters(['sort' => 'price-asc', 'avail' => 'in', 'brand' => 'EcoCare', 'min' => '10', 'max' => '99', 'sub' => 'disinfectants', 'page' => '2']);
    assert_same(['price-asc', 'in', 'EcoCare', 10, 99, 'disinfectants', 2], [$f['sort'], $f['avail'], $f['brand'], $f['min'], $f['max'], $f['sub'], $f['page']]);
    $bad = Catalogue::normaliseFilters(['sort' => "x'; DROP", 'avail' => 'maybe', 'brand' => str_repeat('a', 200), 'min' => '-5', 'max' => 'abc', 'sub' => '../etc', 'page' => 'x']);
    assert_same(['featured', null, null, null, null, null, 1], [$bad['sort'], $bad['avail'], $bad['brand'], $bad['min'], $bad['max'], $bad['sub'], $bad['page']]);
    $swapped = Catalogue::normaliseFilters(['min' => '50', 'max' => '10']);
    assert_same([10, 50], [$swapped['min'], $swapped['max']], 'min above max is swapped');
    assert_same(Catalogue::MAX_CEDIS, Catalogue::normaliseFilters(['max' => '99999999'])['max'] ?? Catalogue::MAX_CEDIS);
    assert_same(null, Catalogue::normaliseFilters(['min' => ['1']])['min'], 'arrays are ignored');
});

function shop_vars(array $over = []): array
{
    $bad = '<script>alert(1)</script>';
    return $over + [
        'title' => 'x', 'category' => ['id' => 1, 'slug' => 'c', 'name' => $bad, 'blurb' => '"><img src=x onerror=alert(1)>'], 'sub' => null,
        'subcategories' => [['id' => 2, 'slug' => 's', 'name' => $bad, 'n' => 3]],
        'facets' => ['all' => 5, 'categories' => [['id' => 1, 'slug' => 'c', 'name' => $bad, 'n' => 5]], 'in_stock' => 4, 'out_of_stock' => 1, 'brands' => [['name' => $bad, 'n' => 2]], 'lowest' => 1000, 'highest' => 9000],
        'filters' => ['sort' => 'price-asc', 'page' => 2, 'avail' => 'in', 'brand' => $bad, 'min' => 5, 'max' => 90, 'sub' => null],
        'items' => [['slug' => 'p"><script>', 'name' => $bad, 'pack_size' => '<b>5L</b>', 'price_pesewas' => 500, 'stock_status' => 'in_stock', 'category' => $bad, 'category_slug' => 'c', 'variant_id' => 7]],
        'total' => 40, 'pages' => 4, 'page' => 2, 'heroSlot' => 'shop/hero', 'trust' => Site::trust(), 'strip' => Site::shopStrip(),
    ];
}

test('a shop or category page escapes hostile text and keeps filters in its links', function (): void {
    Env::fake(['MOCK_DATA' => '1']);
    $empty = sys_get_temp_dir() . '/belis-noimg-' . bin2hex(random_bytes(4));
    mkdir($empty);
    Images::useRoot($empty);
    $html = View::render('pages/listing', shop_vars());
    Images::useRoot(null);
    @rmdir($empty);
    assert_not_contains('<script>alert(1)</script>', $html);
    assert_not_contains('<img src=x', $html);
    assert_not_contains('<b>5L</b>', $html);
    assert_contains('Showing 13 to 24 of 40 products', $html);
    assert_contains('sort=price-asc', $html);
    assert_contains('avail=in', $html);
    assert_contains('rel="next"', $html);
    assert_contains('rel="prev"', $html);
    assert_contains('action="/cart/add"', $html);
    assert_true(preg_match('/<script(?![^>]*\ssrc=)/', $html) !== 1, 'inline script');
});

test('the shop page shows its own headings and no rating or star filters', function (): void {
    Env::fake([]);
    $vars = shop_vars(['category' => null, 'subcategories' => [], 'total' => 0, 'items' => [], 'pages' => 1, 'page' => 1]);
    $html = View::render('pages/listing', $vars);
    assert_contains('Shop Our Products', $html);
    assert_contains('No products match', $html);
    assert_not_contains('Rating', $html);
    assert_not_contains('star', strtolower($html));
});

test('the cart keeps only positive whole numbers and never trusts names or prices', function (): void {
    $_SESSION = [];
    assert_same(0, Cart::count());
    assert_true(Cart::add(5, 2));
    assert_true(Cart::add(5, 3));
    assert_same(5, Cart::lines()[5]);
    Cart::set(5, 9);
    assert_same(9, Cart::count());
    Cart::set(99, 4);
    assert_same([5 => 9], Cart::lines(), 'setting a size that is not in the cart adds nothing');
    Cart::set(5, 0);
    assert_same([], Cart::lines());
    assert_same(1, Cart::clampQty(-3));
    assert_same(Cart::MAX_QTY, Cart::clampQty(999999999));
    $_SESSION = ['cart' => ['x' => 3, 7 => -1, 8 => '4', 9 => 2, 10 => 99999999]];
    assert_same([9 => 2, 10 => Cart::MAX_QTY], Cart::lines(), 'tampered session values are dropped or capped');
    $_SESSION = [];
});

test('the cart is limited to 50 lines', function (): void {
    $_SESSION = [];
    for ($i = 1; $i <= Cart::MAX_LINES; $i++) {
        assert_true(Cart::add($i, 1));
    }
    assert_true(!Cart::add(9999, 1), 'a 51st line was accepted');
    assert_true(Cart::add(1, 1), 'adding to an existing line still works');
    $_SESSION = [];
});

test('only a path on this site is accepted as the place to return to', function (): void {
    assert_same('/shop?sort=name', CartController::returnTo('/shop?sort=name'));
    foreach (['https://evil.example/', '//evil.example', 'javascript:alert(1)', "/a\nSet-Cookie: x=1", '/a\b', '', null, ['/x']] as $bad) {
        assert_same('/cart', CartController::returnTo($bad), json_encode($bad));
    }
});

test('cart routes are POST with CSRF and the cart page is public', function (): void {
    $inv = [];
    foreach (Belis\Core\App::router()->inventory() as $r) {
        $inv[$r['method'] . ' ' . $r['path']] = $r;
    }
    foreach (['POST /cart/add', 'POST /cart/update', 'POST /cart/remove'] as $k) {
        assert_true(isset($inv[$k]) && $inv[$k]['csrf'] === true, "$k needs CSRF");
    }
    assert_same('public', $inv['GET /cart']['policy']);
});
