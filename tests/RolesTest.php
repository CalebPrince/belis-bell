<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Domain\Accounts;

// CTL-AUTHZ-002, THR-025: each staff role reaches only its own pages, on the server.

function roles_env(): OutboxMailer
{
    $mail = gaps_env();
    $a = new Accounts(Db::fromEnv(), $mail);
    $a->createVerified('fulfil@example.test', 'Fola Fulfil', 'n/a', GOOD_PW, 'staff', 'fulfilment');
    $a->createVerified('sales@example.test', 'Sam Sales', 'n/a', GOOD_PW, 'staff', 'sales');
    $a->createVerified('norole@example.test', 'Nora None', 'n/a', GOOD_PW, 'staff', null);
    return $mail;
}

/** Sign in as one of the seeded people with a clean session. */
function as_person(OutboxMailer $mail, string $who): void
{
    $_SESSION = [];
    Auth::reset();
    post('/admin/sign-in', ['email' => $who . '@example.test', 'password' => GOOD_PW], '10.7.0.' . random_int(1, 250));
    post('/admin/verify', ['code' => $mail->lastCode()], '10.7.0.' . random_int(1, 250));
    Auth::reset();
}

function reach(string $method, string $path): int
{
    $req = $method === 'GET' ? new Request('GET', $path) : new Request('POST', $path, [], ['_csrf' => Belis\Core\Csrf::token(), 'fulfilment' => 'packed', 'name' => 'x', 'staff_role' => 'sales']);
    $res = App::router()->dispatch($req);
    // A redirect back to the same area (for example after saving) counts as reached; a redirect to sign-in does not.
    return $res->status === 302 && !str_contains($res->headers['Location'] ?? '', 'sign-in') ? 200 : $res->status;
}

/** @return array<string,array{0:string,1:string}> */
function admin_areas(): array
{
    return [
        'products' => ['GET', '/admin/products'], 'product' => ['GET', '/admin/products/1'], 'categories' => ['GET', '/admin/categories'],
        'orders' => ['GET', '/admin/orders'], 'fulfilment' => ['POST', '/admin/orders/BB-X/fulfilment'],
        'staff' => ['GET', '/admin/staff'], 'audit' => ['GET', '/admin/audit'], 'settings' => ['GET', '/admin/settings'], 'new product' => ['GET', '/admin/products/new'],
    ];
}

/** True when the page was reached (anything but a refusal or sign-in redirect). */
function allowed(int $status): bool
{
    return !in_array($status, [302, 401, 403], true);
}

test('the content role reaches products and categories and nothing else', function (): void {
    $mail = roles_env();
    as_person($mail, 'staff');
    $expect = ['products' => true, 'product' => true, 'categories' => true, 'orders' => false, 'fulfilment' => false, 'staff' => false, 'audit' => false, 'settings' => false, 'new product' => false];
    foreach (admin_areas() as $name => [$m, $p]) {
        assert_same($expect[$name], allowed(reach($m, $p)), 'content role on ' . $name);
    }
    assert_same(200, reach('GET', '/admin'));
    shop_done();
});

test('the fulfilment role reaches orders and delivery progress and nothing else', function (): void {
    $mail = roles_env();
    as_person($mail, 'fulfil');
    $expect = ['products' => false, 'product' => false, 'categories' => false, 'orders' => true, 'fulfilment' => true, 'staff' => false, 'audit' => false, 'settings' => false, 'new product' => false];
    foreach (admin_areas() as $name => [$m, $p]) {
        assert_same($expect[$name], allowed(reach($m, $p)), 'fulfilment role on ' . $name);
    }
    shop_done();
});

test('the sales role can see orders but cannot move them, and reaches nothing else', function (): void {
    $mail = roles_env();
    as_person($mail, 'sales');
    $expect = ['products' => false, 'product' => false, 'categories' => false, 'orders' => true, 'fulfilment' => false, 'staff' => false, 'audit' => false, 'settings' => false, 'new product' => false];
    foreach (admin_areas() as $name => [$m, $p]) {
        assert_same($expect[$name], allowed(reach($m, $p)), 'sales role on ' . $name);
    }
    shop_done();
});

