<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Domain\Accounts;
use Belis\Domain\Orders;
use Belis\Support\Mailer;

// Password recovery, sign-in audit, staff management, account self-service, order emails.

function gaps_env(): OutboxMailer
{
    [$mail] = products_env();
    $pdo = Db::fromEnv()->pdo();
    $pdo->exec('CREATE TABLE addresses (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, label TEXT, name TEXT, phone TEXT, street TEXT, city TEXT, region TEXT, is_default INTEGER DEFAULT 0, created_at INTEGER)');
    return $mail;
}

function add_customer(OutboxMailer $mail, string $email = 'ama@example.test'): int
{
    return (new Accounts(Db::fromEnv(), $mail))->createVerified($email, 'Ama Mensah', '+233 24 000 0000', GOOD_PW, 'customer');
}

function customer_login(OutboxMailer $mail, string $email = 'ama@example.test', string $pw = GOOD_PW, string $ip = '10.5.0.1'): void
{
    post('/account/sign-in', ['email' => $email, 'password' => $pw], $ip);
    post('/account/verify', ['code' => $mail->lastCode()], $ip);
    Auth::reset();
}

const NEW_PW = 'brand new phrase 2026';

/* ---------- password recovery ---------- */

test('forgot password sends a code for a real account and looks the same for a missing one', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    $n = count($mail->sent);
    $a = post('/account/forgot', ['email' => 'ama@example.test'], '10.5.1.1');
    assert_same('/account/reset', $a->headers['Location'] ?? '');
    assert_same($n + 1, count($mail->sent));
    $b = post('/account/forgot', ['email' => 'nobody@example.test'], '10.5.1.2');
    assert_same('/account/reset', $b->headers['Location'] ?? '');
    assert_same($n + 1, count($mail->sent), 'a code was sent to a missing account');
    shop_done();
});

test('customers and staff cannot use each other\'s recovery page', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    $n = count($mail->sent);
    post('/account/forgot', ['email' => 'staff@example.test'], '10.5.1.3');
    post('/admin/forgot', ['email' => 'ama@example.test'], '10.5.1.4');
    assert_same($n, count($mail->sent));
    post('/admin/forgot', ['email' => 'staff@example.test'], '10.5.1.5');
    assert_same($n + 1, count($mail->sent));
    shop_done();
});

test('a reset with the right code changes the password once, and the old one stops working', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    post('/account/forgot', ['email' => 'ama@example.test'], '10.5.2.1');
    $code = $mail->lastCode();
    $res = post('/account/reset', ['code' => $code, 'password' => NEW_PW, 'password2' => NEW_PW], '10.5.2.1');
    assert_same('/account/sign-in', $res->headers['Location'] ?? '');
    assert_contains('password was just changed', end($mail->sent)['body']);
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.5.2.2');
    assert_same(null, Auth::customer(), 'the old password still works');
    customer_login($mail, 'ama@example.test', NEW_PW, '10.5.2.3');
    assert_true(Auth::customer() !== null, 'the new password does not work');
    post('/account/sign-out');
    post('/account/forgot', ['email' => 'ama@example.test'], '10.5.2.4');
    assert_same(422, post('/account/reset', ['code' => $code, 'password' => 'yet another phrase 1', 'password2' => 'yet another phrase 1'], '10.5.2.4')->status, 'an old code worked');
    shop_done();
});

test('a reset refuses wrong codes, weak or mismatched passwords, and a weak password does not spend the code', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    post('/account/forgot', ['email' => 'ama@example.test'], '10.5.3.1');
    $code = $mail->lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';
    assert_same(422, post('/account/reset', ['code' => $wrong, 'password' => NEW_PW, 'password2' => NEW_PW], '10.5.3.1')->status);
    assert_same(422, post('/account/reset', ['code' => $code, 'password' => 'short', 'password2' => 'short'], '10.5.3.1')->status);
    assert_same(422, post('/account/reset', ['code' => $code, 'password' => 'password123', 'password2' => 'password123'], '10.5.3.1')->status);
    assert_same(422, post('/account/reset', ['code' => $code, 'password' => NEW_PW, 'password2' => 'different one'], '10.5.3.1')->status);
    assert_same(302, post('/account/reset', ['code' => $code, 'password' => NEW_PW, 'password2' => NEW_PW], '10.5.3.1')->status, 'the code was spent by a failed attempt');
    shop_done();
});

