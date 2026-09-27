<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Domain\Accounts;
use Belis\Domain\Orders;

// CTL-AUTHZ-001, CTL-AUDIT-001, CTL-DATA-001: staff can see and progress orders; nobody else can; it is all audited.

/** @return array{0:OutboxMailer,1:int,2:int} mailer, staff id, customer id */
function admin_orders_env(): array
{
    $mail = shop_env()[0];
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A, 'APP_URL' => 'http://localhost', 'PAYMENTS_ADAPTER' => 'paystack']);
    $pdo = Db::fromEnv()->pdo();
    $pdo->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value_enc TEXT NOT NULL, updated_by INTEGER, updated_at INTEGER NOT NULL)');
    $a = new Accounts(Db::fromEnv(), $mail);
    $customer = $a->createVerified('ama@example.test', 'Ama Mensah', '+233 24 000 0000', GOOD_PW, 'customer');
    $staff = $a->createVerified('staff@example.test', 'Kojo Staff', 'n/a', GOOD_PW, 'staff', 'fulfilment');
    return [$mail, $staff, $customer];
}

function make_order(int $customerId, string $status = 'paid', int $qty = 1, string $name = 'Ama Mensah'): string
{
    $cart = ['lines' => [['variant_id' => 10, 'name' => 'Bleach', 'label' => '5 L', 'stock_status' => 'in_stock', 'qty' => $qty, 'unit_pesewas' => 4500, 'line_pesewas' => 4500 * $qty]], 'items' => $qty, 'subtotal' => 4500 * $qty, 'delivery' => null, 'total' => 4500 * $qty];
    $o = (new Orders(Db::fromEnv()))->create($customerId, $cart, address(['name' => $name]), ['key' => 'standard', 'name' => 'Standard Delivery', 'fee' => 2000, 'line' => '']);
    Db::fromEnv()->run('UPDATE orders SET status = ? WHERE id = ?', [$status, $o['id']]);
    if ($status === 'paid') {
        Db::fromEnv()->run('UPDATE orders SET paid_at = ? WHERE id = ?', [time(), $o['id']]);
    }
    return $o['ref'];
}

function staff_login(OutboxMailer $mail): void
{
    post('/admin/sign-in', ['email' => 'staff@example.test', 'password' => GOOD_PW], '10.3.0.1');
    post('/admin/verify', ['code' => $mail->lastCode()], '10.3.0.1');
    Auth::reset();
}

test('signed-out visitors and customers cannot see any admin order page', function (): void {
    [$mail, , $cust] = admin_orders_env();
    $ref = make_order($cust);
    $r = App::router();
    foreach (['/admin/orders', '/admin/orders/' . $ref] as $p) {
        assert_same(302, $r->dispatch(new Request('GET', $p))->status, $p);
    }
    assert_same(302, post('/admin/orders/' . $ref . '/fulfilment', ['fulfilment' => 'packed'])->status);
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW], '10.3.0.2');
    post('/account/verify', ['code' => $mail->lastCode()], '10.3.0.2');
    Auth::reset();
    assert_same('/admin/sign-in', $r->dispatch(new Request('GET', '/admin/orders'))->headers['Location'] ?? '');
    assert_same('new', Db::fromEnv()->one('SELECT fulfilment FROM orders')['fulfilment']);
    shop_done();
});

test('staff see the list, can filter by payment, packing and text, and text is not a query', function (): void {
    [$mail, , $cust] = admin_orders_env();
    $paid = make_order($cust, 'paid', 1, 'Ama Mensah');
    make_order($cust, 'pending', 2, 'Kwesi Boateng');
    make_order($cust, 'failed', 3, '100% Real_Name');
    staff_login($mail);
    $r = App::router();
    $all = $r->dispatch(new Request('GET', '/admin/orders'));
    assert_same(200, $all->status);
    assert_contains('3 orders', $all->body);
    assert_contains($paid, $all->body);
    assert_contains('ama@example.test', $all->body);
    $only = $r->dispatch(new Request('GET', '/admin/orders', ['status' => 'pending']));
    assert_contains('1 order match', $only->body);
    assert_true(!str_contains($only->body, $paid));
    assert_contains('1 order match', $r->dispatch(new Request('GET', '/admin/orders', ['q' => 'Boateng']))->body);
    assert_contains('1 order match', $r->dispatch(new Request('GET', '/admin/orders', ['q' => strtolower($paid)]))->body);
    assert_contains('1 order match', $r->dispatch(new Request('GET', '/admin/orders', ['q' => '100%']))->body, 'percent treated as a wildcard');
    assert_contains('0 orders match', $r->dispatch(new Request('GET', '/admin/orders', ['q' => '%zzz']))->body);
    assert_contains('0 orders match', $r->dispatch(new Request('GET', '/admin/orders', ['q' => "x' OR '1'='1"]))->body);
    assert_contains('3 orders', $r->dispatch(new Request('GET', '/admin/orders', ['status' => 'bogus', 'fulfilment' => 'bogus']))->body);
    assert_contains('1 order match', $r->dispatch(new Request('GET', '/admin/orders', ['fulfilment' => 'new', 'status' => 'paid']))->body);
    shop_done();
});

