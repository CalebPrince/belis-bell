<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Core\Secrets;
use Belis\Domain\Accounts;
use Belis\Payments\Payments;
use Belis\Support\Audit;
use Belis\Support\Settings;
use Belis\Support\Smtp;
use Belis\Support\SmtpConnection;

// CTL-SET-001, CTL-AUDIT-001, THR-023: encrypted settings, owner only, fresh code, write-only, audited.

const KEY_A = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
const KEY_B = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';
const SK = 'sk_test_ABCDEFGHIJ1234567890';
const PK = 'pk_test_ABCDEFGHIJ1234567890';

/** @param array<string,string> $extra */
function settings_env(array $extra = []): OutboxMailer
{
    $mail = auth_env();
    Env::fake($extra + ['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => '', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A, 'APP_URL' => 'http://localhost', 'PAYMENTS_ADAPTER' => 'paystack']);
    $pdo = Db::fromEnv()->pdo();
    $pdo->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value_enc TEXT NOT NULL, updated_by INTEGER, updated_at INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, target TEXT, ip TEXT, created_at INTEGER)');
    Settings::forget();
    Payments::useForTests(null);
    return $mail;
}

function sign_in_owner(OutboxMailer $mail, string $role = 'owner'): int
{
    $uid = (new Accounts(Db::fromEnv(), $mail))->createVerified($role . '@example.test', 'Boss', 'n/a', GOOD_PW, $role);
    post('/admin/sign-in', ['email' => $role . '@example.test', 'password' => GOOD_PW], '10.2.0.1');
    post('/admin/verify', ['code' => $mail->lastCode()], '10.2.0.1');
    Auth::reset();
    return $uid;
}

function confirm_it(OutboxMailer $mail): void
{
    post('/admin/settings/code', [], '10.2.0.1');
    post('/admin/settings/verify', ['code' => $mail->lastCode()], '10.2.0.1');
}

function get(string $path): \Belis\Core\Response
{
    return App::router()->dispatch(new Request('GET', $path));
}

test('secrets round trip, differ each time, and fail when tampered, moved or opened with another key', function (): void {
    Env::fake(['SETTINGS_KEY' => KEY_A]);
    $a = Secrets::encrypt('hello', 'NAME');
    $b = Secrets::encrypt('hello', 'NAME');
    assert_true($a !== $b, 'same ciphertext twice');
    assert_same('hello', Secrets::decrypt($a, 'NAME'));
    assert_true(!str_contains($a, 'hello'));
    foreach ([fn () => Secrets::decrypt($a, 'OTHER'), fn () => Secrets::decrypt($a, 'NAME', KEY_B), fn () => Secrets::decrypt(substr($a, 0, -3) . 'AAA', 'NAME'), fn () => Secrets::decrypt('plain', 'NAME')] as $i => $bad) {
        $threw = false;
        try {
            $bad();
        } catch (RuntimeException) {
            $threw = true;
        }
        assert_true($threw, "case $i accepted");
    }
    Env::fake(['SETTINGS_KEY' => 'short']);
    $threw = false;
    try {
        Secrets::encrypt('x', 'N');
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw, 'a bad master key was accepted');
});

test('saved settings are ciphertext in the database, win over the environment file, and secrets are masked', function (): void {
    settings_env(['PAYSTACK_SECRET_KEY' => 'sk_test_FROMENVFILE1234567']);
    assert_same('sk_test_FROMENVFILE1234567', Settings::get('PAYSTACK_SECRET_KEY'));
    assert_same('environment', Settings::source('PAYSTACK_SECRET_KEY'));
    assert_same(null, Settings::save(Db::fromEnv(), 'PAYSTACK_SECRET_KEY', SK, 1));
    assert_same(SK, Settings::get('PAYSTACK_SECRET_KEY'));
    assert_same('settings', Settings::source('PAYSTACK_SECRET_KEY'));
    $raw = Db::fromEnv()->one('SELECT value_enc FROM settings')['value_enc'];
    assert_true(!str_contains($raw, SK) && !str_contains($raw, 'ABCDEFGHIJ'), 'stored in the clear');
    assert_same('ends in 7890', Settings::display('PAYSTACK_SECRET_KEY'));
    Settings::save(Db::fromEnv(), 'WHATSAPP_NUMBER', '+233 24 111 2222', 1);
    assert_same('+233 24 111 2222', Settings::display('WHATSAPP_NUMBER'));
    Settings::clear(Db::fromEnv(), 'PAYSTACK_SECRET_KEY');
    assert_same('sk_test_FROMENVFILE1234567', Settings::get('PAYSTACK_SECRET_KEY'), 'no fallback to the environment file');
    auth_done();
});

test('a saved value moved to another setting name cannot be read', function (): void {
    settings_env();
    Settings::save(Db::fromEnv(), 'PAYSTACK_SECRET_KEY', SK, 1);
    Db::fromEnv()->run("UPDATE settings SET name = 'SMTP_PASSWORD'");
    Settings::forget();
    assert_same(null, Settings::get('SMTP_PASSWORD'));
    auth_done();
});

test('values are checked for shape, live keys, test keys and mixed modes', function (): void {
    settings_env();
    assert_true(Settings::validate('PAYSTACK_SECRET_KEY', 'not a key') !== null);
    assert_true(Settings::validate('PAYSTACK_SECRET_KEY', 'sk_live_ABCDEFGHIJ1234567890') !== null, 'live key accepted locally');
    assert_same(null, Settings::validate('PAYSTACK_SECRET_KEY', SK));
    assert_true(Settings::validate('PAYSTACK_PUBLIC_KEY', SK) !== null);
    assert_true(Settings::validate('SMTP_HOST', 'bad host!') !== null);
    assert_true(Settings::validate('SMTP_PORT', '70000') !== null);
    assert_true(Settings::validate('SMTP_ENCRYPTION', 'none') !== null);
    assert_true(Settings::validate('MAIL_FROM_ADDRESS', 'nope') !== null);
    assert_true(Settings::validate('MAIL_FROM_NAME', 'Evil <x>') !== null);
    assert_true(Settings::validate('SMTP_PASSWORD', "line\nbreak") !== null);
    assert_true(Settings::validate('WHATSAPP_NUMBER', '12') !== null);
    assert_true(Settings::validate('NOT_A_SETTING', 'x') !== null);
    Settings::save(Db::fromEnv(), 'PAYSTACK_SECRET_KEY', SK, 1);
    assert_true(Settings::save(Db::fromEnv(), 'PAYSTACK_PUBLIC_KEY', 'pk_live_ABCDEFGHIJ1234567890', 1) !== null);
    Env::fake(['APP_ENV' => 'production', 'SETTINGS_KEY' => KEY_A, 'RELEASE_APPROVAL_REF' => '']);
    assert_true(Settings::validate('PAYSTACK_SECRET_KEY', SK) !== null, 'test key accepted in production');
    assert_true(Settings::validate('PAYSTACK_SECRET_KEY', 'sk_live_ABCDEFGHIJ1234567890') !== null, 'live key accepted without approval ref');
    assert_same(null, Settings::validate('PAYSTACK_SECRET_KEY', 'sk_live_ABCDEFGHIJ1234567890') === null ? null : null);
    Env::fake(['APP_ENV' => 'production', 'SETTINGS_KEY' => KEY_A, 'RELEASE_APPROVAL_REF' => 'REL-1']);
    assert_same(null, Settings::validate('PAYSTACK_SECRET_KEY', 'sk_live_ABCDEFGHIJ1234567890'));
    assert_true(Settings::validate('MAIL_DRIVER', 'log') !== null, 'log mail accepted in production');
    auth_done();
});

test('keys in use that break the environment rules are reported, wherever they came from', function (): void {
    settings_env();
    Db::fromEnv()->run("INSERT INTO settings (name, value_enc, updated_at) VALUES ('PAYSTACK_SECRET_KEY', '" . Secrets::encrypt('sk_live_ABCDEFGHIJ1234567890', 'PAYSTACK_SECRET_KEY') . "', 1)");
    Settings::forget();
    assert_true(Settings::violations() !== [], 'live key outside production allowed');
    $threw = false;
    try {
        Payments::adapter();
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw, 'the payment adapter used a live key locally');
    auth_done();
});

test('the payment adapter uses the key saved in Settings', function (): void {
    settings_env();
    Settings::save(Db::fromEnv(), 'PAYSTACK_SECRET_KEY', SK, 1);
    assert_true(Payments::adapter() instanceof \Belis\Payments\PaystackAdapter);
    auth_done();
});

test('only a signed-in owner can open settings: signed out, customers and staff cannot', function (): void {
    $mail = settings_env();
    assert_same(302, get('/admin/settings')->status);
    assert_same('/admin/sign-in', get('/admin/settings')->headers['Location'] ?? '');
    sign_in_owner($mail, 'staff');
    assert_same(403, get('/admin/settings')->status);
    foreach (['/admin/settings', '/admin/settings/code', '/admin/settings/verify', '/admin/settings/test-email'] as $p) {
        assert_same(403, post($p, ['PAYSTACK_SECRET_KEY' => SK])->status, $p);
    }
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM settings')['n']);
    auth_done();
});

test('the local preview owner cannot open or change settings', function (): void {
    settings_env(['PREVIEW_LOGIN' => 'owner']);
    $res = get('/admin/settings');
    assert_same(302, $res->status);
    assert_same('/admin', $res->headers['Location'] ?? '');
    assert_same(302, post('/admin/settings', ['PAYSTACK_SECRET_KEY' => SK])->status);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM settings')['n']);
    auth_done();
});

test('the owner sees a code prompt first, and no field until the code is right', function (): void {
    $mail = settings_env();
    sign_in_owner($mail);
    $page = get('/admin/settings');
    assert_same(200, $page->status);
    assert_contains('Email me a code', $page->body);
    assert_true(!str_contains($page->body, 'PAYSTACK_SECRET_KEY'), 'fields shown before confirmation');
    post('/admin/settings/code', [], '10.2.0.1');
    assert_same(422, post('/admin/settings/verify', ['code' => '000000'], '10.2.0.1')->status);
    assert_true(!str_contains(get('/admin/settings')->body, 'PAYSTACK_SECRET_KEY'), 'wrong code opened the form');
    assert_true(post('/admin/settings/verify', ['code' => $mail->lastCode()], '10.2.0.1')->status === 302);
    assert_contains('PAYSTACK_SECRET_KEY', get('/admin/settings')->body);
    auth_done();
});

test('a change without a fresh code is refused, and an old confirmation expires', function (): void {
    $mail = settings_env();
    sign_in_owner($mail);
    post('/admin/settings', ['PAYSTACK_SECRET_KEY' => SK]);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM settings')['n']);
    confirm_it($mail);
    $_SESSION['stepup']['at'] = time() - 601;
    post('/admin/settings', ['PAYSTACK_SECRET_KEY' => SK]);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM settings')['n'], 'expired confirmation still worked');
    auth_done();
});

test('saving stores ciphertext, audits without values, emails the owner without values, and never shows the secret again', function (): void {
    $mail = settings_env();
    $uid = sign_in_owner($mail);
    confirm_it($mail);
    $sent = count($mail->sent);
    $res = post('/admin/settings', ['PAYSTACK_SECRET_KEY' => SK, 'PAYSTACK_PUBLIC_KEY' => PK, 'SMTP_PASSWORD' => 'my-smtp-password-123']);
    assert_same(302, $res->status);
    assert_same(SK, Settings::get('PAYSTACK_SECRET_KEY'));
    foreach (Db::fromEnv()->all('SELECT value_enc FROM settings') as $row) {
        assert_true(!str_contains($row['value_enc'], 'ABCDEFGHIJ') && !str_contains($row['value_enc'], 'my-smtp-password'), 'clear text stored');
    }
    $rows = Db::fromEnv()->all('SELECT user_id, action, target, ip FROM audit_log ORDER BY id');
    $actions = array_column($rows, 'action');
    assert_true(in_array('settings.update', $actions, true) && in_array('settings.stepup', $actions, true));
    foreach ($rows as $r) {
        assert_true(!str_contains(implode(' ', $r), 'ABCDEFGHIJ') && !str_contains(implode(' ', $r), 'my-smtp-password'), 'a value reached the audit log');
    }
    assert_same($uid, (int) $rows[0]['user_id']);
    $note = end($mail->sent);
    assert_same('owner@example.test', $note['to']);
    assert_contains('Paystack secret key', $note['body']);
    assert_true(!str_contains($note['body'], 'ABCDEFGHIJ') && !str_contains($note['body'], 'my-smtp-password'), 'a value was emailed');
    assert_true(count($mail->sent) > $sent);
    $page = get('/admin/settings');
    assert_same(200, $page->status);
    foreach ([SK, 'my-smtp-password-123'] as $secretText) {
        assert_true(!str_contains($page->body, $secretText), 'settings page shows ' . $secretText);
    }
    assert_contains('ends in 7890', $page->body);
    assert_contains('Saved in Settings', $page->body);
    auth_done();
});

test('a bad value saves nothing at all and does not echo the secrets typed', function (): void {
    $mail = settings_env();
    sign_in_owner($mail);
    confirm_it($mail);
    $res = post('/admin/settings', ['PAYSTACK_SECRET_KEY' => SK, 'SMTP_PORT' => '99999', 'SMTP_PASSWORD' => 'typed-secret-pass']);
    assert_same(422, $res->status);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM settings')['n'], 'part of a bad form was saved');
    assert_true(!str_contains($res->body, 'typed-secret-pass') && !str_contains($res->body, SK), 'secret echoed back');
    assert_contains('Enter a port number', $res->body);
    Settings::forget();
    assert_same(null, Settings::get('PAYSTACK_SECRET_KEY'));
    auth_done();
});

test('removing a saved value works and is audited', function (): void {
    $mail = settings_env();
    sign_in_owner($mail);
    confirm_it($mail);
    post('/admin/settings', ['WHATSAPP_NUMBER' => '233241112222']);
    post('/admin/settings', ['clear_WHATSAPP_NUMBER' => '1']);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM settings')['n']);
    assert_true(in_array('settings.clear', array_column(Audit::recent(Db::fromEnv(), 10), 'action'), true));
    auth_done();
});

test('settings forms need a CSRF token', function (): void {
    $mail = settings_env();
    sign_in_owner($mail);
    foreach (['/admin/settings', '/admin/settings/code', '/admin/settings/verify', '/admin/settings/test-email'] as $p) {
        assert_same(419, App::router()->dispatch(new Request('POST', $p, [], ['_csrf' => 'bad']))->status, $p);
    }
    auth_done();
});

test('the test email goes only to the owner and needs a fresh code', function (): void {
    $mail = settings_env();
    sign_in_owner($mail);
    $n = count($mail->sent);
    post('/admin/settings/test-email');
    assert_same($n, count($mail->sent), 'sent without confirmation');
    confirm_it($mail);
    post('/admin/settings/test-email');
    $last = end($mail->sent);
    assert_same('owner@example.test', $last['to']);
    assert_same('Belis Bell test email', $last['subject']);
    auth_done();
});

test('the audit log class can only add and read', function (): void {
    $src = (string) file_get_contents(BASE_PATH . '/src/Support/Audit.php');
    assert_true(preg_match('/\b(UPDATE|DELETE)\b/i', $src) !== 1, 'Audit can change or delete entries');
    $mig = (string) file_get_contents(BASE_PATH . '/database/migrations/007_settings_audit.sql');
    assert_contains('BEFORE UPDATE ON audit_log', $mig);
    assert_contains('BEFORE DELETE ON audit_log', $mig);
});

/** A scripted mail server for the SMTP client. */
final class ScriptedSmtp implements SmtpConnection
{
    /** @var list<string> */
    public array $sent = [];
    public int $tls = 0;
    public bool $closed = false;
    /** @param list<string> $replies */
    public function __construct(private array $replies)
    {
    }
    public function readLine(): string
    {
        return array_shift($this->replies) ?? '';
    }
    public function write(string $data): void
    {
        $this->sent[] = $data;
    }
    public function enableTls(): void
    {
        $this->tls++;
    }
    public function close(): void
    {
        $this->closed = true;
    }
}

function smtp_replies(bool $tls = true): array
{
    $r = ['220 hi', '250-mail', '250 OK'];
    if ($tls) {
        $r = array_merge($r, ['220 go ahead', '250-mail', '250 OK']);
    }
    return array_merge($r, ['334 VXNlcg==', '334 UGFzcw==', '235 ok', '250 ok', '250 ok', '354 go', '250 queued']);
}

test('the SMTP client follows the protocol, uses TLS, authenticates and cleans the message', function (): void {
    $c = new ScriptedSmtp(smtp_replies());
    $smtp = new Smtp('mail.example.com', 587, 'tls', 'user@example.com', 'p@ss', 'shop@example.com', "Belis
Bcc: evil@example.com", fn () => $c);
    $smtp->send('ama@example.test', "Your code
Bcc: x@y.test", "Line one
.leading dot
end");
    $all = implode('', $c->sent);
    assert_same(1, $c->tls);
    assert_true($c->closed);
    assert_contains('AUTH LOGIN', $all);
    assert_contains(base64_encode('user@example.com'), $all);
    assert_contains('RCPT TO:<ama@example.test>', $all);
    assert_true(preg_match('/
Bcc:/i', $all) !== 1, 'a header was injected');
    assert_true(strpos($all, 'STARTTLS') < strpos($all, 'AUTH LOGIN'), 'authenticated before TLS');
});

test('an address with line breaks is refused before any connection', function (): void {
    $c = new ScriptedSmtp(smtp_replies());
    $threw = false;
    try {
        (new Smtp('mail.example.com', 587, 'tls', 'u@example.com', 'p', 'shop@example.com', 'Belis', fn () => $c))->send("ama@example.test
Bcc: evil@example.test", 'Hi', 'x');
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw && $c->sent === []);
});

test('the SMTP client refuses bad addresses and stops on a server error', function (): void {
    $c = new ScriptedSmtp(smtp_replies());
    $smtp = new Smtp('mail.example.com', 587, 'tls', 'u@example.com', 'p', 'shop@example.com', 'Belis', fn () => $c);
    $threw = false;
    try {
        $smtp->send('not an address', 'Hi', 'x');
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw);
    assert_same([], $c->sent, 'connected for a bad address');
    $bad = new ScriptedSmtp(['220 hi', '250 OK', '220 go', '250 OK', '334 a', '334 b', '535 auth failed']);
    $threw = false;
    try {
        (new Smtp('mail.example.com', 587, 'tls', 'u@example.com', 'wrong', 'shop@example.com', 'Belis', fn () => $bad))->send('ama@example.test', 'Hi', 'x');
    } catch (RuntimeException $e) {
        $threw = true;
        assert_true(!str_contains($e->getMessage(), 'wrong'), 'password in the error');
    }
    assert_true($threw && $bad->closed);
});

test('implicit TLS (ssl) skips STARTTLS, and dots at line start are doubled', function (): void {
    $c = new ScriptedSmtp(smtp_replies(false));
    (new Smtp('mail.example.com', 465, 'ssl', 'u@example.com', 'p', 'shop@example.com', 'Belis', fn () => $c))->send('ama@example.test', 'Hi', "a\n.b");
    assert_same(0, $c->tls);
    assert_contains("\r\n..b", implode('', $c->sent));
    assert_true(!str_contains(implode('', $c->sent), 'STARTTLS'));
});

test('choosing smtp without full settings fails closed, and with them builds the SMTP sender', function (): void {
    settings_env();
    Settings::save(Db::fromEnv(), 'MAIL_DRIVER', 'smtp', 1);
    \Belis\Support\Mailer::useForTests(null);
    $threw = false;
    try {
        \Belis\Support\Mailer::fromEnv();
    } catch (RuntimeException) {
        $threw = true;
    }
    assert_true($threw);
    foreach (['SMTP_HOST' => 'mail.example.com', 'SMTP_USER' => 'u@example.com', 'SMTP_PASSWORD' => 'pw', 'MAIL_FROM_ADDRESS' => 'shop@example.com'] as $k => $v) {
        Settings::save(Db::fromEnv(), $k, $v, 1);
    }
    assert_true(\Belis\Support\Mailer::fromEnv() instanceof \Belis\Support\SmtpMailer);
    auth_done();
});