test('a reset code locks after five wrong tries', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    post('/account/forgot', ['email' => 'ama@example.test'], '10.5.4.1');
    $code = $mail->lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';
    for ($i = 0; $i < 5; $i++) {
        post('/account/reset', ['code' => $wrong, 'password' => NEW_PW, 'password2' => NEW_PW], '10.5.4.1');
    }
    assert_same(422, post('/account/reset', ['code' => $code, 'password' => NEW_PW, 'password2' => NEW_PW], '10.5.4.1')->status);
    shop_done();
});

test('the reset page needs a started reset, and a reset code cannot be used to sign in', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    assert_same(302, App::router()->dispatch(new Request('GET', '/account/reset'))->status);
    assert_same(302, App::router()->dispatch(new Request('GET', '/admin/reset'))->status);
    post('/account/forgot', ['email' => 'ama@example.test'], '10.5.5.1');
    assert_same(200, App::router()->dispatch(new Request('GET', '/account/reset'))->status);
    $res = post('/account/verify', ['code' => $mail->lastCode()], '10.5.5.1');
    assert_same('/account/reset', $res->headers['Location'] ?? '');
    assert_same(null, Auth::customer());
    shop_done();
});

test('a reset ends sessions that started before it', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    customer_login($mail);
    assert_true(Auth::customer() !== null);
    $_SESSION['auth']['started'] = time() - 30;
    $saved = $_SESSION['auth'];
    $_SESSION['pending'] = null;
    $uid = (int) Db::fromEnv()->one("SELECT id FROM users WHERE email = 'ama@example.test'")['id'];
    (new Accounts(Db::fromEnv(), $mail))->issueCode($uid, 'reset');
    (new Accounts(Db::fromEnv(), $mail))->resetPassword($uid, $mail->lastCode(), NEW_PW, NEW_PW, '10.5.6.1');
    Auth::reset();
    $_SESSION['auth'] = $saved;
    assert_same(null, Auth::customer(), 'an older session survived a password reset');
    shop_done();
});

test('forgot-password is rate limited per address', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    $n = count($mail->sent);
    for ($i = 0; $i < 6; $i++) {
        post('/account/forgot', ['email' => 'ama@example.test'], '10.5.7.' . ($i + 1));
    }
    assert_same($n + 3, count($mail->sent));
    shop_done();
});

test('a reset also confirms an unconfirmed email address', function (): void {
    $mail = gaps_env();
    $uid = add_customer($mail);
    Db::fromEnv()->run('UPDATE users SET email_verified_at = NULL');
    post('/account/forgot', ['email' => 'ama@example.test'], '10.5.8.1');
    post('/account/reset', ['code' => $mail->lastCode(), 'password' => NEW_PW, 'password2' => NEW_PW], '10.5.8.1');
    assert_true(Db::fromEnv()->one('SELECT email_verified_at FROM users WHERE id = ?', [$uid])['email_verified_at'] !== null);
    shop_done();
});

/* ---------- sign-in audit and alert ---------- */

test('sign-ins, failures and sign-outs are written to the audit log without email addresses', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => 'wrong wrong wrong'], '10.5.9.1');
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.5.9.2');
    post('/account/sign-out');
    $actions = array_column(Db::fromEnv()->all('SELECT action FROM audit_log'), 'action');
    foreach (['auth.signin_failed', 'auth.signin', 'auth.signout'] as $a) {
        assert_true(in_array($a, $actions, true), $a);
    }
    foreach (Db::fromEnv()->all('SELECT target, detail FROM audit_log') as $r) {
        assert_true(!str_contains(implode(' ', array_map('strval', $r)), 'ama@example.test'), 'an email address is in the audit log');
    }
    shop_done();
});