test('a staff account with no role reaches only the dashboard and says so', function (): void {
    $mail = roles_env();
    as_person($mail, 'norole');
    foreach (admin_areas() as $name => [$m, $p]) {
        assert_same(false, allowed(reach($m, $p)), 'no role on ' . $name);
    }
    $page = App::router()->dispatch(new Request('GET', '/admin'));
    assert_same(200, $page->status);
    assert_contains('no role yet', $page->body);
    assert_true(!str_contains($page->body, '/admin/orders') && !str_contains($page->body, '/admin/products'), 'links shown to an account with no role');
    shop_done();
});

test('the owner reaches every area', function (): void {
    $mail = roles_env();
    as_person($mail, 'owner');
    foreach (admin_areas() as $name => [$m, $p]) {
        assert_same(true, allowed(reach($m, $p)), 'owner on ' . $name);
    }
    shop_done();
});

test('a role only sees its own links and figures on the dashboard', function (): void {
    $mail = roles_env();
    make_order(3, 'paid');
    as_person($mail, 'staff');
    $c = App::router()->dispatch(new Request('GET', '/admin'));
    assert_contains('/admin/products', $c->body);
    assert_true(!str_contains($c->body, '/admin/orders'), 'content role shown orders');
    assert_true(!str_contains($c->body, 'Recent orders') && !str_contains($c->body, 'To pack'), 'content role shown order figures');
    as_person($mail, 'fulfil');
    $f = App::router()->dispatch(new Request('GET', '/admin'));
    assert_contains('/admin/orders', $f->body);
    assert_contains('Recent orders', $f->body);
    assert_true(!str_contains($f->body, '/admin/products'), 'fulfilment role shown products');
    shop_done();
});

