<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Domain\Accounts;
use Belis\Domain\CatalogueAdmin;

// CTL-BIZ-001, CTL-AUTHZ-001, CTL-AUDIT-001: prices are owner-only, confirmed by code, versioned and audited.

/** @return array{0:OutboxMailer,1:int,2:int} mailer, staff id, owner id */
function products_env(): array
{
    $mail = shop_env()[0];
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A, 'APP_URL' => 'http://localhost', 'PAYMENTS_ADAPTER' => 'paystack']);
    $pdo = Db::fromEnv()->pdo();
    $pdo->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value_enc TEXT NOT NULL, updated_by INTEGER, updated_at INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE price_history (id INTEGER PRIMARY KEY AUTOINCREMENT, variant_id INTEGER, kind TEXT, old_value TEXT, new_value TEXT, changed_by INTEGER, created_at INTEGER)');
    $pdo->exec('ALTER TABLE categories ADD COLUMN name TEXT');
    $pdo->exec('ALTER TABLE categories ADD COLUMN parent_id INTEGER');
    $pdo->exec("UPDATE categories SET name = 'Cleaning'");
    $pdo->exec("INSERT INTO categories (id, is_published, name, parent_id) VALUES (2, 1, 'Sub cleaning', 1), (3, 1, 'Washrooms', NULL)");
    $a = new Accounts(Db::fromEnv(), $mail);
    $staff = $a->createVerified('staff@example.test', 'Kojo Staff', 'n/a', GOOD_PW, 'staff', 'content');
    $owner = $a->createVerified('owner@example.test', 'Boss', 'n/a', GOOD_PW, 'owner');
    return [$mail, $staff, $owner];
}

function login_as(OutboxMailer $mail, string $who): void
{
    post('/admin/sign-in', ['email' => $who . '@example.test', 'password' => GOOD_PW], '10.4.0.1');
    post('/admin/verify', ['code' => $mail->lastCode()], '10.4.0.1');
    Auth::reset();
}

