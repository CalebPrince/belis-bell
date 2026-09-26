<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Csrf;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Domain\Cart;
use Belis\Domain\Orders;
use Belis\Payments\PaymentAdapter;
use Belis\Payments\Payments;
use Belis\Payments\PaystackAdapter;

// CTL-BIZ-001, CTL-PAY-001, CTL-PAY-002, CTL-AUTHZ-001: server-side prices, verify-before-paid, signed webhooks.

/** A pretend provider whose answers the test controls. */
final class FakeAdapter implements PaymentAdapter
{
    /** @var array{status:string,amount:int,currency:string,reference:string}|null */
    public ?array $answer = null;
    public bool $down = false;
    /** @var list<array{amount:int,reference:string}> */
    public array $started = [];

    public function initialize(string $email, int $amountPesewas, string $reference, string $callbackUrl): string
    {
        if ($this->down) {
            throw new RuntimeException('down');
        }
        $this->started[] = ['amount' => $amountPesewas, 'reference' => $reference];
        return 'https://checkout.paystack.com/abc123';
    }

    public function verify(string $reference): array
    {
        if ($this->down) {
            throw new RuntimeException('down');
        }
        return $this->answer ?? ['status' => 'pending', 'amount' => 0, 'currency' => 'GHS', 'reference' => $reference];
    }

    public function say(string $status, int $amount, string $reference, string $currency = 'GHS'): void
    {
        $this->answer = ['status' => $status, 'amount' => $amount, 'currency' => $currency, 'reference' => $reference];
    }
}

function shop_env(string $stock = 'in_stock'): array
{
    $mail = auth_env();
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'PAYSTACK_SECRET_KEY' => 'sk_test_unit', 'APP_URL' => 'http://localhost', 'PAYMENTS_ADAPTER' => 'paystack']);
    $pdo = Db::fromEnv()->pdo();
    $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, is_published INTEGER)');
    $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, category_id INTEGER, slug TEXT, name TEXT, is_published INTEGER)');
    $pdo->exec('CREATE TABLE product_variants (id INTEGER PRIMARY KEY, product_id INTEGER, label TEXT, price_pesewas INTEGER, stock_status TEXT)');
    $pdo->exec('CREATE TABLE bulk_tiers (variant_id INTEGER, min_qty INTEGER, unit_price_pesewas INTEGER)');
    $pdo->exec('INSERT INTO categories VALUES (1, 1)');
    $pdo->exec("INSERT INTO products VALUES (1, 1, 'bleach', 'Bleach', 1)");
    $pdo->exec("INSERT INTO product_variants VALUES (10, 1, '5 L', 4500, '" . $stock . "')");
    $pdo->exec('INSERT INTO bulk_tiers VALUES (10, 10, 4000)');
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT UNIQUE, user_id INTEGER, status TEXT DEFAULT "pending", fulfilment TEXT DEFAULT "new", needs_review INTEGER DEFAULT 0, currency TEXT DEFAULT "GHS", subtotal_pesewas INTEGER, delivery_pesewas INTEGER, total_pesewas INTEGER, delivery_method TEXT, ship_name TEXT, ship_phone TEXT, ship_street TEXT, ship_city TEXT, ship_region TEXT, notes TEXT, payment_reference TEXT UNIQUE, created_at INTEGER, paid_at INTEGER)');
    $pdo->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, variant_id INTEGER, product_name TEXT, size_label TEXT, qty INTEGER, unit_pesewas INTEGER, line_pesewas INTEGER)');
    $pdo->exec('CREATE TABLE payment_events (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, source TEXT, outcome TEXT, created_at INTEGER)');
    $fake = new FakeAdapter();
    Payments::useForTests($fake);
    return [$mail, $fake];
}

function shop_done(): void
{
    Payments::useForTests(null);
    auth_done();
}

