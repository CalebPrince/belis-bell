<?php
declare(strict_types=1);

use Belis\Core\Env;
use Belis\Core\View;
use Belis\Domain\Pricing;
use Belis\Domain\ProductContent;
use Belis\Support\Images;
use Belis\Support\Site;

// Product page (PG-039 to PG-043): server-side price maths, plain-text content parsing, escaping.

test('unit price uses the largest tier the quantity reaches, otherwise the base price', function (): void {
    $tiers = [['min_qty' => 10, 'unit_price_pesewas' => 5700], ['min_qty' => 50, 'unit_price_pesewas' => 5400], ['min_qty' => 100, 'unit_price_pesewas' => 5100]];
    assert_same(6000, Pricing::unitPrice(6000, $tiers, 1));
    assert_same(6000, Pricing::unitPrice(6000, $tiers, 9));
    assert_same(5700, Pricing::unitPrice(6000, $tiers, 10));
    assert_same(5700, Pricing::unitPrice(6000, $tiers, 49));
    assert_same(5400, Pricing::unitPrice(6000, $tiers, 50));
    assert_same(5100, Pricing::unitPrice(6000, $tiers, 100000));
    assert_same(5100, Pricing::unitPrice(6000, array_reverse($tiers), 500), 'tier order must not matter');
});

test('totals are whole pesewas and quantities are clamped', function (): void {
    $tiers = [['min_qty' => 10, 'unit_price_pesewas' => 5700]];
    assert_same(57000, Pricing::total(6000, $tiers, 10));
    assert_same(6000, Pricing::total(6000, $tiers, 0), 'zero becomes one');
    assert_same(6000, Pricing::total(6000, $tiers, -5), 'negative becomes one');
    assert_same(6000 * Pricing::MAX_QTY, Pricing::total(6000, [], 999999999), 'huge quantity is capped');
    assert_same(6000, Pricing::unitPrice(6000, [['min_qty' => 0, 'unit_price_pesewas' => 1]], 5), 'a tier with min 0 is ignored');
});

test('savings are whole percents rounded down', function (): void {
    assert_same(5, Pricing::savePercent(6000, 5700));
    assert_same(0, Pricing::savePercent(6000, 6000));
    assert_same(0, Pricing::savePercent(6000, 7000));
    assert_same(0, Pricing::savePercent(0, 0));
    assert_same(33, Pricing::savePercent(300, 201));
});

test('bulk table rows cover every quantity with no gap', function (): void {
    $rows = Pricing::rows(6000, [['min_qty' => 10, 'unit_price_pesewas' => 5700], ['min_qty' => 50, 'unit_price_pesewas' => 5400], ['min_qty' => 100, 'unit_price_pesewas' => 5100]]);
    assert_same(['1 to 9', '10 to 49', '50 to 99', '100+'], array_column($rows, 'label'));
    assert_same([0, 5, 10, 15], array_column($rows, 'save_percent'));
    assert_same([], Pricing::rows(6000, []));
    assert_same(['1+'], array_column(Pricing::rows(6000, [['min_qty' => 1, 'unit_price_pesewas' => 5000]]), 'label'));
});

test('mock tiers are 5, 10 and 15 percent off from 10, 50 and 100 units', function (): void {
    $t = Pricing::mockTiers(6200);
    assert_same([10, 50, 100], array_column($t, 'min_qty'));
    assert_same([5890, 5580, 5270], array_column($t, 'unit_price_pesewas'));
});

test('specifications, features, highlights and uses are parsed from plain text', function (): void {
    assert_same([['label' => 'Type', 'value' => 'Bleach'], ['label' => 'Use', 'value' => 'a|b']], ProductContent::specs("Type|Bleach\n\nbad line\nUse|a|b\n |x"));
    assert_same(['One', 'Two'], ProductContent::features("One\r\n\r\n  Two  \n"));
    assert_same([], ProductContent::specs(null));
    $h = ProductContent::highlights("shield-check|Kills germs|Line\nnot-an-icon|Other|More\nonly|two");
    assert_same(['shield-check', 'award'], array_column($h, 'icon'), 'unknown icon names fall back to a safe one');
    assert_same(['laundry', 'floors'], array_column(ProductContent::uses('Laundry, floors, ../etc, laundry, <b>'), 'slug'));
    assert_same('Hello there.', ProductContent::firstSentence('Hello there. More text follows.'));
});