test('the order list pages after 20 and clamps a huge page number', function (): void {
    [$mail, , $cust] = admin_orders_env();
    for ($i = 0; $i < 25; $i++) {
        make_order($cust, 'paid', 1, 'Buyer ' . $i);
    }
    staff_login($mail);
    $r = App::router();
    $p1 = $r->dispatch(new Request('GET', '/admin/orders'));
    assert_contains('25 orders', $p1->body);
    assert_contains('page=2', $p1->body);
    assert_same(20, substr_count($p1->body, '<tr>') - 1);
    assert_same(5, substr_count($r->dispatch(new Request('GET', '/admin/orders', ['page' => '99']))->body, '<tr>') - 1);
    shop_done();
});

test('opening an order shows the customer and address and writes an audit entry; unknown orders are 404', function (): void {
    [$mail, $staffId, $cust] = admin_orders_env();
    $ref = make_order($cust);
    staff_login($mail);
    $page = App::router()->dispatch(new Request('GET', '/admin/orders/' . $ref));
    assert_same(200, $page->status);
    assert_contains('12 Sample Street', $page->body);
    assert_contains('ama@example.test', $page->body);
    assert_contains('Bleach', $page->body);
    $row = Db::fromEnv()->one("SELECT user_id, target, ip FROM audit_log WHERE action = 'order.view'");
    assert_same($staffId, (int) $row['user_id']);
    assert_same($ref, $row['target']);
    assert_same(404, App::router()->dispatch(new Request('GET', '/admin/orders/BB-NOPE'))->status);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM audit_log WHERE action = 'order.view'")['n'], 'a missing order was logged as viewed');
    shop_done();
});

test('staff can move a paid order along, it is audited, and other orders and values are refused', function (): void {
    [$mail, $staffId, $cust] = admin_orders_env();
    $paid = make_order($cust, 'paid');
    $pending = make_order($cust, 'pending');
    staff_login($mail);
    assert_same(302, post('/admin/orders/' . $paid . '/fulfilment', ['fulfilment' => 'packed'])->status);
    assert_same('packed', Db::fromEnv()->one('SELECT fulfilment FROM orders WHERE ref = ?', [$paid])['fulfilment']);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM audit_log WHERE action = 'order.fulfilment.packed' AND target = ? AND user_id = ?", [$paid, $staffId])['n']);
    post('/admin/orders/' . $paid . '/fulfilment', ['fulfilment' => 'hacked']);
    assert_same('packed', Db::fromEnv()->one('SELECT fulfilment FROM orders WHERE ref = ?', [$paid])['fulfilment']);
    post('/admin/orders/' . $pending . '/fulfilment', ['fulfilment' => 'delivered']);
    assert_same('new', Db::fromEnv()->one('SELECT fulfilment FROM orders WHERE ref = ?', [$pending])['fulfilment'], 'an unpaid order was moved');
    post('/admin/orders/' . $paid . '/fulfilment', ['fulfilment' => 'packed']);
    assert_same('packed', Db::fromEnv()->one('SELECT fulfilment FROM orders WHERE ref = ?', [$paid])['fulfilment'], 'setting the same value again failed');
    assert_same(419, App::router()->dispatch(new Request('POST', '/admin/orders/' . $paid . '/fulfilment', [], ['_csrf' => 'bad', 'fulfilment' => 'delivered']))->status);
    shop_done();
});

test('staff cannot change payment status, amounts or the order through the fulfilment form', function (): void {
    [$mail, , $cust] = admin_orders_env();
    $pending = make_order($cust, 'pending', 2);
    staff_login($mail);
    post('/admin/orders/' . $pending . '/fulfilment', ['fulfilment' => 'packed', 'status' => 'paid', 'total_pesewas' => '1', 'paid_at' => '1']);
    $o = Db::fromEnv()->one('SELECT status, total_pesewas, paid_at FROM orders');
    assert_same('pending', $o['status']);
    assert_same(2 * 4500 + 2000, (int) $o['total_pesewas']);
    assert_same(null, $o['paid_at']);
    shop_done();
});

test('the dashboard shows real figures and recent orders to staff', function (): void {
    [$mail, , $cust] = admin_orders_env();
    make_order($cust, 'paid', 2);
    make_order($cust, 'pending');
    $bad = make_order($cust, 'paid');
    Db::fromEnv()->run('UPDATE orders SET needs_review = 1 WHERE ref = ?', [$bad]);
    staff_login($mail);
    $page = App::router()->dispatch(new Request('GET', '/admin'));
    assert_same(200, $page->status);
    assert_contains('To pack', $page->body);
    assert_contains('Need review', $page->body);
    assert_contains('1 waiting for payment', $page->body);
    assert_contains('/admin/orders/BB-', $page->body);
    assert_true(!str_contains($page->body, 'Sample Customer'), 'sample rows shown to real staff');
    $f = (new Orders(Db::fromEnv()))->adminFigures();
    assert_same(2, $f['to_pack']);
    assert_same(1, $f['review']);
    assert_same(1, $f['unpaid']);
    assert_same((4500 * 2 + 2000) + (4500 + 2000), $f['week_pesewas'], 'paid total for the week');
    shop_done();
});

test('the preview staff person sees sample orders but cannot change them', function (): void {
    admin_orders_env();
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'staff', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    Auth::reset();
    $r = App::router();
    assert_same(200, $r->dispatch(new Request('GET', '/admin/orders'))->status);
    assert_same(200, $r->dispatch(new Request('GET', '/admin/orders/BB-10482'))->status);
    assert_same(404, $r->dispatch(new Request('GET', '/admin/orders/BB-99999'))->status);
    $before = (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM audit_log')['n'];
    assert_same(302, post('/admin/orders/BB-10482/fulfilment', ['fulfilment' => 'packed'])->status);
    assert_same($before, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM audit_log')['n']);
    shop_done();
});