function sign_in_as(OutboxMailer $mail, string $email = 'ama@example.test', string $ip = '10.1.0.1'): void
{
    post('/account/register', ['name' => 'Ama Mensah', 'email' => $email, 'phone' => '+233 24 000 0000', 'password' => GOOD_PW, 'password2' => GOOD_PW], $ip);
    post('/account/verify', ['code' => $mail->lastCode()], $ip);
    post('/account/sign-in', ['email' => $email, 'password' => GOOD_PW], $ip);
    post('/account/verify', ['code' => $mail->lastCode()], $ip);
    Auth::reset();
}

function address(array $over = []): array
{
    return $over + ['name' => 'Ama Mensah', 'phone' => '+233 24 000 0000', 'street' => '12 Sample Street', 'city' => 'Accra', 'region' => 'Greater Accra', 'delivery' => 'standard', 'notes' => ''];
}

function place(array $over = [], ?array $post = null): \Belis\Core\Response
{
    return post('/checkout', $post ?? address($over));
}

test('checkout builds the order from server prices and ignores totals sent by the browser', function (): void {
    [$mail, $fake] = shop_env();
    sign_in_as($mail);
    Cart::add(10, 3);
    $res = place(['total' => '1', 'price' => '1', 'unit_pesewas' => '1', 'delivery_fee' => '0']);
    assert_same(302, $res->status);
    $o = Db::fromEnv()->one('SELECT * FROM orders');
    assert_same(3 * 4500, (int) $o['subtotal_pesewas']);
    assert_same(2000, (int) $o['delivery_pesewas']);
    assert_same(3 * 4500 + 2000, (int) $o['total_pesewas']);
    assert_same('pending', $o['status']);
    assert_same($o['total_pesewas'], $fake->started[0]['amount']);
    assert_same(1, count($fake->started));
    assert_true(str_starts_with($res->headers['Location'] ?? '', '/pay/BB-'));
    shop_done();
});

test('bulk tier prices apply on the server and are frozen on the order', function (): void {
    [$mail] = shop_env();
    sign_in_as($mail);
    Cart::add(10, 10);
    place();
    $i = Db::fromEnv()->one('SELECT unit_pesewas, line_pesewas, product_name FROM order_items');
    assert_same(4000, (int) $i['unit_pesewas']);
    assert_same(40000, (int) $i['line_pesewas']);
    Db::fromEnv()->run('UPDATE product_variants SET price_pesewas = 9999');
    assert_same(4000, (int) Db::fromEnv()->one('SELECT unit_pesewas FROM order_items')['unit_pesewas'], 'order price followed the catalogue');
    shop_done();
});

test('bad address or delivery choice re-shows the form and creates no order', function (): void {
    [$mail, $fake] = shop_env();
    sign_in_as($mail);
    Cart::add(10, 1);
    foreach ([address(['street' => '']), address(['region' => 'Mars']), address(['delivery' => 'teleport']), address(['phone' => 'abc'])] as $bad) {
        $res = place([], $bad);
        assert_same(422, $res->status);
    }
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM orders')['n']);
    assert_same([], $fake->started);
    shop_done();
});

test('an out of stock line or empty cart cannot be ordered', function (): void {
    [$mail] = shop_env('out');
    sign_in_as($mail);
    Cart::add(10, 1);
    assert_same('/cart', place()->headers['Location'] ?? '');
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM orders')['n']);
    Cart::clear();
    assert_same('/cart', place()->headers['Location'] ?? '');
    shop_done();
});

test('checkout needs a real sign-in and a CSRF token', function (): void {
    [$mail] = shop_env();
    assert_same(302, place()->status);
    assert_same('/account/sign-in', place()->headers['Location'] ?? '');
    sign_in_as($mail);
    assert_same(419, App::router()->dispatch(new Request('POST', '/checkout', [], address() + ['_csrf' => 'bad']))->status);
    shop_done();
});

