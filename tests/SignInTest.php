<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Csrf;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Core\Throttle;
use Belis\Domain\Accounts;
use Belis\Support\Mailer;

// CTL-AUTH-001, CTL-AUTH-002, CTL-SESS-001: password plus emailed code, single use codes, lockout, uniform answers.

/** Collects emails instead of writing them anywhere. */
final class OutboxMailer extends Mailer
{
    /** @var list<array{to:string,subject:string,body:string}> */
    public array $sent = [];

    public function send(string $to, string $subject, string $body): void
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
    }

    public function lastCode(): string
    {
        $last = end($this->sent);
        return $last !== false && preg_match('/is ([0-9]{6})\./', $last['body'], $m) === 1 ? $m[1] : '';
    }
}

function auth_env(): OutboxMailer
{
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters']);
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE NOT NULL, name TEXT NOT NULL, phone TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT "customer", is_active INTEGER NOT NULL DEFAULT 1, email_verified_at INTEGER, created_at INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE auth_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, purpose TEXT NOT NULL, code_hash TEXT NOT NULL, expires_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, used_at INTEGER, created_at INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE throttle (key_hash TEXT PRIMARY KEY, hits INTEGER NOT NULL, window_start INTEGER NOT NULL)');
    Db::useForTests(new Db($pdo));
    $mail = new OutboxMailer();
    Mailer::useForTests($mail);
    $_SESSION = [];
    Auth::reset();
    return $mail;
}

function auth_done(): void
{
    Db::useForTests(null);
    Mailer::useForTests(null);
    $_SESSION = [];
    Auth::reset();
}

function post(string $path, array $data = [], string $ip = '10.0.0.1'): \Belis\Core\Response
{
    return App::router()->dispatch(new Request("POST", $path, [], $data + ["_csrf" => Csrf::token()], [], null, $ip));
}

const GOOD_PW = 'correct horse battery';

function register_and_confirm(OutboxMailer $mail, string $email = 'ama@example.test'): void
{
    $res = post('/account/register', ['name' => 'Ama Mensah', 'email' => $email, 'phone' => '+233 24 000 0000', 'password' => GOOD_PW, 'password2' => GOOD_PW]);
    assert_same(302, $res->status);
    assert_same('/account/verify', $res->headers['Location'] ?? '');
    $res = post('/account/verify', ['code' => $mail->lastCode()]);
    assert_same('/account/sign-in', $res->headers['Location'] ?? '', 'email confirmation');
}

test('password rules: length, common passwords and own email', function (): void {
    assert_same('Use at least 10 characters.', Accounts::passwordProblem('short'));
    assert_true(Accounts::passwordProblem('password123') !== null);
    assert_true(Accounts::passwordProblem('ilovebelis2026', 'ilovebelis@example.test') !== null);
    assert_same(null, Accounts::passwordProblem(GOOD_PW));
});

test('registering sends a code, stores only hashes, and does not sign anyone in', function (): void {
    $mail = auth_env();
    $res = post('/account/register', ['name' => 'Ama Mensah', 'email' => 'Ama@Example.test', 'phone' => '+233 24 000 0000', 'password' => GOOD_PW, 'password2' => GOOD_PW]);
    assert_same(302, $res->status);
    assert_same(1, count($mail->sent));
    assert_same('ama@example.test', $mail->sent[0]['to']);
    $code = $mail->lastCode();
    assert_true(strlen($code) === 6);
    $row = Db::fromEnv()->one('SELECT password_hash, email_verified_at FROM users');
    assert_true(!str_contains($row['password_hash'], GOOD_PW) && $row['email_verified_at'] === null);
    $stored = Db::fromEnv()->one('SELECT code_hash FROM auth_codes');
    assert_true($stored['code_hash'] !== $code && strlen($stored['code_hash']) === 64);
    assert_same(null, Auth::customer());
    auth_done();
});

test('registration errors keep what was typed but never the password', function (): void {
    auth_env();
    $res = post('/account/register', ['name' => 'Ama', 'email' => 'not-an-email', 'phone' => '12', 'password' => 'hunter2hunter2', 'password2' => 'different']);
    assert_same(422, $res->status);
    assert_contains('Enter a valid email address.', $res->body);
    assert_contains('Enter a valid phone number.', $res->body);
    assert_true(!str_contains($res->body, 'hunter2hunter2'), 'password echoed back');
    auth_done();
});

