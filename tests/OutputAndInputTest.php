<?php
declare(strict_types=1);

use Belis\Core\SecurityHeaders;
use Belis\Core\View;
use Belis\Payments\PaystackWebhook;
use Belis\Support\Logger;
use Belis\Support\Validator;

// THR-007 (XSS), THR-018 (third-party script), CTL-INP-001, CTL-PAY-002, CTL-DATA-001.

test('e() escapes markup and quotes', function (): void {
    assert_same('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
    assert_same('&quot;x&quot; &amp; &#039;y&#039;', e('"x" & \'y\''));
});

test('a rendered page escapes hostile product data', function (): void {
    $html = View::render('pages/home', [
        'title' => '<b>t</b>',
        'trust' => [], 'why' => [], 'audiences' => [],
        'categories' => [['name' => '<img src=x onerror=alert(1)>', 'blurb' => '"><script>x</script>', 'slug' => 's']],
        'products' => [['name' => '<script>alert(1)</script>', 'pack_size' => '5"L', 'price_pesewas' => 1000, 'stock_status' => 'in_stock', 'slug' => 'p', 'category' => 'c']],
    ]);
    assert_not_contains('<script>alert(1)</script>', $html);
    assert_not_contains('<img src=x', $html);
    assert_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
});

test('templates print data only through the approved helpers', function (): void {
    foreach (files_under(BASE_PATH . '/templates', ['php']) as $file) {
        $code = (string) file_get_contents($file);
        preg_match_all('/<\?=\s*(.{0,40})/s', $code, $m);
        foreach ($m[1] as $expr) {
            $ok = preg_match('/^(e|asset|csrf_field|flag|image_html|icon)\(/', $expr) === 1
                || (str_ends_with($file, 'templates/layout.php') && str_starts_with($expr, 'raw($content)'));
            assert_true($ok, basename($file) . ' prints unescaped output: <?= ' . trim($expr));
        }
    }
});

test('templates contain no inline script, style or event handlers', function (): void {
    foreach (files_under(BASE_PATH . '/templates', ['php']) as $file) {
        $code = strtolower((string) file_get_contents($file));
        assert_true(preg_match('/<script(?![^>]*\ssrc=)/', $code) !== 1 && !str_contains($code, '<style'), basename($file) . ' has an inline script or style block');
        assert_true(preg_match('/\son[a-z]+\s*=/', $code) !== 1, basename($file) . ' has an inline event handler');
        assert_true(preg_match('/\sstyle\s*=/', $code) !== 1, basename($file) . ' has an inline style attribute');
    }
});

test('SQL is never built from variables', function (): void {
    $bad = [];
    foreach (array_merge(files_under(BASE_PATH . '/src', ['php']), files_under(BASE_PATH . '/bin', ['php'])) as $file) {
        $code = (string) file_get_contents($file);
        // A double-quoted SQL string containing $variable or {$...}, or SQL literal concatenated with a variable.
        if (preg_match('/"[^"\n]*\b(SELECT|INSERT|UPDATE|DELETE)\b[^"\n]*(\$\w|\{\$)[^"\n]*"/i', $code) === 1
            || preg_match('/\'[^\'\n]*\b(SELECT|INSERT|UPDATE|DELETE)\b[^\'\n]*\'\s*\.\s*\$/i', $code) === 1) {
            $bad[] = $file;
        }
    }
    assert_same([], $bad, 'interpolated SQL found in');
});

test('the CSP has no unsafe directives and no third-party origins', function (): void {
    $csp = SecurityHeaders::CSP;
    assert_contains("default-src 'self'", $csp);
    assert_contains("frame-ancestors 'none'", $csp);
    assert_contains("object-src 'none'", $csp);
    assert_not_contains('unsafe-inline', $csp);
    assert_not_contains('unsafe-eval', $csp);
    assert_not_contains('http', $csp);
});

test('security headers include HSTS only over https', function (): void {
    assert_true(!isset(SecurityHeaders::all(false)['Strict-Transport-Security']));
    assert_true(isset(SecurityHeaders::all(true)['Strict-Transport-Security']));
    assert_same('nosniff', SecurityHeaders::all(false)['X-Content-Type-Options']);
});

test('Paystack signature: valid passes, tampered, missing and empty-secret fail', function (): void {
    $body = '{"event":"charge.success","data":{"reference":"r1","amount":1000}}';
    $secret = 'sk_test_example';
    $sig = hash_hmac('sha512', $body, $secret);
    assert_true(PaystackWebhook::verifySignature($body, $sig, $secret));
    assert_true(!PaystackWebhook::verifySignature($body . ' ', $sig, $secret), 'tampered body accepted');
    assert_true(!PaystackWebhook::verifySignature($body, null, $secret), 'missing signature accepted');
    assert_true(!PaystackWebhook::verifySignature($body, 'deadbeef', $secret), 'wrong signature accepted');
    assert_true(!PaystackWebhook::verifySignature($body, $sig, ''), 'empty secret accepted');
});

test('money is formatted from whole pesewas without floats', function (): void {
    assert_same('GH₵ 185.00', money(18500));
    assert_same('GH₵ 0.05', money(5));
    assert_same('GH₵ 1,234.56', money(123456));
});

test('validator rules', function (): void {
    $errors = Validator::check(['email' => 'nope', 'qty' => 'x', 'name' => str_repeat('a', 200)], [
        'email' => 'required|email', 'qty' => 'required|int', 'name' => 'required|max:120', 'phone' => 'required',
    ]);
    assert_same(['email', 'qty', 'name', 'phone'], array_keys($errors));
    assert_same([], Validator::check(['email' => 'a@b.co', 'qty' => '3'], ['email' => 'required|email', 'qty' => 'required|int']));
});

test('logs redact emails and phone numbers', function (): void {
    $out = Logger::redact('Order for ama@example.com call +233 24 123 4567 now');
    assert_not_contains('ama@example.com', $out);
    assert_not_contains('123 4567', $out);
});