function confirm_price(OutboxMailer $mail): void
{
    post('/admin/confirm/code', ['next' => '/admin/products/1'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/products/1', 'code' => $mail->lastCode()], '10.4.0.1');
}

function variant(int $id = 10): array
{
    return Db::fromEnv()->one('SELECT * FROM product_variants WHERE id = ?', [$id]);
}

function count_rows(string $where): int
{
    return (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM audit_log WHERE ' . $where)['n'];
}

test('prices are parsed from cedis text without floating point, and bad text is refused', function (): void {
    assert_same(4500, CatalogueAdmin::parsePrice('45'));
    assert_same(4550, CatalogueAdmin::parsePrice('45.5'));
    assert_same(4505, CatalogueAdmin::parsePrice('45.05'));
    assert_same(10000000, CatalogueAdmin::parsePrice('100000.00'));
    foreach (['', '0', '0.00', '-5', '45.555', '45,50', 'abc', '1e3', '100000.01', '1000000', ' '] as $bad) {
        assert_same(null, CatalogueAdmin::parsePrice($bad), $bad);
    }
    assert_same('45.05', CatalogueAdmin::plainPrice(4505));
    assert_same('0.99', CatalogueAdmin::plainPrice(99));
    assert_same('bleach-5-l', CatalogueAdmin::slugify('Bleach 5 L!'));
});

test('signed-out visitors and customers cannot reach product management', function (): void {
    [$mail] = products_env();
    $r = App::router();
    foreach (['/admin/products', '/admin/products/1', '/admin/products/new', '/admin/confirm'] as $p) {
        assert_same(302, $r->dispatch(new Request('GET', $p))->status, $p);
    }
    assert_same(302, post('/admin/products/1', ['name' => 'X'])->status);
    assert_same('Bleach', Db::fromEnv()->one('SELECT name FROM products WHERE id = 1')['name']);
    shop_done();
});

test('staff can see the list and edit content, stock and size names, and it is audited', function (): void {
    [$mail, $staff] = products_env();
    login_as($mail, 'staff');
    $r = App::router();
    $list = $r->dispatch(new Request('GET', '/admin/products'));
    assert_same(200, $list->status);
    assert_contains('Bleach', $list->body);
    assert_true(!str_contains($list->body, 'Add a product'), 'staff offered product creation');
    assert_same(302, post('/admin/products/1', ['name' => 'Household bleach', 'brand' => 'FreshClean', 'category_id' => '1', 'subcategory_id' => '2', 'summary' => 'Strong', 'description' => 'Long text', 'usage_notes' => '', 'is_published' => '1'])->status);
    $p = Db::fromEnv()->one('SELECT name, brand, subcategory_id, is_published FROM products WHERE id = 1');
    assert_same('Household bleach', $p['name']);
    assert_same('FreshClean', $p['brand']);
    assert_same(2, (int) $p['subcategory_id']);
    assert_same(1, count_rows("action = 'product.update' AND user_id = " . $staff));
    post('/admin/products/1/size/10', ['label' => '5 litres', 'stock_qty' => '5', 'stock_reason' => 'stock take']);
    $v = variant();
    assert_same('5 litres', $v['label']);
    assert_same('low', $v['stock_status']);
    assert_same('low', Db::fromEnv()->one('SELECT stock_status FROM products WHERE id = 1')['stock_status'], 'the shop listing was not updated');
    assert_same(4500, (int) $v['price_pesewas']);
    shop_done();
});

test('content edits are validated: bad category, foreign subcategory, empty name, long text', function (): void {
    [$mail] = products_env();
    login_as($mail, 'staff');
    foreach ([['name' => ''], ['category_id' => '99'], ['category_id' => '2'], ['subcategory_id' => '3'], ['summary' => str_repeat('x', 256)]] as $bad) {
        $res = post('/admin/products/1', $bad + ['name' => 'Ok', 'category_id' => '1']);
        assert_same(422, $res->status, json_encode($bad));
    }
    assert_same('Bleach', Db::fromEnv()->one('SELECT name FROM products WHERE id = 1')['name']);
    shop_done();
});

test('staff cannot change prices, bulk prices, add sizes or create products, even by forging the form', function (): void {
    [$mail] = products_env();
    login_as($mail, 'staff');
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '1.00']);
    assert_same(4500, (int) variant()['price_pesewas'], 'staff changed a price');
    assert_same(403, post('/admin/products/1/tiers/10', ['tier_min' => ['5'], 'tier_price' => ['30']])->status);
    assert_same(403, post('/admin/products/1/addsize', ['label' => 'x', 'price' => '1', 'stock_qty' => '100'])->status);
    assert_same(403, post('/admin/products/new', ['name' => 'Evil'])->status);
    assert_same(403, App::router()->dispatch(new Request('GET', '/admin/products/new'))->status);
    assert_same(1, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM products')['n']);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM price_history')['n']);
    $page = App::router()->dispatch(new Request('GET', '/admin/products/1'));
    assert_true(!str_contains($page->body, 'name="price"'), 'staff shown a price field');
    shop_done();
});

test('an owner price change needs a fresh code, is versioned and audited, and updates the shop price', function (): void {
    [$mail, , $owner] = products_env();
    login_as($mail, 'owner');
    $res = post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '48.00']);
    assert_contains('/admin/confirm', $res->headers['Location'] ?? '');
    assert_same(4500, (int) variant()['price_pesewas'], 'changed without the code');
    confirm_price($mail);
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '48.00']);
    assert_same(4800, (int) variant()['price_pesewas']);
    assert_same(4800, (int) Db::fromEnv()->one('SELECT price_pesewas FROM products WHERE id = 1')['price_pesewas'], 'listing price not synced');
    $h = Db::fromEnv()->one('SELECT kind, old_value, new_value, changed_by FROM price_history');
    assert_same(['price', '45.00', '48.00', $owner], [$h['kind'], $h['old_value'], $h['new_value'], (int) $h['changed_by']]);
    assert_same(1, count_rows("action = 'price.update' AND target = 'product:1/size:10'"));
    $page = App::router()->dispatch(new Request('GET', '/admin/products/1'));
    assert_contains('45.00', $page->body);
    shop_done();
});