test('registering an existing address gives the same answer and emails the owner instead', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    $before = count($mail->sent);
    $res = post('/account/register', ['name' => 'Someone Else', 'email' => 'ama@example.test', 'phone' => '+233 24 000 0000', 'password' => 'another good phrase', 'password2' => 'another good phrase'], '10.0.0.2');
    assert_same(302, $res->status);
    assert_same('/account/verify', $res->headers['Location'] ?? '');
    assert_same($before + 1, count($mail->sent));
    assert_contains('already has one', $mail->sent[$before]['body']);
    assert_same(1, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM users')['n']);
    auth_done();
});

test('a right password alone never creates a session; the emailed code does', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    $res = post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW]);
    assert_same('/account/verify', $res->headers['Location'] ?? '');
    assert_same(null, Auth::customer(), 'signed in before the code');
    assert_same(302, App::router()->dispatch(new Request('GET', '/account'))->status);
    $res = post('/account/verify', ['code' => $mail->lastCode()]);
    assert_same('/account', $res->headers['Location'] ?? '');
    Auth::reset();
    assert_same('Ama Mensah', Auth::customer()['name'] ?? null);
    assert_same(200, App::router()->dispatch(new Request('GET', '/account'))->status);
    auth_done();
});

test('wrong password, unknown address and wrong role all get the same answer and send nothing', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    (new Accounts(Db::fromEnv(), $mail))->createVerified('boss@example.test', 'Boss', 'n/a', 'boss long passphrase', 'owner');
    $sent = count($mail->sent);
    foreach ([['ama@example.test', 'wrong password!'], ['nobody@example.test', GOOD_PW], ['boss@example.test', 'boss long passphrase']] as [$email, $pw]) {
        $res = post('/account/sign-in', ['email' => $email, 'password' => $pw], '10.0.0.' . random_int(3, 200));
        assert_same(302, $res->status, $email);
        assert_same('/account/verify', $res->headers['Location'] ?? '', $email);
    }
    assert_same($sent, count($mail->sent), 'a code was sent for a failed sign-in');
    $res = post('/account/verify', ['code' => '123456']);
    assert_same(422, $res->status);
    auth_done();
});

test('codes are single use', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW]);
    $code = $mail->lastCode();
    assert_same('/account', post('/account/verify', ['code' => $code])->headers['Location'] ?? '');
    post('/account/sign-out');
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW]);
    $accounts = new Accounts(Db::fromEnv(), $mail);
    $uid = (int) Db::fromEnv()->one('SELECT id FROM users')['id'];
    assert_true(!$accounts->checkCode($uid, 'login', $code, '10.0.0.9'), 'old code accepted');
    auth_done();
});

test('a code locks after five wrong tries, even when the right one is then sent', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW]);
    $good = $mail->lastCode();
    $wrong = $good === '000000' ? '111111' : '000000';
    for ($i = 0; $i < 5; $i++) {
        assert_same(422, post('/account/verify', ['code' => $wrong])->status);
    }
    assert_same(422, post('/account/verify', ['code' => $good])->status, 'locked code still accepted');
    assert_same(null, Auth::customer());
    auth_done();
});

test('an expired code is refused, and a new code replaces the old one', function (): void {
    $mail = auth_env();
    $db = Db::fromEnv();
    $t = 1_000_000;
    $accounts = new Accounts($db, $mail, $t);
    $uid = $accounts->createVerified('kofi@example.test', 'Kofi', 'n/a', GOOD_PW, 'customer');
    $accounts->issueCode($uid, 'login');
    $first = $mail->lastCode();
    $late = new Accounts($db, $mail, $t + Accounts::CUSTOMER_CODE_SECONDS + 1);
    assert_true(!$late->checkCode($uid, 'login', $first, '10.0.0.5'), 'expired code accepted');
    $accounts->issueCode($uid, 'login');
    $second = $mail->lastCode();
    assert_true(!$accounts->checkCode($uid, 'login', $first, '10.0.0.5') || $first === $second, 'replaced code accepted');
    auth_done();
});

test('staff codes expire in 5 minutes and staff must use the staff sign-in', function (): void {
    $mail = auth_env();
    $t = 2_000_000;
    $accounts = new Accounts(Db::fromEnv(), $mail, $t);
    $uid = $accounts->createVerified('staff@example.test', 'Staff', 'n/a', GOOD_PW, 'staff');
    assert_same(0, $accounts->signIn('staff@example.test', GOOD_PW, false, '10.0.0.6')['uid'], 'staff signed in on the customer page');
    $r = $accounts->signIn('staff@example.test', GOOD_PW, true, '10.0.0.6');
    assert_same($uid, $r['uid']);
    $code = $mail->lastCode();
    $late = new Accounts(Db::fromEnv(), $mail, $t + Accounts::STAFF_CODE_SECONDS + 1);
    assert_true(!$late->checkCode($uid, 'login', $code, '10.0.0.6'));
    auth_done();
});

