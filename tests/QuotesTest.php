<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Domain\Accounts;
use Belis\Domain\Quotes;

// Quotes: customer requests, staff (sales role and owner) answer and price, customer accepts. Text only, own data only.

function quotes_env(): OutboxMailer
{
    $mail = roles_env();
    $pdo = Db::fromEnv()->pdo();
    $pdo->exec('CREATE TABLE quotes (id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT UNIQUE, user_id INTEGER, org_name TEXT, status TEXT DEFAULT "new", needed_by INTEGER, assigned_to INTEGER, accepted_offer_id INTEGER, accepted_at INTEGER, created_at INTEGER, updated_at INTEGER)');
    $pdo->exec('CREATE TABLE quote_items (id INTEGER PRIMARY KEY AUTOINCREMENT, quote_id INTEGER, name TEXT, qty INTEGER, note TEXT)');
    $pdo->exec('CREATE TABLE quote_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, quote_id INTEGER, author_id INTEGER, author_role TEXT, body TEXT, created_at INTEGER)');
    $pdo->exec('CREATE TABLE quote_offers (id INTEGER PRIMARY KEY AUTOINCREMENT, quote_id INTEGER, total_pesewas INTEGER, valid_until INTEGER, note TEXT, created_by INTEGER, created_at INTEGER)');
    $pdo->exec('CREATE TABLE quote_offer_lines (id INTEGER PRIMARY KEY AUTOINCREMENT, offer_id INTEGER, item_id INTEGER, name TEXT, qty INTEGER, unit_pesewas INTEGER)');
    return $mail;
}

function quote_form(array $over = []): array
{
    return $over + ['org_name' => 'Sample Bank', 'needed_by' => '', 'message' => 'Please quote for our branches.', 'item_name' => ['Toilet tissue', 'Hand soap 5 L', ''], 'item_qty' => ['200', '30', ''], 'item_note' => ['2 ply', '', '']];
}

/** Sign the customer in and send one request. */
function send_quote(OutboxMailer $mail, array $over = [], string $email = 'ama@example.test'): string
{
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, $email, GOOD_PW, '10.8.' . random_int(1, 200) . '.' . random_int(1, 200));
    $res = post('/quote/new', quote_form($over));
    assert_same(302, $res->status);
    return substr($res->headers['Location'], strlen('/quotes/'));
}

function sales_person(OutboxMailer $mail): void
{
    $_SESSION = [];
    Auth::reset();
    login_as($mail, 'sales');
}

function future(int $days): string
{
    return gmdate('Y-m-d', time() + $days * 86400);
}

test('signed-out visitors cannot reach any quote page, and customers cannot reach the admin ones', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $r = App::router();
    foreach (['/quote/new', '/quotes', '/quotes/QT-X', '/admin/quotes', '/admin/quotes/QT-X'] as $p) {
        assert_same(302, $r->dispatch(new Request('GET', $p))->status, $p);
    }
    assert_same(302, post('/quote/new', quote_form())->status);
    customer_login($mail);
    foreach (['/admin/quotes', '/admin/quotes/QT-X'] as $p) {
        assert_same('/admin/sign-in', $r->dispatch(new Request('GET', $p))->headers['Location'] ?? '', $p);
    }
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quotes')['n']);
    shop_done();
});

test('a customer sends a request: items, message and thread are stored, staff are emailed, status is new', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $n = count($mail->sent);
    $ref = send_quote($mail);
    $q = Db::fromEnv()->one('SELECT * FROM quotes WHERE ref = ?', [$ref]);
    assert_same(['new', 'Sample Bank'], [$q['status'], $q['org_name']]);
    assert_same(2, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_items')['n'], 'blank rows were stored');
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM quote_messages WHERE author_role = 'customer'")['n']);
    $to = array_column(array_slice($mail->sent, $n), 'to');
    assert_true(in_array('owner@example.test', $to, true) && in_array('sales@example.test', $to, true), 'owner and sales not emailed');
    assert_true(!in_array('fulfil@example.test', $to, true) && !in_array('staff@example.test', $to, true), 'other staff were emailed');
    $page = App::router()->dispatch(new Request('GET', '/quotes/' . $ref));
    assert_same(200, $page->status);
    assert_contains('Toilet tissue', $page->body);
    assert_contains('Please quote for our branches.', $page->body);
    assert_contains($ref, App::router()->dispatch(new Request('GET', '/quotes'))->body);
    shop_done();
});