test('five failed staff sign-ins email the owners once', function (): void {
    $mail = gaps_env();
    $n = count($mail->sent);
    for ($i = 0; $i < 4; $i++) {
        post('/admin/sign-in', ['email' => 'staff@example.test', 'password' => 'bad bad bad ' . $i], '10.5.10.' . ($i + 1));
    }
    assert_same($n, count($mail->sent), 'alerted too early');
    post('/admin/sign-in', ['email' => 'staff@example.test', 'password' => 'bad bad bad 5'], '10.5.10.9');
    assert_same($n + 1, count($mail->sent));
    assert_same('owner@example.test', end($mail->sent)['to']);
    assert_contains('Repeated failed staff sign-ins', end($mail->sent)['subject']);
    post('/admin/sign-in', ['email' => 'staff@example.test', 'password' => 'bad bad bad 6'], '10.5.10.10');
    assert_same($n + 1, count($mail->sent), 'alerted again');
    shop_done();
});

/* ---------- staff management and the activity log ---------- */

function owner_login(OutboxMailer $mail): void
{
    login_as($mail, 'owner');
    post('/admin/confirm/code', ['next' => '/admin/staff'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/staff', 'code' => $mail->lastCode()], '10.4.0.1');
}

test('only the owner can see staff and the activity log', function (): void {
    $mail = gaps_env();
    $r = App::router();
    foreach (['/admin/staff', '/admin/audit'] as $p) {
        assert_same(302, $r->dispatch(new Request('GET', $p))->status, $p);
    }
    login_as($mail, 'staff');
    foreach (['/admin/staff', '/admin/audit'] as $p) {
        assert_same(403, $r->dispatch(new Request('GET', $p))->status, $p);
    }
    assert_same(403, post('/admin/staff', ['name' => 'X', 'email' => 'x@example.test'])->status);
    assert_same(403, post('/admin/staff/1/active', ['active' => '0'])->status);
    shop_done();
});

test('adding staff needs a fresh code, creates a verified account and emails how to set a password', function (): void {
    $mail = gaps_env();
    login_as($mail, 'owner');
    $res = post('/admin/staff', ['name' => 'Efua Staff', 'email' => 'efua@example.test']);
    assert_contains('/admin/confirm', $res->headers['Location'] ?? '');
    assert_same(0, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM users WHERE email = 'efua@example.test'")['n']);
    post('/admin/confirm/code', ['next' => '/admin/staff'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/staff', 'code' => $mail->lastCode()], '10.4.0.1');
    assert_same(302, post('/admin/staff', ['name' => 'Efua Staff', 'email' => 'Efua@Example.test'])->status);
    $u = Db::fromEnv()->one("SELECT role, is_active, email_verified_at FROM users WHERE email = 'efua@example.test'");
    assert_same('staff', $u['role']);
    assert_true($u['email_verified_at'] !== null);
    assert_contains('/admin/forgot', end($mail->sent)['body']);
    assert_same(1, count_rows("action = 'staff.create'"));
    assert_same(422, post('/admin/staff', ['name' => 'Again', 'email' => 'efua@example.test'])->status);
    assert_same(422, post('/admin/staff', ['name' => '', 'email' => 'nope'])->status);
    // They choose a password with the reset code, then sign in.
    post('/admin/logout-not-a-route', [], '10.4.0.1');
    post('/admin/sign-out');
    post('/admin/forgot', ['email' => 'efua@example.test'], '10.6.0.1');
    post('/admin/reset', ['code' => $mail->lastCode(), 'password' => NEW_PW, 'password2' => NEW_PW], '10.6.0.1');
    post('/admin/sign-in', ['email' => 'efua@example.test', 'password' => NEW_PW], '10.6.0.2');
    post('/admin/verify', ['code' => $mail->lastCode()], '10.6.0.2');
    Auth::reset();
    assert_true(Auth::staff() !== null, 'new staff could not sign in');
    assert_same(403, App::router()->dispatch(new Request('GET', '/admin/staff'))->status, 'new staff got owner pages');
    shop_done();
});

test('switching staff off ends their access at once, and owners and yourself cannot be switched off', function (): void {
    $mail = gaps_env();
    [, $staffId, $ownerId] = [null, (int) Db::fromEnv()->one("SELECT id FROM users WHERE role = 'staff'")['id'], (int) Db::fromEnv()->one("SELECT id FROM users WHERE role = 'owner'")['id']];
    login_as($mail, 'staff');
    assert_true(Auth::staff() !== null);
    $staffSession = $_SESSION;
    $_SESSION = [];
    owner_login($mail);
    assert_same(302, post('/admin/staff/' . $staffId . '/active', ['active' => '0'])->status);
    assert_same(0, (int) Db::fromEnv()->one('SELECT is_active FROM users WHERE id = ?', [$staffId])['is_active']);
    assert_same(1, count_rows("action = 'staff.deactivate'"));
    post('/admin/staff/' . $ownerId . '/active', ['active' => '0']);
    assert_same(1, (int) Db::fromEnv()->one('SELECT is_active FROM users WHERE id = ?', [$ownerId])['is_active'], 'the owner switched themselves off');
    $ownerSession = $_SESSION;
    $_SESSION = $staffSession;
    Auth::reset();
    assert_same(null, Auth::staff(), 'a switched-off account kept its session');
    $_SESSION = $ownerSession;
    Auth::reset();
    post('/admin/staff/' . $staffId . '/active', ['active' => '1']);
    assert_same(1, (int) Db::fromEnv()->one('SELECT is_active FROM users WHERE id = ?', [$staffId])['is_active']);
    shop_done();
});

test('the activity log shows entries by area, records that it was opened, and needs no secrets', function (): void {
    $mail = gaps_env();
    owner_login($mail);
    post('/admin/products/1/size/10', ['label' => '5 L', 'stock' => 'low']);
    $all = App::router()->dispatch(new Request('GET', '/admin/audit'));
    assert_same(200, $all->status);
    assert_contains('auth.signin', $all->body);
    assert_contains('confirm.ok', $all->body);
    $orders = App::router()->dispatch(new Request('GET', '/admin/audit', ['area' => 'size.']));
    assert_contains('size.update', $orders->body);
    assert_true(!str_contains($orders->body, 'auth.signin'), 'the filter did not filter');
    assert_true(str_contains(App::router()->dispatch(new Request('GET', '/admin/audit', ['area' => "x' OR 1=1"]))->body, 'auth.signin'), 'an unknown area should show everything');
    assert_true(count_rows("action = 'audit.view'") >= 2);
    shop_done();
});

/* ---------- account self-service ---------- */

test('customers can change their name and phone, and bad values are refused', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    customer_login($mail);
    post('/account/details', ['name' => 'Ama K Mensah', 'phone' => '0244 111 222']);
    $u = Db::fromEnv()->one("SELECT name, phone FROM users WHERE email = 'ama@example.test'");
    assert_same(['Ama K Mensah', '0244 111 222'], [$u['name'], $u['phone']]);
    post('/account/details', ['name' => '', 'phone' => 'abc']);
    assert_same('Ama K Mensah', Db::fromEnv()->one("SELECT name FROM users WHERE email = 'ama@example.test'")['name']);
    assert_same(302, post('/account/details', ['name' => 'X', 'phone' => '0244 111 222', 'email' => 'new@example.test', 'role' => 'owner'])->status);
    $u = Db::fromEnv()->one("SELECT email, role FROM users WHERE name = 'X'");
    assert_same(['ama@example.test', 'customer'], [$u['email'], $u['role']], 'email or role changed through the details form');
    shop_done();
});

test('changing a password needs the current one, ends other sessions, keeps this one, and emails a notice', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    customer_login($mail);
    post('/account/password', ['current' => 'not my password', 'password' => NEW_PW, 'password2' => NEW_PW]);
    assert_true(password_verify(GOOD_PW, Db::fromEnv()->one("SELECT password_hash FROM users WHERE email = 'ama@example.test'")['password_hash']), 'password changed with a wrong current password');
    $n = count($mail->sent);
    post('/account/password', ['current' => GOOD_PW, 'password' => 'short', 'password2' => 'short']);
    post('/account/password', ['current' => GOOD_PW, 'password' => NEW_PW, 'password2' => 'other']);
    assert_same($n, count($mail->sent));
    $other = $_SESSION['auth'];
    $other['started'] = time() - 60;
    post('/account/password', ['current' => GOOD_PW, 'password' => NEW_PW, 'password2' => NEW_PW]);
    assert_same($n + 1, count($mail->sent));
    Auth::reset();
    assert_true(Auth::customer() !== null, 'this session was ended by its own password change');
    $_SESSION['auth'] = $other;
    Auth::reset();
    assert_same(null, Auth::customer(), 'another session survived');
    shop_done();
});

test('password change attempts are rate limited', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    customer_login($mail);
    for ($i = 0; $i < 6; $i++) {
        post('/account/password', ['current' => 'nope nope nope ' . $i, 'password' => NEW_PW, 'password2' => NEW_PW]);
    }
    post('/account/password', ['current' => GOOD_PW, 'password' => NEW_PW, 'password2' => NEW_PW]);
    assert_true(password_verify(GOOD_PW, Db::fromEnv()->one("SELECT password_hash FROM users WHERE email = 'ama@example.test'")['password_hash']), 'the limit did not stop a sixth attempt');
    shop_done();
});

function address_in(array $over = []): array
{
    return $over + ['label' => 'Home', 'name' => 'Ama Mensah', 'phone' => '+233 24 000 0000', 'street' => '12 Sample Street', 'city' => 'Accra', 'region' => 'Greater Accra'];
}

test('saved addresses: the first is the default, defaults move, removing a default promotes another', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    customer_login($mail);
    post('/account/addresses', address_in());
    post('/account/addresses', address_in(['label' => 'Office', 'street' => '3 Other Road']));
    $rows = Db::fromEnv()->all('SELECT id, label, is_default FROM addresses ORDER BY id');
    assert_same([1, 0], [(int) $rows[0]['is_default'], (int) $rows[1]['is_default']]);
    post('/account/addresses/' . $rows[1]['id'] . '/default');
    assert_same('Office', Db::fromEnv()->one('SELECT label FROM addresses WHERE is_default = 1')['label']);
    post('/account/addresses/' . $rows[1]['id'] . '/delete');
    assert_same(1, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM addresses WHERE is_default = 1')['n']);
    assert_same('Home', Db::fromEnv()->one('SELECT label FROM addresses WHERE is_default = 1')['label']);
    shop_done();
});

test('saved addresses are validated, capped at ten, and private to their owner', function (): void {
    $mail = gaps_env();
    add_customer($mail);
    $other = add_customer($mail, 'kofi@example.test');
    customer_login($mail);
    foreach ([address_in(['street' => '']), address_in(['region' => 'Mars']), address_in(['phone' => 'x']), address_in(['label' => ''])] as $bad) {
        post('/account/addresses', $bad);
    }
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM addresses')['n']);
    for ($i = 0; $i < 12; $i++) {
        post('/account/addresses', address_in(['label' => 'A' . $i]));
    }
    assert_same(10, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM addresses')['n']);
    Db::fromEnv()->run("INSERT INTO addresses (user_id, label, name, phone, street, city, region, is_default, created_at) VALUES (?, 'Theirs', 'K', '0244000000', 's', 'c', 'Ashanti', 1, 1)", [$other]);
    $theirs = (int) Db::fromEnv()->one("SELECT id FROM addresses WHERE label = 'Theirs'")['id'];
    post('/account/addresses/' . $theirs . '/delete');
    post('/account/addresses/' . $theirs . '/default');
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM addresses WHERE label = 'Theirs' AND is_default = 1")['n'], "another customer's address was changed");
    assert_true(!str_contains(App::router()->dispatch(new Request('GET', '/account'))->body, 'Theirs'), "another customer's address was shown");
    shop_done();
});

test('checkout is prefilled from the default or chosen address and can save a new one', function (): void {
    $mail = gaps_env();
    $uid = add_customer($mail);
    customer_login($mail);
    post('/account/addresses', address_in(['label' => 'Home']));
    post('/account/addresses', address_in(['label' => 'Office', 'street' => '3 Other Road', 'city' => 'Kumasi', 'region' => 'Ashanti']));
    \Belis\Domain\Cart::add(10, 1);
    $page = App::router()->dispatch(new Request('GET', '/checkout'));
    assert_contains('12 Sample Street', $page->body);
    $office = (int) Db::fromEnv()->one("SELECT id FROM addresses WHERE label = 'Office'")['id'];
    assert_contains('3 Other Road', App::router()->dispatch(new Request('GET', '/checkout', ['address' => (string) $office]))->body);
    assert_true(!str_contains(App::router()->dispatch(new Request('GET', '/checkout', ['address' => '99999']))->body, '3 Other Road'));
    post('/checkout', address(['street' => '77 Brand New Lane', 'save_address' => '1']));
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM addresses WHERE street = '77 Brand New Lane'")['n']);
    \Belis\Domain\Cart::add(10, 1);
    post('/checkout', address(['street' => '77 brand new lane', 'save_address' => '1']));
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM addresses WHERE LOWER(street) = '77 brand new lane'")['n'], 'a duplicate address was saved');
    Belis\Payments\Payments::useForTests(null);
    shop_done();
});

/* ---------- order emails ---------- */

function email_order(OutboxMailer $mail): string
{
    $uid = add_customer($mail);
    return make_order($uid, 'pending', 2);
}

test('the customer gets one confirmation email when the order becomes paid, and none on a replay', function (): void {
    $mail = gaps_env();
    $ref = email_order($mail);
    $fake = new FakeAdapter();
    Belis\Payments\Payments::useForTests($fake);
    $o = Db::fromEnv()->one('SELECT payment_reference, total_pesewas FROM orders');
    $orders = new Orders(Db::fromEnv());
    $n = count($mail->sent);
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    $orders->confirm($o['payment_reference'], $fake, 'callback');
    assert_same($n + 1, count($mail->sent));
    $m = end($mail->sent);
    assert_same('ama@example.test', $m['to']);
    assert_contains($ref, $m['body']);
    assert_contains('Bleach', $m['body']);
    assert_contains('12 Sample Street', $m['body']);
    assert_true(!str_contains($m['body'], $o['payment_reference']), 'the payment reference was emailed');
    $orders->confirm($o['payment_reference'], $fake, 'webhook');
    assert_same($n + 1, count($mail->sent), 'a replay sent another email');
    shop_done();
});

test('a failed or missing email never stops an order becoming paid', function (): void {
    $mail = gaps_env();
    email_order($mail);
    Mailer::useForTests(new class extends Mailer {
        public function send(string $to, string $subject, string $body): void
        {
            throw new RuntimeException('mail server down');
        }
    });
    $fake = new FakeAdapter();
    $o = Db::fromEnv()->one('SELECT payment_reference, total_pesewas FROM orders');
    $fake->say('success', (int) $o['total_pesewas'], $o['payment_reference']);
    assert_same('paid', (new Orders(Db::fromEnv()))->confirm($o['payment_reference'], $fake, 'callback'));
    shop_done();
});

test('staff progress emails go out for packed, out for delivery and delivered, once each, never for "to pack"', function (): void {
    $mail = gaps_env();
    $ref = email_order($mail);
    Db::fromEnv()->run("UPDATE orders SET status = 'paid'");
    $orders = new Orders(Db::fromEnv());
    $n = count($mail->sent);
    assert_same(['ok' => true, 'changed' => false], $orders->setFulfilment($ref, 'new'));
    assert_same($n, count($mail->sent));
    assert_same(['ok' => true, 'changed' => true], $orders->setFulfilment($ref, 'packed'));
    assert_contains('packed', end($mail->sent)['body']);
    $orders->setFulfilment($ref, 'packed');
    assert_same($n + 1, count($mail->sent), 'the same step emailed twice');
    $orders->setFulfilment($ref, 'out_for_delivery');
    assert_contains('out for delivery', end($mail->sent)['body']);
    $orders->setFulfilment($ref, 'delivered');
    assert_contains('delivered', end($mail->sent)['body']);
    assert_same($n + 3, count($mail->sent));
    assert_same(['ok' => false, 'changed' => false], $orders->setFulfilment($ref, 'nonsense'));
    Db::fromEnv()->run("UPDATE orders SET status = 'pending'");
    assert_same(['ok' => false, 'changed' => false], $orders->setFulfilment($ref, 'packed'));
    shop_done();
});

test('the preview people cannot change staff accounts, details or addresses', function (): void {
    gaps_env();
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'owner', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    Auth::reset();
    $r = App::router();
    assert_same(200, $r->dispatch(new Request('GET', '/admin/staff'))->status);
    assert_same(200, $r->dispatch(new Request('GET', '/admin/audit'))->status);
    assert_same(302, post('/admin/staff', ['name' => 'X', 'email' => 'x@example.test'])->status);
    assert_same(302, post('/account/details', ['name' => 'X', 'phone' => '0244000000'])->status);
    assert_same(302, post('/account/addresses', address_in())->status);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM users WHERE email = \'x@example.test\'')['n']);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM addresses')['n']);
    shop_done();
});