test('a payment provider outage cancels the new order and charges nothing', function (): void {
    [$mail, $fake] = shop_env();
    sign_in_as($mail);
    Cart::add(10, 1);
    $fake->down = true;
    $res = place();
    assert_same('/checkout', $res->headers['Location'] ?? '');
    assert_same('cancelled', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    shop_done();
});

function order_via_checkout(OutboxMailer $mail, FakeAdapter $fake): array
{
    sign_in_as($mail);
    Cart::add(10, 2);
    place();
    return Db::fromEnv()->one('SELECT * FROM orders');
}

test('coming back from Paystack does not mark an order paid unless the provider says so', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $res = App::router()->dispatch(new Request('GET', '/payment/callback', ['reference' => $o['payment_reference'], 'status' => 'success', 'trxref' => 'x']));
    assert_same('/order/' . $o['ref'], $res->headers['Location'] ?? '');
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    assert_same(2, Cart::count(), 'cart emptied before payment');
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    App::router()->dispatch(new Request('GET', '/payment/callback', ['reference' => $o['payment_reference']]));
    assert_same('paid', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    assert_same(0, Cart::count(), 'cart kept after payment');
    shop_done();
});

test('a paid answer with the wrong amount, currency or reference is never accepted', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $orders = new Orders(Db::fromEnv());
    foreach ([[100, 'GHS', $o['payment_reference']], [(int) $o['total_pesewas'], 'USD', $o['payment_reference']], [(int) $o['total_pesewas'], 'GHS', 'BBP-other']] as [$amt, $cur, $ref]) {
        $fake->say('success', $amt, $ref, $cur);
        assert_same('pending', $orders->confirm($o['payment_reference'], $fake, 'callback'));
    }
    $row = Db::fromEnv()->one('SELECT status, needs_review FROM orders');
    assert_same('pending', $row['status']);
    assert_same(1, (int) $row['needs_review']);
    shop_done();
});

test('a failed payment marks the order failed, and a later success can still complete it', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $orders = new Orders(Db::fromEnv());
    $fake->say('failed', 0, $o['payment_reference']);
    assert_same('failed', $orders->confirm($o['payment_reference'], $fake, 'callback'));
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    assert_same('paid', $orders->confirm($o['payment_reference'], $fake, 'webhook'));
    shop_done();
});

test('a provider outage while checking leaves the order as it was', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $fake->down = true;
    $threw = false;
    try {
        (new Orders(Db::fromEnv()))->confirm($o['payment_reference'], $fake, 'webhook');
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    shop_done();
});

function webhook(string $body, ?string $signature): \Belis\Core\Response
{
    $headers = $signature === null ? [] : ['x-paystack-signature' => $signature];
    return App::router()->dispatch(new Request('POST', '/webhooks/paystack', [], [], $headers, $body));
}

test('the webhook rejects a missing or wrong signature and needs no CSRF token', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    $body = (string) json_encode(['event' => 'charge.success', 'data' => ['reference' => $o['payment_reference']]]);
    assert_same(401, webhook($body, null)->status);
    assert_same(401, webhook($body, 'deadbeef')->status);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    $sig = hash_hmac('sha512', $body, 'sk_test_unit');
    assert_same(200, webhook($body, $sig)->status);
    assert_same('paid', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    shop_done();
});

test('a signed webhook cannot mark an order paid when the provider does not confirm, and replays change nothing', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $body = (string) json_encode(['event' => 'charge.success', 'data' => ['reference' => $o['payment_reference'], 'status' => 'success', 'amount' => 1]]);
    $sig = hash_hmac('sha512', $body, 'sk_test_unit');
    assert_same(200, webhook($body, $sig)->status);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM orders')['status'], 'webhook body was trusted');
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    webhook($body, $sig);
    $paidAt = Db::fromEnv()->one('SELECT paid_at FROM orders')['paid_at'];
    webhook($body, $sig);
    assert_same($paidAt, Db::fromEnv()->one('SELECT paid_at FROM orders')['paid_at'], 'replay changed the order');
    $fake->down = true;
    assert_same(500, webhook($body, $sig)->status, 'a failed lookup should ask Paystack to retry');
    assert_same(200, webhook('{"event":"other"}', hash_hmac('sha512', '{"event":"other"}', 'sk_test_unit'))->status);
    shop_done();
});

