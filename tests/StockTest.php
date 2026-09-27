<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Domain\CatalogueAdmin;
use Belis\Domain\Cart;
use Belis\Domain\Orders;

// CTL-BIZ-002, THR-026: stock is a counted number, reduced once when an order is paid, never below zero silently.

function stock_env(): OutboxMailer
{
    $mail = gaps_env();
    Belis\Payments\Payments::useForTests(new FakeAdapter());
    return $mail;
}

function qty(int $id = 10): int
{
    return (int) Db::fromEnv()->one('SELECT stock_qty FROM product_variants WHERE id = ?', [$id])['stock_qty'];
}

function pay(string $ref): string
{
    $o = Db::fromEnv()->one('SELECT payment_reference, total_pesewas FROM orders WHERE ref = ?', [$ref]);
    $fake = new FakeAdapter();
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    return (new Orders(Db::fromEnv()))->confirm($o['payment_reference'], $fake, 'callback');
}

test('a paid order takes its items off stock once, and replays change nothing', function (): void {
    $mail = stock_env();
    $ref = make_order(add_customer($mail), 'pending', 3);
    assert_same(100, qty(), 'stock changed before payment');
    assert_same('paid', pay($ref));
    assert_same(97, qty());
    pay($ref);
    (new Orders(Db::fromEnv()))->confirm(Db::fromEnv()->one('SELECT payment_reference FROM orders')['payment_reference'], new FakeAdapter(), 'webhook');
    assert_same(97, qty(), 'a replay took stock again');
    $h = Db::fromEnv()->one("SELECT delta, qty_after, kind, order_id FROM stock_history WHERE kind = 'order'");
    assert_same([-3, 97, 'order'], [(int) $h['delta'], (int) $h['qty_after'], $h['kind']]);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM stock_history WHERE kind = 'order'")['n']);
    shop_done();
});

test('failed, pending and cancelled payments never touch stock', function (): void {
    $mail = stock_env();
    $uid = add_customer($mail);
    $ref = make_order($uid, 'pending', 5);
    $o = Db::fromEnv()->one('SELECT payment_reference FROM orders');
    $fake = new FakeAdapter();
    $fake->say('failed', 0, $o['payment_reference']);
    (new Orders(Db::fromEnv()))->confirm($o['payment_reference'], $fake, 'callback');
    (new Orders(Db::fromEnv()))->cancel($ref, $uid, new FakeAdapter());
    assert_same(100, qty());
    shop_done();
});

test('the shop label follows the count: low at 10 or fewer, out at zero, and the product follows its sizes', function (): void {
    assert_same('in_stock', CatalogueAdmin::labelFor(11));
    assert_same('low', CatalogueAdmin::labelFor(10));
    assert_same('low', CatalogueAdmin::labelFor(1));
    assert_same('out', CatalogueAdmin::labelFor(0));
    $mail = stock_env();
    pay(make_order(add_customer($mail), 'pending', 91));
    assert_same(9, qty());
    assert_same('low', Db::fromEnv()->one('SELECT stock_status FROM product_variants WHERE id = 10')['stock_status']);
    assert_same('low', Db::fromEnv()->one('SELECT stock_status FROM products WHERE id = 1')['stock_status']);
    pay(make_order(add_customer($mail, 'kofi@example.test'), 'pending', 9));
    assert_same(0, qty());
    assert_same('out', Db::fromEnv()->one('SELECT stock_status FROM products WHERE id = 1')['stock_status']);
    shop_done();
});