test('a wrong code does not unlock price changes, and the confirmation expires', function (): void {
    [$mail] = products_env();
    login_as($mail, 'owner');
    post('/admin/confirm/code', ['next' => '/admin/products/1'], '10.4.0.1');
    assert_same(422, post('/admin/confirm', ['next' => '/admin/products/1', 'code' => '000000'], '10.4.0.1')->status);
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '48.00']);
    assert_same(4500, (int) variant()['price_pesewas']);
    post('/admin/confirm', ['next' => '/admin/products/1', 'code' => $mail->lastCode()], '10.4.0.1');
    $_SESSION['stepup']['at'] = time() - 601;
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '48.00']);
    assert_same(4500, (int) variant()['price_pesewas'], 'expired confirmation still worked');
    shop_done();
});

test('the confirm page only returns to allowed admin pages', function (): void {
    assert_same('/admin/products/12', \Belis\Support\StepUp::safeNext('/admin/products/12'));
    assert_same('/admin/settings', \Belis\Support\StepUp::safeNext('/admin/settings'));
    foreach (['https://evil.example', '//evil.example', '/admin/products/1/../../x', '/account', "/admin/products/1\r\nX: y", null, ['/admin']] as $bad) {
        assert_same('/admin', \Belis\Support\StepUp::safeNext($bad));
    }
});

test('a price change of more than 50 percent needs the extra tick box, and bad prices are refused', function (): void {
    [$mail] = products_env();
    login_as($mail, 'owner');
    confirm_price($mail);
    Db::fromEnv()->run('DELETE FROM bulk_tiers');
    assert_same(422, post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '4.50'])->status);
    assert_same(4500, (int) variant()['price_pesewas'], 'a big drop went through unconfirmed');
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '4.50', 'confirm_big' => '1']);
    assert_same(450, (int) variant()['price_pesewas']);
    foreach (['abc', '0', '-3', '1.999', '999999999'] as $bad) {
        assert_same(422, post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => $bad, 'confirm_big' => '1'])->status, $bad);
    }
    assert_same(450, (int) variant()['price_pesewas']);
    shop_done();
});

test('bulk prices: validated, lower than the base and falling, replaced as a set, versioned', function (): void {
    [$mail] = products_env();
    login_as($mail, 'owner');
    confirm_price($mail);
    $bad = [
        [['10', '50.00']], [['5', '40'], ['10', '41']], [['10', '40'], ['10', '39']], [['1', '40']], [['x', '40']], [['10', 'abc']],
        [['2', '44'], ['3', '43'], ['4', '42'], ['5', '41'], ['6', '40'], ['7', '39'], ['8', '38']],
    ];
    foreach ($bad as $rows) {
        $res = post('/admin/products/1/tiers/10', ['tier_min' => array_column($rows, 0), 'tier_price' => array_column($rows, 1)]);
        assert_same(422, $res->status, json_encode($rows));
    }
    assert_same([['min_qty' => 10, 'unit_price_pesewas' => 4000]], Db::fromEnv()->all('SELECT min_qty, unit_price_pesewas FROM bulk_tiers'), 'a rejected form changed the tiers');
    assert_same(302, post('/admin/products/1/tiers/10', ['tier_min' => ['20', '10', '', ''], 'tier_price' => ['35.00', '40.00', '', '']])->status);
    assert_same([['min_qty' => 10, 'unit_price_pesewas' => 4000], ['min_qty' => 20, 'unit_price_pesewas' => 3500]], Db::fromEnv()->all('SELECT min_qty, unit_price_pesewas FROM bulk_tiers ORDER BY min_qty'));
    assert_same('tiers', Db::fromEnv()->one('SELECT kind FROM price_history')['kind']);
    assert_same(1, count_rows("action = 'price.tiers'"));
    $before = (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM price_history')['n'];
    post('/admin/products/1/tiers/10', ['tier_min' => ['10', '20'], 'tier_price' => ['40.00', '35.00']]);
    assert_same($before, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM price_history')['n'], 'an unchanged save made history');
    post('/admin/products/1/tiers/10', ['tier_min' => ['', ''], 'tier_price' => ['', '']]);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM bulk_tiers')['n']);
    shop_done();
});

test('a new base price cannot sit below an existing bulk price', function (): void {
    [$mail] = products_env();
    login_as($mail, 'owner');
    confirm_price($mail);
    assert_same(422, post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '39.00', 'confirm_big' => '1'])->status);
    assert_same(4500, (int) variant()['price_pesewas']);
    shop_done();
});

