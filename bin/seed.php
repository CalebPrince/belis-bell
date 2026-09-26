<?php
declare(strict_types=1);

// Loads MOCK data for development and staging. Refuses to run in production (CTL-ENV-001).
require __DIR__ . '/_boot.php';

use Belis\Core\Db;
use Belis\Core\Env;

if ((Env::get('APP_ENV', 'production') ?? 'production') === 'production') {
    fwrite(STDERR, "Refusing to seed: APP_ENV is production.\n");
    exit(1);
}
if (!Env::bool('MOCK_DATA')) {
    fwrite(STDERR, "Refusing to seed: set MOCK_DATA=1 to confirm this is a non-production mock environment.\n");
    exit(1);
}
$data = require dirname(__DIR__) . '/database/seed/mock_data.php';
$db = Db::fromEnv();
foreach ($data['categories'] as [$slug, $name, $blurb, $order]) {
    $db->run(
        'INSERT INTO categories (slug, name, blurb, sort_order, is_mock) VALUES (?, ?, ?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE name = VALUES(name), blurb = VALUES(blurb), sort_order = VALUES(sort_order)',
        [$slug, $name, $blurb, $order],
    );
}
foreach ($data['products'] as [$cat, $slug, $name, $pack, $price, $stock]) {
    $row = $db->one('SELECT id FROM categories WHERE slug = ?', [$cat]);
    if ($row === null) {
        continue;
    }
    $db->run(
        'INSERT INTO products (category_id, slug, name, pack_size, price_pesewas, stock_status, is_mock) VALUES (?, ?, ?, ?, ?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE name = VALUES(name), pack_size = VALUES(pack_size), price_pesewas = VALUES(price_pesewas), stock_status = VALUES(stock_status)',
        [(int) $row['id'], $slug, $name, $pack, $price, $stock],
    );
}
echo 'mock data loaded (' . count($data['categories']) . ' categories, ' . count($data['products']) . " products)\n";
