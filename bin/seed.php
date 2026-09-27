<?php
declare(strict_types=1);

// Loads MOCK data for development and staging. Refuses to run in production (CTL-ENV-001).
require __DIR__ . '/_boot.php';

use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Domain\Pricing;

if ((Env::get('APP_ENV', 'production') ?? 'production') === 'production') {
    fwrite(STDERR, "Refusing to seed: APP_ENV is production.\n");
    exit(1);
}
if (!Env::bool('MOCK_DATA')) {
    fwrite(STDERR, "Refusing to seed: set MOCK_DATA=1 to confirm this is a non-production mock environment.\n");
    exit(1);
}
$data = require dirname(__DIR__) . '/database/seed/mock_data.php';
$extras = require dirname(__DIR__) . '/database/seed/mock_extras.php';
$db = Db::fromEnv();

foreach ($data['categories'] as [$slug, $name, $blurb, $order]) {
    $db->run(
        'INSERT INTO categories (slug, name, blurb, sort_order, is_mock) VALUES (?, ?, ?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE name = VALUES(name), blurb = VALUES(blurb), sort_order = VALUES(sort_order)',
        [$slug, $name, $blurb, $order],
    );
}

foreach ($data['subcategories'] ?? [] as [$parentSlug, $slug, $name, $order]) {
    $parent = $db->one('SELECT id FROM categories WHERE slug = ?', [$parentSlug]);
    if ($parent === null) {
        continue;
    }
    $db->run(
        'INSERT INTO categories (parent_id, slug, name, blurb, sort_order, is_mock) VALUES (?, ?, ?, ?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE parent_id = VALUES(parent_id), name = VALUES(name), sort_order = VALUES(sort_order)',
        [(int) $parent['id'], $slug, $name, '', $order],
    );
}

// Invented brand names taken from the owner's mockup, handed out in turn. Not real brands.
$mockBrands = ['Cleaning Essentials', 'Hygiene Pro', 'FreshClean', 'EcoCare'];
$productIndex = 0;

// Sample content for products that have no entry in mock_extras.php.
$defaultUses = [
    'cleaning-products' => 'floors,bathrooms,kitchens',
    'paper-products-disposables' => 'bathrooms,kitchens',
    'washroom-supplies' => 'bathrooms',
    'bins-waste-management' => 'bins,kitchens',
    'cleaning-tools-accessories' => 'floors,bathrooms',
    'general-supplies' => 'laundry,kitchens',
];

$variantCount = 0;
foreach ($data['products'] as [$cat, $slug, $name, $pack, $price, $stock, $description, $usage]) {
    $row = $db->one('SELECT id FROM categories WHERE slug = ?', [$cat]);
    if ($row === null) {
        continue;
    }
    $e = $extras[$slug] ?? [];
    $brand = $e['brand'] ?? $mockBrands[$productIndex++ % count($mockBrands)];
    $subRow = isset($e['sub']) ? $db->one('SELECT id FROM categories WHERE slug = ?', [$e['sub']]) : null;
    $subId = $subRow === null ? null : (int) $subRow['id'];
    $variants = $e['variants'] ?? [[$pack, $price, $stock]];
    // The product row mirrors its first size, so listing cards keep working.
    [$pack, $price, $stock] = $variants[0];

    $summary = $e['summary'] ?? null;
    $specs = $e['specs'] ?? "Pack size|{$pack}\nNote|Sample specifications, not real";
    $features = $e['features'] ?? "Sample feature: everyday quality\nSample feature: suited to homes and businesses\nSample feature: available for bulk orders";
    $highlights = $e['highlights'] ?? "award|Sample highlight|Real highlights come from the supplier's data sheet.\ntruck|Fast delivery|Across Ghana (sample).\ntag|Great value|Bulk prices available (sample).";
    $uses = $e['uses'] ?? ($defaultUses[$cat] ?? 'floors');

    $db->run(
        'INSERT INTO products (category_id, subcategory_id, brand, slug, name, pack_size, summary, description, usage_notes, specs, features, highlights, uses, price_pesewas, stock_status, is_mock) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE subcategory_id = VALUES(subcategory_id), brand = VALUES(brand), name = VALUES(name), pack_size = VALUES(pack_size), summary = VALUES(summary), description = VALUES(description), '
        . 'usage_notes = VALUES(usage_notes), specs = VALUES(specs), features = VALUES(features), highlights = VALUES(highlights), uses = VALUES(uses), '
        . 'price_pesewas = VALUES(price_pesewas), stock_status = VALUES(stock_status)',
        [(int) $row['id'], $subId, $brand, $slug, $name, $pack, $summary, $description, $usage, $specs, $features, $highlights, $uses, $price, $stock],
    );
    $product = $db->one('SELECT id FROM products WHERE slug = ?', [$slug]);
    $productId = (int) $product['id'];

    // Sizes and their mock bulk tiers are rebuilt on every seed. Bulk tiers go with their size (cascade).
    $db->run('DELETE FROM product_variants WHERE product_id = ? AND is_mock = 1', [$productId]);
    foreach ($variants as $i => [$label, $vPrice, $vStock]) {
        $db->run(
            'INSERT INTO product_variants (product_id, label, price_pesewas, stock_status, stock_qty, sort_order, is_mock) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$productId, $label, $vPrice, $vStock, $vStock === 'in_stock' ? 100 : ($vStock === 'low' ? 5 : 0), $i],
        );
        $variantId = (int) $db->pdo()->lastInsertId();
        foreach (Pricing::mockTiers((int) $vPrice) as $tier) {
            $db->run(
                'INSERT INTO bulk_tiers (variant_id, min_qty, unit_price_pesewas, is_mock) VALUES (?, ?, ?, 1)',
                [$variantId, $tier['min_qty'], $tier['unit_price_pesewas']],
            );
        }
        $variantCount++;
    }
}
echo 'mock data loaded (' . count($data['categories']) . ' categories, ' . count($data['products']) . " products, {$variantCount} sizes with mock bulk tiers)\n";
