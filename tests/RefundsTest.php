<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Domain\Refunds;
use Belis\Payments\Payments;
use Belis\Payments\PaystackAdapter;
use Belis\Support\Settings;

// CTL-PAY-003, THR-024: owner-only refunds, fresh code each time, checked amounts, read back from the provider.

/** @return array{0:OutboxMailer,1:FakeAdapter,2:string,3:int} mailer, provider, order ref, order total */
function refunds_env(): array
{
    $mail = gaps_env();
    $fake = new FakeAdapter();
    Payments::useForTests($fake);
    $ref = make_order(add_customer($mail), 'paid', 4); // 4 x 45.00 + 20.00 delivery = 200.00
    return [$mail, $fake, $ref, 20000];
}

function confirm_for(OutboxMailer $mail, string $ref): void
{
    post('/admin/confirm/code', ['next' => '/admin/orders/' . $ref], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/orders/' . $ref, 'code' => $mail->lastCode()], '10.4.0.1');
}

function refund_rows(): array
{
    return Db::fromEnv()->all('SELECT id, amount_pesewas FROM refunds ORDER BY id');
}

test('only the owner can refund: staff of every role, visitors and customers are refused', function (): void {
    [$mail, , $ref] = refunds_env();
    $a = new Belis\Domain\Accounts(Db::fromEnv(), $mail);
    $a->createVerified('fulfil@example.test', 'F', 'n/a', GOOD_PW, 'staff', 'fulfilment');
    assert_same(302, post('/admin/orders/' . $ref . '/refund', ['amount' => '10', 'reason' => 'x'])->status);
    foreach (['staff', 'fulfil'] as $who) {
        $_SESSION = [];
        Belis\Core\Auth::reset();
        login_as($mail, $who);
        assert_same(403, post('/admin/orders/' . $ref . '/refund', ['amount' => '10', 'reason' => 'x'])->status, $who);
    }
    assert_same([], refund_rows());
    shop_done();
});

test('a refund needs a fresh emailed code, then goes to the provider for exactly that amount and is audited', function (): void {
    [$mail, $fake, $ref] = refunds_env();
    login_as($mail, 'owner');
    $res = post('/admin/orders/' . $ref . '/refund', ['amount' => '25.50', 'reason' => 'Damaged bottle']);
    assert_contains('/admin/confirm', $res->headers['Location'] ?? '');
    assert_same([], refund_rows(), 'refunded without the code');
    confirm_for($mail, $ref);
    assert_same(302, post('/admin/orders/' . $ref . '/refund', ['amount' => '25.50', 'reason' => 'Damaged bottle'])->status);
    assert_same([['id' => 1, 'amount_pesewas' => 2550]], array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'amount_pesewas' => (int) $r['amount_pesewas']], refund_rows()));
    assert_same([['amount' => 2550, 'note' => 'Damaged bottle']], $fake->refundsMade);
    assert_same('processed', Db::fromEnv()->one('SELECT status FROM refund_events ORDER BY id DESC LIMIT 1')['status']);
    assert_same(1, count_rows("action = 'refund.create' AND target = '" . $ref . "'"));
    $emails = array_column($mail->sent, 'subject');
    assert_true(in_array('A Belis Bell refund was made', $emails, true), 'owner not emailed');
    assert_true(in_array('Your Belis Bell refund', $emails, true), 'customer not emailed');
    foreach ($mail->sent as $m) {
        assert_true(!str_contains($m['body'], 'BBP-'), 'a payment reference was emailed');
    }
    shop_done();
});