test('checkout refuses more than is in stock, and a sold-out size, with a clear message', function (): void {
    $mail = stock_env();
    add_customer($mail);
    customer_login($mail);
    Db::fromEnv()->run('UPDATE product_variants SET stock_qty = 3 WHERE id = 10');
    Cart::add(10, 4);
    $res = post('/checkout', address());
    assert_same('/cart', $res->headers['Location'] ?? '');
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM orders')['n']);
    assert_contains('Only 3 left', implode(' ', array_column(Belis\Support\Flash::take(), 'text')));
    $cart = App::router()->dispatch(new Request('GET', '/cart'));
    assert_contains('Only 3 left', $cart->body);
    Cart::set(10, 3);
    assert_same(302, post('/checkout', address())->status);
    assert_same(1, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM orders')['n']);
    Db::fromEnv()->run('UPDATE product_variants SET stock_qty = 0 WHERE id = 10');
    Cart::add(10, 1);
    post('/checkout', address());
    assert_contains('sold out', implode(' ', array_column(Belis\Support\Flash::take(), 'text')));
    shop_done();
});

test('a paid order that cannot be filled empties the stock, is flagged for staff and is logged', function (): void {
    $mail = stock_env();
    $a = add_customer($mail);
    $b = add_customer($mail, 'kofi@example.test');
    Db::fromEnv()->run('UPDATE product_variants SET stock_qty = 4 WHERE id = 10');
    $first = make_order($a, 'pending', 3);
    $second = make_order($b, 'pending', 3);
    pay($first);
    assert_same(1, qty());
    assert_same('paid', pay($second), 'money was taken, so the order is paid');
    assert_same(0, qty());
    $o = Db::fromEnv()->one('SELECT status, needs_review FROM orders WHERE ref = ?', [$second]);
    assert_same(['paid', 1], [$o['status'], (int) $o['needs_review']]);
    assert_same(0, (int) Db::fromEnv()->one('SELECT needs_review FROM orders WHERE ref = ?', [$first])['needs_review']);
    assert_contains('short by 2', Db::fromEnv()->one("SELECT reason FROM stock_history WHERE order_id = (SELECT id FROM orders WHERE ref = '" . $second . "')")['reason']);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM payment_events WHERE outcome = 'stock_short'")['n']);
    shop_done();
});

test('if stock cannot be written, the order does not become paid (one transaction)', function (): void {
    $mail = stock_env();
    $ref = make_order(add_customer($mail), 'pending', 2);
    Db::fromEnv()->pdo()->exec('DROP TABLE stock_history');
    $threw = false;
    try {
        pay($ref);
    } catch (Throwable) {
        $threw = true;
    }
    assert_true($threw);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM orders WHERE ref = ?', [$ref])['status'], 'paid without its stock change');
    assert_same(100, qty(), 'stock changed without the order');
    shop_done();
});

test('the content role adjusts a count only with a reason, and it is logged and audited', function (): void {
    $mail = stock_env();
    login_as($mail, 'staff');
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '60']);
    assert_same(100, qty(), 'the count changed without a reason');
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '60', 'stock_reason' => str_repeat('x', 201)]);
    assert_same(100, qty());
    foreach (['abc', '-5', '1.5', '', '9999999'] as $bad) {
        assert_same(422, post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => $bad, 'stock_reason' => 'x'])->status, $bad);
    }
    assert_same(302, post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '60', 'stock_reason' => 'Stock take'])->status);
    assert_same(60, qty());
    $h = Db::fromEnv()->one("SELECT delta, qty_after, reason, changed_by FROM stock_history WHERE kind = 'adjust'");
    assert_same([-40, 60, 'Stock take'], [(int) $h['delta'], (int) $h['qty_after'], $h['reason']]);
    assert_true($h['changed_by'] !== null);
    assert_same(1, count_rows("action = 'size.update' AND detail LIKE '%stock%'"));
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '60']);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM stock_history WHERE kind = 'adjust'")['n'], 'an unchanged count was logged');
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock_qty' => '0', 'stock_reason' => 'Sold out elsewhere']);
    assert_same('out', Db::fromEnv()->one('SELECT stock_status FROM product_variants WHERE id = 10')['stock_status']);
    $page = App::router()->dispatch(new Request('GET', '/admin/products/1'));
    assert_contains('Sold out elsewhere', $page->body);
    assert_contains('Stock history', $page->body);
    shop_done();
});

test('a new size and a new product need a count and start their history', function (): void {
    $mail = stock_env();
    login_as($mail, 'owner');
    post('/admin/confirm/code', ['next' => '/admin/products/1'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/products/1', 'code' => $mail->lastCode()], '10.4.0.1');
    assert_same(422, post('/admin/products/1/addsize', ['label' => '1 L', 'price' => '12', 'stock_qty' => ''])->status);
    assert_same(302, post('/admin/products/1/addsize', ['label' => '1 L', 'price' => '12', 'stock_qty' => '8'])->status);
    $v = Db::fromEnv()->one("SELECT id, stock_qty, stock_status FROM product_variants WHERE label = '1 L'");
    assert_same([8, 'low'], [(int) $v['stock_qty'], $v['stock_status']]);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM stock_history WHERE variant_id = ? AND kind = 'initial'", [(int) $v['id']])['n']);
    assert_same(422, post('/admin/products/new', ['name' => 'Soap', 'category_id' => '3', 'label' => '1 L', 'price' => '5', 'stock_qty' => 'many'])->status);
    assert_same(302, post('/admin/products/new', ['name' => 'Soap', 'category_id' => '3', 'label' => '1 L', 'price' => '5', 'stock_qty' => '30'])->status);
    assert_same(30, (int) Db::fromEnv()->one("SELECT stock_qty FROM product_variants WHERE label = '1 L' AND product_id <> 1")['stock_qty']);
    shop_done();
});

test('the stock history cannot be edited', function (): void {
    $mail = stock_env();
    as_person($mail, 'owner');
    $mig = (string) file_get_contents(BASE_PATH . '/database/migrations/012_stock_counts.sql');
    assert_contains('BEFORE UPDATE ON stock_history', $mig);
    assert_contains('BEFORE DELETE ON stock_history', $mig);
    $src = (string) file_get_contents(BASE_PATH . '/src/Domain/CatalogueAdmin.php');
    assert_true(preg_match('/(UPDATE|DELETE FROM) stock_history/i', $src) !== 1, 'code changes the stock history');
    shop_done();
});
