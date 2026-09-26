<?php
declare(strict_types=1);

use Belis\Core\App;
use Belis\Core\Env;
use Belis\Core\Request;

// PG-053, PG-055 to PG-060, CTL-AUTHZ-001: signed-in pages are closed unless the local preview is on.

function local(array $over = []): array
{
    return $over + ['APP_ENV' => 'local', 'MOCK_DATA' => '1', 'PREVIEW_LOGIN' => ''];
}

test('signed-in pages send a visitor to sign in, and staff pages to the staff sign in', function (): void {
    Env::fake(local());
    $r = App::router();
    foreach (['/account', '/checkout', '/order/BB-10482'] as $path) {
        $res = $r->dispatch(new Request('GET', $path));
        assert_same(302, $res->status, $path);
        assert_same('/account/sign-in', $res->headers['Location'] ?? '', $path);
    }
    $res = $r->dispatch(new Request('GET', '/admin'));
    assert_same(302, $res->status);
    assert_same('/admin/sign-in', $res->headers['Location'] ?? '');
});

test('sign-in and register pages are public forms with a CSRF token; the code page needs step one first', function (): void {
    Env::fake(local());
    foreach (['/account/sign-in', '/account/register', '/admin/sign-in'] as $path) {
        $res = App::router()->dispatch(new Request('GET', $path));
        assert_same(200, $res->status, $path);
        assert_true(str_contains($res->body, 'method="post"') && str_contains($res->body, 'name="_csrf" value="'), $path);
        assert_true(!str_contains($res->body, 'name="_csrf" value=""'), $path);
    }
    foreach (['/account/verify', '/admin/verify'] as $path) {
        assert_same(302, App::router()->dispatch(new Request('GET', $path))->status, $path);
    }
});

test('a customer preview opens the account and order pages but not the admin', function (): void {
    Env::fake(local(['PREVIEW_LOGIN' => 'customer']));
    $r = App::router();
    assert_same(200, $r->dispatch(new Request('GET', '/account'))->status);
    assert_same(200, $r->dispatch(new Request('GET', '/order/BB-10482'))->status);
    assert_same(404, $r->dispatch(new Request('GET', '/order/BB-99999'))->status);
    assert_same(302, $r->dispatch(new Request('GET', '/admin'))->status);
});

test('the order page shows each of the four states and ignores an unknown one', function (): void {
    Env::fake(local(['PREVIEW_LOGIN' => 'customer']));
    $r = App::router();
    $expect = ['paid' => 'order is confirmed', 'pending' => 'waiting for your payment', 'failed' => 'did not go through', 'cancelled' => 'was cancelled'];
    foreach ($expect as $state => $text) {
        $res = $r->dispatch(new Request('GET', '/order/BB-10482', ['status' => $state]));
        assert_true(str_contains($res->body, $text), $state);
    }
    $res = $r->dispatch(new Request('GET', '/order/BB-10482', ['status' => '<script>']));
    assert_true(str_contains($res->body, 'order is confirmed') && !str_contains($res->body, '<script>'));
});

test('a staff preview opens the admin dashboard', function (): void {
    Env::fake(local(['PREVIEW_LOGIN' => 'staff']));
    $res = App::router()->dispatch(new Request('GET', '/admin'));
    assert_same(200, $res->status);
    assert_true(str_contains($res->body, 'Recent orders'));
});

test('no sample account data exists without the preview', function (): void {
    Env::fake(local());
    assert_same([], Belis\Support\PreviewData::orders());
    assert_same([], Belis\Support\PreviewData::adminStats());
    assert_same(null, Belis\Support\PreviewData::order('BB-10482'));
});