test('changing a role needs a fresh code, is audited, takes effect at once and ends the old session', function (): void {
    $mail = roles_env();
    $fulfil = (int) Db::fromEnv()->one("SELECT id FROM users WHERE email = 'fulfil@example.test'")['id'];
    as_person($mail, 'fulfil');
    $oldSession = $_SESSION;
    assert_same(200, reach('GET', '/admin/orders'));
    as_person($mail, 'owner');
    $res = post('/admin/staff/' . $fulfil . '/role', ['staff_role' => 'content']);
    assert_contains('/admin/confirm', $res->headers['Location'] ?? '');
    assert_same('fulfilment', Db::fromEnv()->one('SELECT staff_role FROM users WHERE id = ?', [$fulfil])['staff_role'], 'changed without the code');
    post('/admin/confirm/code', ['next' => '/admin/staff'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/staff', 'code' => $mail->lastCode()], '10.4.0.1');
    assert_same(302, post('/admin/staff/' . $fulfil . '/role', ['staff_role' => 'content'])->status);
    assert_same('content', Db::fromEnv()->one('SELECT staff_role FROM users WHERE id = ?', [$fulfil])['staff_role']);
    assert_same('role set to content', Db::fromEnv()->one("SELECT detail FROM audit_log WHERE action = 'staff.role'")['detail']);
    $ownerSession = $_SESSION;
    $_SESSION = $oldSession;
    Auth::reset();
    assert_same(null, Auth::staff(), 'the old session survived a role change');
    $_SESSION = [];
    as_person($mail, 'fulfil');
    assert_same(false, allowed(reach('GET', '/admin/orders')), 'the old role still works after sign-in');
    assert_same(true, allowed(reach('GET', '/admin/products')));
    shop_done();
});

test('a role cannot be set to anything else, on owners, customers or by staff', function (): void {
    $mail = roles_env();
    $cust = add_customer($mail, 'buyer@example.test');
    $owner = (int) Db::fromEnv()->one("SELECT id FROM users WHERE role = 'owner'")['id'];
    $sales = (int) Db::fromEnv()->one("SELECT id FROM users WHERE email = 'sales@example.test'")['id'];
    as_person($mail, 'owner');
    post('/admin/confirm/code', ['next' => '/admin/staff'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/staff', 'code' => $mail->lastCode()], '10.4.0.1');
    post('/admin/staff/' . $sales . '/role', ['staff_role' => 'owner']);
    post('/admin/staff/' . $sales . '/role', ['staff_role' => "content'; DROP TABLE users;--"]);
    post('/admin/staff/' . $sales . '/role', ['staff_role' => '']);
    assert_same('sales', Db::fromEnv()->one('SELECT staff_role FROM users WHERE id = ?', [$sales])['staff_role']);
    post('/admin/staff/' . $cust . '/role', ['staff_role' => 'content']);
    post('/admin/staff/' . $owner . '/role', ['staff_role' => 'content']);
    assert_same(null, Db::fromEnv()->one('SELECT staff_role FROM users WHERE id = ?', [$cust])['staff_role']);
    assert_same(null, Db::fromEnv()->one('SELECT staff_role FROM users WHERE id = ?', [$owner])['staff_role']);
    as_person($mail, 'sales');
    assert_same(403, post('/admin/staff/' . $sales . '/role', ['staff_role' => 'content'])->status, 'staff changed their own role');
    assert_same('sales', Db::fromEnv()->one('SELECT staff_role FROM users WHERE id = ?', [$sales])['staff_role']);
    shop_done();
});

test('adding staff needs a role, and the new person only reaches that role\'s pages', function (): void {
    $mail = roles_env();
    as_person($mail, 'owner');
    post('/admin/confirm/code', ['next' => '/admin/staff'], '10.4.0.1');
    post('/admin/confirm', ['next' => '/admin/staff', 'code' => $mail->lastCode()], '10.4.0.1');
    assert_same(422, post('/admin/staff', ['name' => 'No Role', 'email' => 'nr@example.test'])->status);
    assert_same(422, post('/admin/staff', ['name' => 'Bad Role', 'email' => 'br@example.test', 'staff_role' => 'owner'])->status);
    assert_same(0, (int) Db::fromEnv()->one("SELECT COUNT(*) AS n FROM users WHERE email IN ('nr@example.test', 'br@example.test')")['n']);
    assert_same(302, post('/admin/staff', ['name' => 'Sales Two', 'email' => 'sales2@example.test', 'staff_role' => 'sales'])->status);
    assert_same('sales', Db::fromEnv()->one("SELECT staff_role FROM users WHERE email = 'sales2@example.test'")['staff_role']);
    shop_done();
});

test('the local preview staff and owner can look at every area but change nothing', function (): void {
    roles_env();
    Env::fake(['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => 'staff', 'AUTH_PEPPER' => 'test-pepper-with-at-least-32-characters', 'SETTINGS_KEY' => KEY_A]);
    $_SESSION = [];
    Auth::reset();
    foreach (['products', 'orders', 'categories'] as $k) {
        assert_same(true, allowed(reach(...admin_areas()[$k])), 'preview on ' . $k);
    }
    shop_done();
});

test('every admin route has a policy, and the staff-only areas are never left as plain staff', function (): void {
    foreach (App::router()->inventory() as $r) {
        if (!str_starts_with($r['path'], '/admin') || in_array($r['path'], ['/admin/sign-in', '/admin/verify', '/admin/resend', '/admin/forgot', '/admin/reset', '/admin/sign-out'], true)) {
            continue;
        }
        if (in_array($r['path'], ['/admin', '/admin/confirm', '/admin/confirm/code'], true)) {
            assert_same('staff', $r['policy'], $r['path']);
            continue;
        }
        assert_true(in_array($r['policy'], ['content', 'orders', 'fulfilment', 'owner'], true), $r['method'] . ' ' . $r['path'] . ' has policy ' . $r['policy']);
    }
});