test('a request is validated: no items, bad quantities, long text, bad dates', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    customer_login($mail);
    $bad = [
        ['item_name' => ['', ''], 'item_qty' => ['', ''], 'item_note' => ['', '']],
        ['item_name' => ['Soap'], 'item_qty' => ['0'], 'item_note' => ['']],
        ['item_name' => ['Soap'], 'item_qty' => ['-3'], 'item_note' => ['']],
        ['item_name' => ['Soap'], 'item_qty' => ['1.5'], 'item_note' => ['']],
        ['item_name' => ['Soap'], 'item_qty' => ['100001'], 'item_note' => ['']],
        ['item_name' => [''], 'item_qty' => ['5'], 'item_note' => ['']],
        ['item_name' => [str_repeat('x', 161)], 'item_qty' => ['5'], 'item_note' => ['']],
        ['item_name' => ['Soap'], 'item_qty' => ['5'], 'item_note' => [str_repeat('x', 201)]],
        ['message' => str_repeat('x', 2001)],
        ['org_name' => str_repeat('x', 121)],
        ['needed_by' => '2020-01-01'],
        ['needed_by' => '2099-01-01'],
        ['needed_by' => '15/12/2026'],
        ['needed_by' => '2026-02-31'],
    ];
    foreach ($bad as $i => $over) {
        assert_same(422, post('/quote/new', quote_form($over))->status, 'case ' . $i);
    }
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quotes')['n']);
    $tooMany = quote_form(['item_name' => array_fill(0, 21, 'Item'), 'item_qty' => array_fill(0, 21, '1'), 'item_note' => array_fill(0, 21, '')]);
    assert_same(422, post('/quote/new', $tooMany)->status);
    assert_same(302, post('/quote/new', quote_form(['needed_by' => future(30)]))->status);
    shop_done();
});

test('quote requests are rate limited per customer', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    customer_login($mail);
    $codes = [];
    for ($i = 0; $i < 7; $i++) {
        $codes[] = post('/quote/new', quote_form())->status;
    }
    assert_same([302, 302, 302, 302, 302, 422, 422], $codes);
    assert_same(5, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quotes')['n']);
    shop_done();
});

test('customers see only their own quotes; another customer gets a 404 and cannot reply or answer', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    add_customer($mail, 'kofi@example.test');
    $ref = send_quote($mail);
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, 'kofi@example.test', GOOD_PW, '10.8.250.1');
    $r = App::router();
    assert_same(404, $r->dispatch(new Request('GET', '/quotes/' . $ref))->status);
    assert_same(404, post('/quotes/' . $ref . '/reply', ['body' => 'hello'])->status);
    assert_same(404, post('/quotes/' . $ref . '/answer', ['choice' => 'accept'])->status);
    assert_true(!str_contains($r->dispatch(new Request('GET', '/quotes'))->body, $ref), "another customer's quote was listed");
    assert_same(1, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_messages')['n']);
    shop_done();
});

test('the sales role and the owner can see and answer quotes; other roles cannot', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    foreach (['staff' => 403, 'fulfil' => 403, 'norole' => 403, 'sales' => 200, 'owner' => 200] as $who => $code) {
        $_SESSION = [];
        Auth::reset();
        login_as($mail, $who);
        assert_same($code, App::router()->dispatch(new Request('GET', '/admin/quotes'))->status, $who . ' list');
        assert_same($code, App::router()->dispatch(new Request('GET', '/admin/quotes/' . $ref))->status, $who . ' detail');
        $res = post('/admin/quotes/' . $ref . '/reply', ['body' => 'hi from ' . $who]);
        assert_same($code === 200 ? 302 : 403, $res->status, $who . ' reply');
    }
    assert_same(2, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM quote_messages WHERE author_role = 'staff'")['n']);
    shop_done();
});

test('opening a quote and every staff action is audited', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    sales_person($mail);
    App::router()->dispatch(new Request('GET', '/admin/quotes/' . $ref));
    post('/admin/quotes/' . $ref . '/status', ['status' => 'in_progress']);
    post('/admin/quotes/' . $ref . '/reply', ['body' => 'Working on it']);
    foreach (['quote.view', 'quote.status', 'quote.reply'] as $a) {
        assert_same(1, count_rows("action = '" . $a . "' AND target = '" . $ref . "'"), $a);
    }
    assert_same(0, count_rows("action = 'quote.view' AND target = 'QT-NOPE'"));
    assert_same(404, App::router()->dispatch(new Request('GET', '/admin/quotes/QT-NOPE'))->status);
    shop_done();
});