test('the amount is checked on the server: zero, negative, text, too much and repeat over-refunds are refused', function (): void {
    [$mail, $fake, $ref, $total] = refunds_env();
    login_as($mail, 'owner');
    confirm_for($mail, $ref);
    foreach (['0', '-5', 'abc', '', '1e3', '200.01', '999999'] as $bad) {
        post('/admin/orders/' . $ref . '/refund', ['amount' => $bad, 'reason' => 'x']);
    }
    assert_same([], refund_rows());
    post('/admin/orders/' . $ref . '/refund', ['amount' => '150.00', 'reason' => 'first']);
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '50.01', 'reason' => 'too much now']);
    assert_same(1, count(refund_rows()), 'refunded more than was paid');
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '50.00', 'reason' => 'the rest']);
    assert_same(2, count(refund_rows()));
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '0.01', 'reason' => 'one more']);
    assert_same(2, count(refund_rows()), 'a fully refunded order was refunded again');
    assert_same($total, (int) Db::fromEnv()->one('SELECT SUM(amount_pesewas) AS n FROM refunds')['n']);
    shop_done();
});

test('a reason is required, and only paid orders can be refunded', function (): void {
    [$mail, , $ref] = refunds_env();
    $uid = add_customer($mail, 'kofi@example.test');
    $pending = make_order($uid, 'pending');
    $failed = make_order($uid, 'failed');
    login_as($mail, 'owner');
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '10', 'reason' => '']);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '10', 'reason' => str_repeat('x', 201)]);
    post('/admin/orders/' . $pending . '/refund', ['amount' => '10', 'reason' => 'x']);
    post('/admin/orders/' . $failed . '/refund', ['amount' => '10', 'reason' => 'x']);
    post('/admin/orders/BB-NOPE/refund', ['amount' => '10', 'reason' => 'x']);
    assert_same([], refund_rows());
    shop_done();
});

test('the daily limit: over it, each refund spends its confirmation and needs a new code', function (): void {
    [$mail, $fake] = refunds_env();
    $uid = (int) Db::fromEnv()->one("SELECT id FROM users WHERE email = 'ama@example.test'")['id'];
    $refs = [];
    for ($i = 0; $i < 4; $i++) {
        $refs[] = make_order($uid, 'paid', 100); // each 4,520.00
    }
    login_as($mail, 'owner');
    confirm_for($mail, $refs[0]);
    post('/admin/orders/' . $refs[0] . '/refund', ['amount' => '2000.00', 'reason' => 'a']);
    assert_same(1, count(refund_rows()), 'exactly at the limit is still allowed');
    post('/admin/orders/' . $refs[1] . '/refund', ['amount' => '100.00', 'reason' => 'b']);
    assert_same(2, count(refund_rows()), 'the refund that crosses the limit is allowed');
    $res = post('/admin/orders/' . $refs[2] . '/refund', ['amount' => '100.00', 'reason' => 'c']);
    assert_contains('/admin/confirm', $res->headers['Location'] ?? '', 'over the limit and no new code');
    assert_same(2, count(refund_rows()));
    confirm_for($mail, $refs[2]);
    assert_same(302, post('/admin/orders/' . $refs[2] . '/refund', ['amount' => '100.00', 'reason' => 'c'])->status);
    assert_same(3, count(refund_rows()));
    post('/admin/orders/' . $refs[3] . '/refund', ['amount' => '100.00', 'reason' => 'd']);
    assert_same(3, count(refund_rows()), 'the same code was used for two over-limit refunds');
    shop_done();
});

test('the daily limit is an owner setting with a default of GHS 2,000.00', function (): void {
    gaps_env();
    assert_same(200000, Refunds::dailyLimit());
    Settings::save(Db::fromEnv(), 'REFUND_DAILY_LIMIT', '500.50', 1);
    assert_same(50050, Refunds::dailyLimit());
    assert_true(Settings::validate('REFUND_DAILY_LIMIT', 'lots') !== null);
    assert_true(Settings::validate('REFUND_DAILY_LIMIT', '-1') !== null);
    assert_same(null, Settings::validate('REFUND_DAILY_LIMIT', '0'));
    shop_done();
});