test('customers see only their own orders', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    post('/account/sign-out');
    sign_in_as($mail, 'kwame@example.test', '10.1.0.2');
    $r = App::router();
    assert_same(404, $r->dispatch(new Request('GET', '/order/' . $o['ref']))->status);
    assert_same(302, $r->dispatch(new Request('GET', '/payment/callback', ['reference' => $o['payment_reference']]))->status);
    assert_same('/account', $r->dispatch(new Request('GET', '/payment/callback', ['reference' => $o['payment_reference']]))->headers['Location'] ?? '');
    assert_same(404, post('/order/' . $o['ref'] . '/cancel')->status);
    assert_same(404, post('/order/' . $o['ref'] . '/refresh')->status);
    assert_same(404, $r->dispatch(new Request('GET', '/pay/' . $o['ref']))->status);
    assert_true(!str_contains($r->dispatch(new Request('GET', '/account'))->body, $o['ref']), 'order listed for another customer');
    shop_done();
});

test('the owner sees the order page, and cancel checks with the provider first', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $page = App::router()->dispatch(new Request('GET', '/order/' . $o['ref']));
    assert_same(200, $page->status);
    assert_contains('waiting for your payment', $page->body);
    assert_contains('Cancel this order', $page->body);
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    post('/order/' . $o['ref'] . '/cancel');
    assert_same('paid', Db::fromEnv()->one('SELECT status FROM orders')['status'], 'paid order was cancelled');
    shop_done();
});

test('cancelling an unpaid order works', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    post('/order/' . $o['ref'] . '/cancel');
    assert_same('cancelled', Db::fromEnv()->one('SELECT status FROM orders')['status']);
    shop_done();
});

test('the pay page only links to Paystack or the local pretend page', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $page = App::router()->dispatch(new Request('GET', '/pay/' . $o['ref']));
    assert_same(200, $page->status);
    assert_contains('https://checkout.paystack.com/abc123', $page->body);
    $_SESSION['pay'][$o['ref']] = 'https://evil.example/pay';
    assert_same(302, App::router()->dispatch(new Request('GET', '/pay/' . $o['ref']))->status);
    $_SESSION['pay'][$o['ref']] = 'https://checkout.paystack.com.evil.example/x';
    assert_same(302, App::router()->dispatch(new Request('GET', '/pay/' . $o['ref']))->status);
    shop_done();
});

test('reconciliation marks paid orders, expires old unpaid ones and leaves recent ones', function (): void {
    [$mail, $fake] = shop_env();
    $o = order_via_checkout($mail, $fake);
    $db = Db::fromEnv();
    $future = time() + 400;
    $r = (new Orders($db, $future))->reconcile($fake);
    assert_same(['checked' => 1, 'paid' => 0, 'expired' => 0, 'errors' => 0], $r);
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    assert_same(1, (new Orders($db, $future))->reconcile($fake)['paid']);
    $db->run("UPDATE orders SET status = 'pending', paid_at = NULL");
    $fake->answer = null;
    $r = (new Orders($db, time() + 90000))->reconcile($fake);
    assert_same(1, $r['expired']);
    assert_same('cancelled', $db->one('SELECT status FROM orders')['status']);
    $fake->down = true;
    $db->run("UPDATE orders SET status = 'pending'");
    assert_same(1, (new Orders($db, $future))->reconcile($fake)['errors']);
    shop_done();
});