test('a product page escapes hostile text everywhere and shows the bulk panel', function (): void {
    Env::fake(['MOCK_DATA' => '1']);
    $empty = sys_get_temp_dir() . '/belis-noimg-' . bin2hex(random_bytes(4));
    mkdir($empty);
    Images::useRoot($empty);
    $bad = '<script>alert(1)</script>';
    $product = ['id' => 1, 'category_id' => 1, 'slug' => 'p', 'name' => $bad, 'pack_size' => $bad, 'summary' => $bad, 'description' => $bad, 'usage_notes' => '"><img src=x onerror=alert(1)>',
        'price_pesewas' => 1234, 'currency' => 'GHS', 'stock_status' => 'low', 'category' => $bad, 'category_slug' => 'c'];
    $variants = [
        ['id' => 1, 'label' => $bad, 'price_pesewas' => 1234, 'stock_status' => 'low', 'tiers' => [['min_qty' => 10, 'unit_price_pesewas' => 1100]]],
        ['id' => 2, 'label' => 'Big', 'price_pesewas' => 5000, 'stock_status' => 'in_stock', 'tiers' => []],
    ];
    $html = View::render('pages/product', [
        'title' => 'x', 'extra_js' => 'js/product.js', 'product' => $product, 'summary' => $bad, 'variants' => $variants, 'selected' => 0, 'related' => [],
        'specs' => [['label' => $bad, 'value' => $bad]], 'features' => [$bad], 'highlights' => [['icon' => 'award', 'title' => $bad, 'line' => $bad]],
        'uses' => [['slug' => 'laundry', 'label' => 'Laundry']], 'trust' => Site::trust(), 'deliveryReturns' => [$bad], 'whatsapp' => null,
    ]);
    Images::useRoot(null);
    @rmdir($empty);
    assert_not_contains('<script>alert(1)</script>', $html);
    assert_not_contains('<img src=x', $html);
    assert_contains('GH₵ 12.34', $html);
    assert_contains('Buying in bulk?', $html);
    assert_contains('<th scope="row">10+</th>', $html, 'tier row for 10 and over');
    assert_contains('data-tiers="10:1100"', $html);
    assert_contains('Request bulk quote', $html);
    assert_contains('js/product.js', $html);
    assert_true(preg_match('/<script(?![^>]*\ssrc=)/', $html) !== 1, 'inline script in the product page');
});

test('a size without bulk tiers shows no tier table, and a single size shows no size chooser', function (): void {
    Env::fake([]);
    $empty = sys_get_temp_dir() . '/belis-noimg-' . bin2hex(random_bytes(4));
    mkdir($empty);
    Images::useRoot($empty);
    $product = ['id' => 1, 'category_id' => 1, 'slug' => 'p', 'name' => 'Item', 'pack_size' => '1', 'summary' => '', 'description' => 'd', 'usage_notes' => '',
        'price_pesewas' => 1000, 'currency' => 'GHS', 'stock_status' => 'in_stock', 'category' => 'C', 'category_slug' => 'c'];
    $html = View::render('pages/product', [
        'title' => 'x', 'product' => $product, 'summary' => '', 'selected' => 0, 'related' => [], 'specs' => [], 'features' => [], 'highlights' => [], 'uses' => [],
        'variants' => [['id' => 0, 'label' => '1', 'price_pesewas' => 1000, 'stock_status' => 'in_stock', 'tiers' => []]],
        'trust' => [], 'deliveryReturns' => [], 'whatsapp' => null,
    ]);
    Images::useRoot(null);
    @rmdir($empty);
    assert_not_contains('tier-table', $html);
    assert_not_contains('class="sizes"', $html);
    assert_not_contains('Common Uses', $html);
    assert_contains('Request bulk quote', $html);
});

test('a template that throws leaves no half-rendered page behind', function (): void {
    $before = ob_get_level();
    $threw = false;
    $level = error_reporting(0); // the missing data raises notices we do not want in the test output
    try {
        View::render('pages/product', ['title' => 'x']);
    } catch (Throwable) {
        $threw = true;
    }
    error_reporting($level);
    assert_true($threw, 'rendering with missing data should fail');
    assert_same($before, ob_get_level(), 'an output buffer was left open');
});