test('a refund that stays pending is recorded as pending, and the provider lookup completes it once', function (): void {
    [$mail, $fake, $ref] = refunds_env();
    $fake->refundStatus = 'pending';
    login_as($mail, 'owner');
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '30.00', 'reason' => 'x']);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM refund_events ORDER BY id DESC LIMIT 1')['status']);
    $pay = Db::fromEnv()->one('SELECT payment_reference FROM orders')['payment_reference'];
    $refunds = new Refunds(Db::fromEnv());
    $refunds->sync($pay, $fake);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM refund_events ORDER BY id DESC LIMIT 1')['status'], 'still pending at the provider');
    $fake->refundList = [['amount' => 3000, 'status' => 'processed']];
    $n = count($mail->sent);
    $refunds->sync($pay, $fake);
    $refunds->sync($pay, $fake);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM refund_events WHERE status = 'processed'")['n'], 'a replay added another event');
    assert_same($n + 1, count($mail->sent), 'the customer was emailed more than once');
    shop_done();
});

test('a refund webhook only triggers a provider lookup and cannot mark anything processed itself', function (): void {
    [$mail, $fake, $ref] = refunds_env();
    $fake->refundStatus = 'pending';
    login_as($mail, 'owner');
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '30.00', 'reason' => 'x']);
    $pay = Db::fromEnv()->one('SELECT payment_reference FROM orders')['payment_reference'];
    $fake->refundList = [['amount' => 3000, 'status' => 'pending']];
    $body = (string) json_encode(['event' => 'refund.processed', 'data' => ['transaction_reference' => $pay, 'status' => 'processed', 'amount' => 3000]]);
    $sig = hash_hmac('sha512', $body, 'sk_test_unit');
    Belis\Core\Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A, 'PAYSTACK_SECRET_KEY' => 'sk_test_unit', 'PAYMENTS_ADAPTER' => 'paystack']);
    assert_same(401, App::router()->dispatch(new Request('POST', '/webhooks/paystack', [], [], ['x-paystack-signature' => 'bad'], $body))->status);
    assert_same(200, App::router()->dispatch(new Request('POST', '/webhooks/paystack', [], [], ['x-paystack-signature' => $sig], $body))->status);
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM refund_events ORDER BY id DESC LIMIT 1')['status'], 'the webhook body was trusted');
    $fake->refundList = [['amount' => 3000, 'status' => 'processed']];
    App::router()->dispatch(new Request('POST', '/webhooks/paystack', [], [], ['x-paystack-signature' => $sig], $body));
    assert_same('processed', Db::fromEnv()->one('SELECT status FROM refund_events ORDER BY id DESC LIMIT 1')['status']);
    $fake->down = true;
    assert_same(500, App::router()->dispatch(new Request('POST', '/webhooks/paystack', [], [], ['x-paystack-signature' => $sig], $body))->status);
    shop_done();
});

test('if the provider cannot be reached the refund stays pending, is not lost, and the owner is told', function (): void {
    [$mail, $fake, $ref] = refunds_env();
    login_as($mail, 'owner');
    confirm_for($mail, $ref);
    $fake->down = true;
    post('/admin/orders/' . $ref . '/refund', ['amount' => '40.00', 'reason' => 'x']);
    assert_same(1, count(refund_rows()));
    assert_same('pending', Db::fromEnv()->one('SELECT status FROM refund_events ORDER BY id DESC LIMIT 1')['status']);
    assert_contains('could not be reached', end($mail->sent)['body']);
    // The amount stays reserved so it cannot be refunded twice while unsure.
    assert_same(4000, (new Refunds(Db::fromEnv()))->refundedTotal(1));
    // Later the provider says it never heard of it: after 2 hours it is marked failed and the amount is free again.
    $fake->down = false;
    $fake->refundList = [];
    (new Refunds(Db::fromEnv(), time() + 100))->reconcile($fake);
    assert_same(4000, (new Refunds(Db::fromEnv()))->refundedTotal(1), 'released too early');
    (new Refunds(Db::fromEnv(), time() + 8000))->reconcile($fake);
    assert_same(0, (new Refunds(Db::fromEnv()))->refundedTotal(1));
    shop_done();
});