test('the owner can add a size and create a product, which starts hidden with a price history', function (): void {
    [$mail, , $owner] = products_env();
    login_as($mail, 'owner');
    confirm_price($mail);
    assert_same(302, post('/admin/products/1/addsize', ['label' => '1 L', 'price' => '12.50', 'stock_qty' => '100'])->status);
    assert_same(2, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM product_variants WHERE product_id = 1')['n']);
    assert_same(1250, (int) Db::fromEnv()->one('SELECT price_pesewas FROM products WHERE id = 1')['price_pesewas'], 'listing price should be the cheapest size');
    assert_same(422, post('/admin/products/1/addsize', ['label' => '', 'price' => 'x', 'stock_qty' => '100'])->status);
    $res = post('/admin/products/new', ['name' => 'Hand Soap', 'category_id' => '3', 'label' => '500 ml', 'price' => '18.00', 'stock_qty' => '100', 'is_published' => '1']);
    assert_same(302, $res->status);
    $p = Db::fromEnv()->one("SELECT id, slug, is_published, is_mock, price_pesewas FROM products WHERE name = 'Hand Soap'");
    assert_same('hand-soap', $p['slug']);
    assert_same(0, (int) $p['is_published'], 'a new product went live');
    assert_same(0, (int) $p['is_mock']);
    assert_same(1800, (int) $p['price_pesewas']);
    post('/admin/products/new', ['name' => 'Hand Soap', 'category_id' => '3', 'label' => '1 L', 'price' => '30', 'stock_qty' => '100']);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM products WHERE slug = 'hand-soap-2'")['n'], 'slug not made unique');
    assert_same(422, post('/admin/products/new', ['name' => 'X', 'category_id' => '3', 'label' => '', 'price' => 'nope', 'stock_qty' => 'bad'])->status);
    assert_true(count_rows("action = 'product.create' AND user_id = " . $owner) === 2);
    shop_done();
});

test('history and audit never contain anything but prices and field names', function (): void {
    [$mail] = products_env();
    login_as($mail, 'owner');
    confirm_price($mail);
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '100', 'price' => '48.00']);
    post('/admin/products/1', ['name' => 'Renamed', 'category_id' => '1', 'is_published' => '1']);
    foreach (Db::fromEnv()->all('SELECT action, target, detail FROM audit_log') as $r) {
        assert_true(!str_contains(implode(' ', array_map('strval', $r)), 'Renamed'), 'content leaked into the audit log');
    }
    assert_same('name', Db::fromEnv()->one("SELECT detail FROM audit_log WHERE action = 'product.update'")['detail']);
    shop_done();
});

test('the other product routes exist with the right policies and need a CSRF token', function (): void {
    [$mail] = products_env();
    login_as($mail, 'owner');
    foreach (['/admin/products/1', '/admin/products/1/size/10', '/admin/products/1/tiers/10', '/admin/products/1/addsize', '/admin/products/new', '/admin/confirm', '/admin/confirm/code'] as $p) {
        assert_same(419, App::router()->dispatch(new Request('POST', $p, [], ['_csrf' => 'bad']))->status, $p);
    }
    assert_same(404, App::router()->dispatch(new Request('GET', '/admin/products/999'))->status);
    assert_same(404, post('/admin/products/1/size/999', ['label' => 'x', 'stock_qty' => '100'])->status);
    shop_done();
});

test('the preview people can look at products but not change anything', function (): void {
    products_env();
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'owner', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    Auth::reset();
    $r = App::router();
    assert_same(200, $r->dispatch(new Request('GET', '/admin/products'))->status);
    assert_same(200, $r->dispatch(new Request('GET', '/admin/products/1'))->status);
    assert_same(302, post('/admin/products/1', ['name' => 'Hacked', 'category_id' => '1'])->status);
    assert_same(302, post('/admin/products/1/size/10', ['label' => 'x', 'stock_qty' => '5', 'stock_reason' => 'stock take', 'price' => '1'])->status);
    assert_same('Bleach', Db::fromEnv()->one('SELECT name FROM products WHERE id = 1')['name']);
    assert_same(4500, (int) variant()['price_pesewas']);
    shop_done();
});