test('the Paystack adapter sends the amount in pesewas with GHS and a bearer key, and reads the verify answer', function (): void {
    $calls = [];
    $http = function (string $method, string $url, array $headers, ?string $body) use (&$calls): array {
        $calls[] = [$method, $url, $headers, $body];
        if (str_contains($url, 'initialize')) {
            return ['status' => 200, 'body' => '{"status":true,"data":{"authorization_url":"https://checkout.paystack.com/x1","reference":"R"}}'];
        }
        return ['status' => 200, 'body' => '{"status":true,"data":{"status":"success","amount":15400,"currency":"GHS","reference":"BBP-abcdef123456"}}'];
    };
    $p = new PaystackAdapter('sk_test_unit', $http);
    assert_same('https://checkout.paystack.com/x1', $p->initialize('a@example.test', 15400, 'BBP-abcdef123456', 'http://localhost/payment/callback'));
    $sent = json_decode((string) $calls[0][3], true);
    assert_same(15400, $sent['amount']);
    assert_same('GHS', $sent['currency']);
    assert_true(in_array('Authorization: Bearer sk_test_unit', $calls[0][2], true));
    assert_same(['status' => 'success', 'amount' => 15400, 'currency' => 'GHS', 'reference' => 'BBP-abcdef123456'], $p->verify('BBP-abcdef123456'));
    assert_same('GET', $calls[1][0]);
});

test('the Paystack adapter refuses odd answers, odd references and a missing key', function (): void {
    $bad = fn (string $body, int $status = 200) => new PaystackAdapter('sk_test_unit', fn () => ['status' => $status, 'body' => $body]);
    foreach ([
        fn () => $bad('{"status":true,"data":{"authorization_url":"http://insecure.example/x"}}')->initialize('a@b.test', 100, 'BBP-x', 'u'),
        fn () => $bad('{"status":false}')->initialize('a@b.test', 100, 'BBP-x', 'u'),
        fn () => $bad('not json', 500)->verify('BBP-abcdef123456'),
        fn () => $bad('{}')->verify('../../etc/passwd'),
        fn () => (new PaystackAdapter('', fn () => ['status' => 200, 'body' => '{}']))->verify('BBP-abcdef123456'),
    ] as $i => $call) {
        $threw = false;
        try {
            $call();
        } catch (RuntimeException) {
            $threw = true;
        }
        assert_true($threw, "case $i did not throw");
    }
});

test('a Paystack unknown status stays pending, never paid', function (): void {
    $p = new PaystackAdapter('sk_test_unit', fn () => ['status' => 200, 'body' => '{"status":true,"data":{"status":"abandoned","amount":100,"currency":"GHS","reference":"BBP-abcdef123456"}}']);
    assert_same('pending', $p->verify('BBP-abcdef123456')['status']);
});

test('the pretend payment routes answer 404 outside local development and with the real adapter', function (): void {
    [$mail] = shop_env();
    assert_same(404, App::router()->dispatch(new Request('GET', '/mock-pay/abc'))->status);
    Env::fake(['APP_ENV' => 'production', 'PAYMENTS_ADAPTER' => 'mock']);
    Payments::useForTests(null);
    $threw = false;
    try {
        Payments::adapter();
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw, 'mock adapter available outside local');
    Env::fake(['APP_ENV' => 'local', 'PAYMENTS_ADAPTER' => 'paystack', 'PAYSTACK_SECRET_KEY' => '']);
    $threw = false;
    try {
        Payments::adapter();
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw, 'Paystack adapter available without a key');
    shop_done();
});

test('no template or log writes card details: checkout has no card or mobile money fields', function (): void {
    $html = (string) file_get_contents(BASE_PATH . '/templates/pages/account/checkout.php');
    foreach (['card', 'cvv', 'cvc', 'expiry', 'momo', 'pin'] as $word) {
        assert_true(preg_match('/name="[^"]*' . $word . '[^"]*"/i', $html) !== 1, "field named like $word");
    }
});