test('the admin order page shows refunds and the form only to the owner, and the customer sees the refunded amount', function (): void {
    [$mail, , $ref] = refunds_env();
    login_as($mail, 'staff');
    $_SESSION = [];
    login_as($mail, 'owner');
    $page = App::router()->dispatch(new Request('GET', '/admin/orders/' . $ref));
    assert_contains('Refunds', $page->body);
    assert_contains('/refund', $page->body);
    confirm_for($mail, $ref);
    post('/admin/orders/' . $ref . '/refund', ['amount' => '12.34', 'reason' => 'Late delivery']);
    $page = App::router()->dispatch(new Request('GET', '/admin/orders/' . $ref));
    assert_contains('Late delivery', $page->body);
    assert_contains('12.34', $page->body);
    $_SESSION = [];
    customer_login($mail);
    $mine = App::router()->dispatch(new Request('GET', '/order/' . $ref));
    assert_contains('Refunded', $mine->body);
    assert_contains('12.34', $mine->body);
    shop_done();
});

test('the refund tables refuse changes, and the code never updates or deletes refund rows', function (): void {
    $mig = (string) file_get_contents(BASE_PATH . '/database/migrations/013_refunds.sql');
    foreach (['BEFORE UPDATE ON refunds', 'BEFORE DELETE ON refunds', 'BEFORE UPDATE ON refund_events', 'BEFORE DELETE ON refund_events'] as $t) {
        assert_contains($t, $mig);
    }
    $src = (string) file_get_contents(BASE_PATH . '/src/Domain/Refunds.php');
    assert_true(preg_match('/(UPDATE|DELETE FROM) refunds?(_events)?\b/i', $src) !== 1, 'code changes refund rows');
    foreach (['card', 'cvv', 'pan', 'msisdn'] as $w) {
        assert_true(preg_match('/CREATE TABLE refunds[^;]*\b' . $w . '\b/i', $mig) !== 1, "column like $w");
    }
});

test('the Paystack adapter refund calls send the amount in pesewas and read the answers safely', function (): void {
    $calls = [];
    $http = function (string $method, string $url, array $headers, ?string $body) use (&$calls): array {
        $calls[] = [$method, $url, $body];
        if ($method === 'POST') {
            return ['status' => 200, 'body' => '{"status":true,"data":{"status":"pending"}}'];
        }
        return ['status' => 200, 'body' => '{"status":true,"data":[{"amount":3000,"status":"processed"},{"amount":500,"status":"needs-attention"},{"status":"processed"}]}'];
    };
    $p = new PaystackAdapter('sk_test_unit', $http);
    assert_same(['status' => 'pending'], $p->refund('BBP-abcdef123456', 3000, 'Damaged'));
    $sent = json_decode((string) $calls[0][2], true);
    assert_same([3000, 'GHS', 'BBP-abcdef123456'], [$sent['amount'], $sent['currency'], $sent['transaction']]);
    assert_same([['amount' => 3000, 'status' => 'processed'], ['amount' => 500, 'status' => 'pending']], $p->refunds('BBP-abcdef123456'));
    foreach ([fn () => $p->refund('../x', 100, 'n'), fn () => $p->refund('BBP-abcdef123456', 0, 'n'), fn () => $p->refunds('bad ref!')] as $i => $bad) {
        $threw = false;
        try {
            $bad();
        } catch (RuntimeException) {
            $threw = true;
        }
        assert_true($threw, "case $i");
    }
    $refused = new PaystackAdapter('sk_test_unit', fn () => ['status' => 400, 'body' => '{"status":false}']);
    $threw = false;
    try {
        $refused->refund('BBP-abcdef123456', 100, 'n');
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw);
});

test('the preview owner cannot refund', function (): void {
    [$mail, , $ref] = refunds_env();
    Belis\Core\Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'owner', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    Belis\Core\Auth::reset();
    assert_same(302, post('/admin/orders/' . $ref . '/refund', ['amount' => '10', 'reason' => 'x'])->status);
    assert_same([], refund_rows());
    shop_done();
});