test('a staff reply moves a new quote to in progress, emails the customer and shows in the thread; message limits apply', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    sales_person($mail);
    $n = count($mail->sent);
    post('/admin/quotes/' . $ref . '/reply', ['body' => 'Can you tell us the delivery address?']);
    assert_same('in_progress', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status']);
    assert_same('ama@example.test', $mail->sent[$n]['to']);
    assert_true(!str_contains($mail->sent[$n]['body'], 'delivery address'), 'the message text was emailed');
    post('/admin/quotes/' . $ref . '/reply', ['body' => '']);
    post('/admin/quotes/' . $ref . '/reply', ['body' => str_repeat('x', 2001)]);
    assert_same(1, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM quote_messages WHERE author_role = 'staff'")['n']);
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.8.240.1');
    assert_contains('Can you tell us the delivery address?', App::router()->dispatch(new Request('GET', '/quotes/' . $ref))->body);
    post('/quotes/' . $ref . '/reply', ['body' => '<b>Osu, Accra</b>']);
    $page = App::router()->dispatch(new Request('GET', '/quotes/' . $ref));
    assert_contains('&lt;b&gt;Osu, Accra&lt;/b&gt;', $page->body);
    assert_true(!str_contains($page->body, '<b>Osu'), 'a message was not escaped');
    shop_done();
});

test('staff send a priced offer: every item needs a price, the total is worked out on the server, and the customer is emailed', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    $items = Db::fromEnv()->all('SELECT id FROM quote_items ORDER BY id');
    sales_person($mail);
    $bad = [
        ['unit' => [$items[0]['id'] => '10.00'], 'valid_until' => future(10)],
        ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => 'abc'], 'valid_until' => future(10)],
        ['unit' => [$items[0]['id'] => '0', $items[1]['id'] => '5'], 'valid_until' => future(10)],
        ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.00'], 'valid_until' => '2020-01-01'],
        ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.00'], 'valid_until' => future(200)],
        ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.00'], 'valid_until' => 'soon'],
        ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.00'], 'valid_until' => future(10), 'note' => str_repeat('x', 501)],
    ];
    foreach ($bad as $i => $form) {
        post('/admin/quotes/' . $ref . '/offer', $form);
        assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_offers')['n'], 'case ' . $i . ' made an offer');
    }
    $n = count($mail->sent);
    post('/admin/quotes/' . $ref . '/offer', ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.50'], 'valid_until' => future(10), 'note' => 'Delivery included', 'total' => '1', 'total_pesewas' => '1']);
    $o = Db::fromEnv()->one('SELECT total_pesewas, note FROM quote_offers');
    assert_same(200 * 1000 + 30 * 550, (int) $o['total_pesewas']);
    assert_same('quoted', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status']);
    assert_same(2, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_offer_lines')['n']);
    assert_same('ama@example.test', $mail->sent[$n]['to']);
    assert_same(1, count_rows("action = 'quote.offer'"));
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.8.230.1');
    $page = App::router()->dispatch(new Request('GET', '/quotes/' . $ref));
    assert_contains('Our quote', $page->body);
    assert_contains('Delivery included', $page->body);
    assert_contains('Accept this quote', $page->body);
    shop_done();
});

test('the customer accepts or declines only an open, unexpired offer, once', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    $items = Db::fromEnv()->all('SELECT id FROM quote_items ORDER BY id');
    sales_person($mail);
    post('/admin/quotes/' . $ref . '/offer', ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.50'], 'valid_until' => future(10)]);
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.8.220.1');
    post('/quotes/' . $ref . '/answer', ['choice' => 'maybe']);
    assert_same('quoted', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status']);
    $n = count($mail->sent);
    post('/quotes/' . $ref . '/answer', ['choice' => 'accept']);
    $q = Db::fromEnv()->one('SELECT status, accepted_offer_id, accepted_at FROM quotes WHERE ref = ?', [$ref]);
    assert_same('accepted', $q['status']);
    assert_true($q['accepted_offer_id'] !== null && $q['accepted_at'] !== null);
    assert_true(count($mail->sent) > $n, 'staff not told');
    post('/quotes/' . $ref . '/answer', ['choice' => 'decline']);
    assert_same('accepted', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status'], 'an answered quote changed its answer');
    shop_done();
});

test('an expired offer cannot be accepted', function (): void {
    $mail = quotes_env();
    $uid = add_customer($mail);
    $ref = send_quote($mail);
    $items = Db::fromEnv()->all('SELECT id FROM quote_items ORDER BY id');
    sales_person($mail);
    post('/admin/quotes/' . $ref . '/offer', ['unit' => [$items[0]['id'] => '10.00', $items[1]['id'] => '5.50'], 'valid_until' => future(10)]);
    Db::fromEnv()->run('UPDATE quote_offers SET valid_until = ?', [time() - 10]);
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.8.210.1');
    $page = App::router()->dispatch(new Request('GET', '/quotes/' . $ref));
    assert_true(!str_contains($page->body, 'Accept this quote'), 'an expired offer can be accepted');
    post('/quotes/' . $ref . '/answer', ['choice' => 'accept']);
    assert_same('quoted', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status']);
    shop_done();
});

test('staff cannot set sent, accepted or declined by hand, and a closed quote takes no messages or offers', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    $items = Db::fromEnv()->all('SELECT id FROM quote_items ORDER BY id');
    sales_person($mail);
    foreach (['quoted', 'accepted', 'declined', 'new', 'bogus'] as $bad) {
        post('/admin/quotes/' . $ref . '/status', ['status' => $bad]);
        assert_same('new', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status'], $bad);
    }
    post('/admin/quotes/' . $ref . '/status', ['status' => 'closed']);
    assert_same('closed', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status']);
    post('/admin/quotes/' . $ref . '/status', ['status' => 'in_progress']);
    assert_same('closed', Db::fromEnv()->one('SELECT status FROM quotes WHERE ref = ?', [$ref])['status'], 'a closed quote was reopened');
    $msgs = (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_messages')['n'];
    post('/admin/quotes/' . $ref . '/reply', ['body' => 'late reply']);
    post('/admin/quotes/' . $ref . '/offer', ['unit' => [$items[0]['id'] => '1', $items[1]['id'] => '1'], 'valid_until' => future(5)]);
    assert_same(0, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_offers')['n']);
    $_SESSION = [];
    Auth::reset();
    customer_login($mail, 'ama@example.test', GOOD_PW, '10.8.200.1');
    post('/quotes/' . $ref . '/reply', ['body' => 'hello?']);
    assert_same($msgs, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quote_messages')['n'], 'a message got through on a closed quote');
    shop_done();
});

test('the admin list filters by status and text, treats text as text, and pages', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $a = send_quote($mail, ['org_name' => 'Alpha School']);
    send_quote($mail, ['org_name' => '100% Real_Org']);
    sales_person($mail);
    post('/admin/quotes/' . $a . '/status', ['status' => 'in_progress']);
    $r = App::router();
    assert_contains('2 requests', $r->dispatch(new Request('GET', '/admin/quotes'))->body);
    assert_contains('1 request.', $r->dispatch(new Request('GET', '/admin/quotes', ['status' => 'in_progress']))->body);
    assert_contains('1 request.', $r->dispatch(new Request('GET', '/admin/quotes', ['q' => 'alpha']))->body);
    assert_contains('1 request.', $r->dispatch(new Request('GET', '/admin/quotes', ['q' => '100%']))->body);
    assert_contains('0 requests', $r->dispatch(new Request('GET', '/admin/quotes', ['q' => "x' OR '1'='1"]))->body);
    assert_contains('2 requests', $r->dispatch(new Request('GET', '/admin/quotes', ['status' => 'bogus']))->body);
    shop_done();
});

test('quote forms need a CSRF token and the preview people cannot send or change anything', function (): void {
    $mail = quotes_env();
    add_customer($mail);
    $ref = send_quote($mail);
    foreach (['/quote/new', '/quotes/' . $ref . '/reply', '/quotes/' . $ref . '/answer'] as $p) {
        assert_same(419, App::router()->dispatch(new Request('POST', $p, [], ['_csrf' => 'bad']))->status, $p);
    }
    sales_person($mail);
    foreach (['reply', 'status', 'offer'] as $p) {
        assert_same(419, App::router()->dispatch(new Request('POST', '/admin/quotes/' . $ref . '/' . $p, [], ['_csrf' => 'bad']))->status, $p);
    }
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'owner', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    $_SESSION = [];
    Auth::reset();
    $before = (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quotes')['n'];
    assert_same(302, post('/quote/new', quote_form())->status);
    assert_same(302, post('/admin/quotes/' . $ref . '/reply', ['body' => 'x'])->status);
    assert_same($before, (int) Db::fromEnv()->one('SELECT COUNT(*) AS n FROM quotes')['n']);
    assert_same(200, App::router()->dispatch(new Request('GET', '/admin/quotes'))->status);
    assert_same(404, App::router()->dispatch(new Request('GET', '/admin/quotes/' . $ref))->status, 'the preview person read a real customer quote');
    shop_done();
});

test('quote messages and offers cannot be edited or deleted, and no file upload exists', function (): void {
    $mig = (string) file_get_contents(BASE_PATH . '/database/migrations/014_quotes.sql');
    foreach (['BEFORE UPDATE ON quote_messages', 'BEFORE DELETE ON quote_messages', 'BEFORE UPDATE ON quote_offers', 'BEFORE DELETE ON quote_offers'] as $t) {
        assert_contains($t, $mig);
    }
    $src = (string) file_get_contents(BASE_PATH . '/src/Domain/Quotes.php');
    assert_true(preg_match('/(UPDATE|DELETE FROM) quote_(messages|offers|offer_lines)/i', $src) !== 1, 'code changes messages or offers');
    foreach (['quote/new', 'admin/quote'] as $t) {
        $html = (string) file_get_contents(BASE_PATH . '/templates/pages/' . $t . '.php');
        assert_true(!str_contains($html, 'type="file"') && !str_contains($html, 'multipart'), $t . ' has a file upload');
    }
});