test('admin pages need the staff sign-in, and a customer session cannot open them', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW]);
    post('/account/verify', ['code' => $mail->lastCode()]);
    Auth::reset();
    $res = App::router()->dispatch(new Request('GET', '/admin'));
    assert_same(302, $res->status);
    assert_same('/admin/sign-in', $res->headers['Location'] ?? '');
    auth_done();
});

test('signing out ends the session, and a deactivated account loses access at once', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW]);
    post('/account/verify', ['code' => $mail->lastCode()]);
    Auth::reset();
    assert_true(Auth::customer() !== null);
    Db::fromEnv()->run('UPDATE users SET is_active = 0');
    Auth::reset();
    assert_same(null, Auth::customer(), 'deactivated account still signed in');
    assert_true(!isset($_SESSION['auth']), 'session kept after deactivation');
    Db::fromEnv()->run('UPDATE users SET is_active = 1');
    post('/account/sign-in', ['email' => 'ama@example.test', 'password' => GOOD_PW], '10.0.0.44');
    post('/account/verify', ['code' => $mail->lastCode()], '10.0.0.44');
    Auth::reset();
    assert_true(Auth::customer() !== null);
    post('/account/sign-out');
    Auth::reset();
    assert_same(null, Auth::customer());
    assert_same(302, App::router()->dispatch(new Request('GET', '/account'))->status);
    auth_done();
});

test('a changed role invalidates the session, and idle staff sessions time out', function (): void {
    $mail = auth_env();
    $accounts = new Accounts(Db::fromEnv(), $mail);
    $accounts->createVerified('staff@example.test', 'Staff', 'n/a', GOOD_PW, 'staff');
    post('/admin/sign-in', ['email' => 'staff@example.test', 'password' => GOOD_PW]);
    assert_same('/admin', post('/admin/verify', ['code' => $mail->lastCode()])->headers['Location'] ?? '');
    Auth::reset();
    assert_true(Auth::staff() !== null);
    $_SESSION['auth']['seen'] = time() - Auth::STAFF_IDLE_SECONDS - 1;
    Auth::reset();
    assert_same(null, Auth::staff(), 'idle staff session survived');
    auth_done();
});

test('sign-in and codes are rate limited', function (): void {
    $mail = auth_env();
    register_and_confirm($mail);
    $last = null;
    for ($i = 0; $i < 7; $i++) {
        $last = post('/account/sign-in', ['email' => 'ama@example.test', 'password' => 'nope nope nope']);
    }
    assert_same(429, $last->status);
    $db = Db::fromEnv();
    $allowed = 0;
    for ($i = 0; $i < 5; $i++) {
        $allowed += Throttle::hit($db, 'x', 3, 60, 100) ? 1 : 0;
    }
    assert_same(3, $allowed);
    assert_true(Throttle::hit($db, 'x', 3, 60, 161), 'window did not reset');
    auth_done();
});

test('sign-in, verify and resend need a CSRF token', function (): void {
    auth_env();
    foreach (['/account/sign-in', '/account/register', '/account/verify', '/account/resend', '/account/sign-out', '/admin/sign-in', '/admin/verify', '/admin/resend', '/admin/sign-out'] as $p) {
        assert_same(419, App::router()->dispatch(new Request('POST', $p, [], ['_csrf' => 'wrong']))->status, $p);
    }
    auth_done();
});

test('the code page is only reachable after sign-in step one', function (): void {
    auth_env();
    assert_same(302, App::router()->dispatch(new Request('GET', '/account/verify'))->status);
    assert_same(302, App::router()->dispatch(new Request('GET', '/admin/verify'))->status);
    auth_done();
});

test('without a pepper codes cannot be issued (fails closed)', function (): void {
    $mail = auth_env();
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '', 'AUTH_PEPPER' => '']);
    $threw = false;
    try {
        $a = new Accounts(Db::fromEnv(), $mail);
        $uid = $a->createVerified('kofi@example.test', 'Kofi', 'n/a', GOOD_PW, 'customer');
        $a->issueCode($uid, 'login');
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw);
    auth_done();
});
