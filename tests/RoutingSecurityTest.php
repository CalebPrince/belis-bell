<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Csrf;
use Belis\Core\Env;
use Belis\Core\Policy;
use Belis\Core\Request;
use Belis\Core\Router;

// CTL-FW-001, CTL-AUTHZ-001, CTL-SESS-001: routes are deny-by-default and CSRF-checked.

test('every route declares a known policy', function (): void {
    $inventory = App::router()->inventory();
    assert_true($inventory !== [], 'route table is empty');
    foreach ($inventory as $r) {
        assert_true(in_array($r['policy'], Policy::KNOWN, true), "{$r['method']} {$r['path']} has unknown policy {$r['policy']}");
    }
});

test('every unsafe route is CSRF-checked or has a written exemption reason', function (): void {
    foreach (App::router()->inventory() as $r) {
        $unsafe = !in_array($r['method'], ['GET', 'HEAD'], true);
        if ($unsafe) {
            assert_true($r['csrf'] || (is_string($r['csrfExemptReason']) && trim($r['csrfExemptReason']) !== ''), "{$r['method']} {$r['path']} has no CSRF rule");
        }
    }
});

test('a route without a policy cannot be registered', function (): void {
    $threw = false;
    try {
        (new Router())->add('GET', '/x', static fn () => null, '');
    } catch (LogicException) {
        $threw = true;
    }
    assert_true($threw, 'empty policy was accepted');
});

test('a CSRF exemption without a reason is rejected', function (): void {
    $threw = false;
    try {
        (new Router())->add('POST', '/x', static fn () => null, 'public', '  ');
    } catch (LogicException) {
        $threw = true;
    }
    assert_true($threw, 'blank exemption reason was accepted');
});

test('an unknown policy name is forbidden, not allowed', function (): void {
    assert_same(403, Policy::evaluate('does-not-exist', new Request('GET', '/')));
});

test('signed-in policies deny while accounts are not built', function (): void {
    foreach (['customer', 'staff', 'owner'] as $p) {
        assert_same(401, Policy::evaluate($p, new Request('GET', '/')), $p);
    }
});

test('POST without a CSRF token is rejected with 419', function (): void {
    $router = new Router();
    $router->add('POST', '/thing', static fn () => new Belis\Core\Response(200, 'done'), 'public');
    $res = $router->dispatch(new Request('POST', '/thing'));
    assert_same(419, $res->status);
});

test('POST with a wrong CSRF token is rejected, with the right token accepted', function (): void {
    Env::fake(['APP_URL' => 'http://localhost']);
    $router = new Router();
    $router->add('POST', '/thing', static fn () => new Belis\Core\Response(200, 'done'), 'public');
    $token = Csrf::token();
    assert_same(419, $router->dispatch(new Request('POST', '/thing', [], ['_csrf' => 'wrong']))->status);
    assert_same(200, $router->dispatch(new Request('POST', '/thing', [], ['_csrf' => $token]))->status);
});

test('unknown paths are 404 and wrong methods are 405', function (): void {
    Env::fake(['APP_URL' => 'http://localhost']);
    $router = App::router();
    assert_same(404, $router->dispatch(new Request('GET', '/nope'))->status);
    assert_same(405, $router->dispatch(new Request('POST', '/health'))->status);
});

test('redirects only go to same-site paths', function (): void {
    assert_same('/', Belis\Core\Response::redirect('https://evil.example/x')->headers['Location']);
    assert_same('/', Belis\Core\Response::redirect('//evil.example/x')->headers['Location']);
    assert_same('/account', Belis\Core\Response::redirect('/account')->headers['Location']);
});
