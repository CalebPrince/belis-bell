<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Addresses;
use Belis\Domain\Cart;
use Belis\Domain\Catalogue;
use Belis\Domain\Orders;
use Belis\Payments\Payments;
use Belis\Support\Logger;
use Belis\Support\PreviewData;
use Belis\Support\Site;

/**
 * Customer sign-in, register, checkout, order confirmation and account pages. Sign-in, accounts, orders and
 * Paystack are NOT BUILT: the public forms are shown but cannot submit, and the signed-in pages only open in
 * the local preview (see Auth). No form here posts anywhere.
 */
final class AccountController
{
    /** @param array<string,string> $params */
    public function signIn(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/auth/sign-in', ['title' => 'Sign in | Belis Bell', 'admin' => false]));
    }

    /** @param array<string,string> $params */
    public function register(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/auth/register', ['title' => 'Create an account | Belis Bell']));
    }

    /** @param array<string,string> $params */
    public function verify(Request $request, array $params = []): Response
    {
        if (!AuthController::hasPending(false)) {
            return Response::redirect('/account/sign-in');
        }
        return Response::html(View::render('pages/auth/verify', ['title' => 'Enter your code | Belis Bell', 'admin' => false]));
    }

    /** @param array<string,string> $params */
    public function forgot(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/auth/forgot', ['title' => 'Forgot your password | Belis Bell', 'admin' => false]));
    }

    /** @param array<string,string> $params */
    public function reset(Request $request, array $params = []): Response
    {
        if (!AuthController::hasPending(false, 'reset')) {
            return Response::redirect('/account/forgot');
        }
        return Response::html(View::render('pages/auth/reset', ['title' => 'Choose a new password | Belis Bell', 'admin' => false, 'error' => '']));
    }

    /** @param array<string,string> $params */
    public function checkout(Request $request, array $params = []): Response
    {
        try {
            $cart = Cart::detailed(new Catalogue(Db::fromEnv()));
        } catch (\Throwable $e) {
            Logger::error('Checkout page failed', ['type' => $e::class]);
            return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
        }
        if ($cart['lines'] === []) {
            return Response::redirect('/cart');
        }
        $customer = Auth::customer() ?? [];
        $old = ['name' => $customer['name'] ?? '', 'phone' => $customer['phone'] ?? ''];
        $saved = [];
        if (isset($customer['id'])) {
            try {
                $book = new Addresses(Db::fromEnv());
                $saved = $book->forUser((int) $customer['id']);
                $want = is_string($request->query['address'] ?? null) && ctype_digit($request->query['address']) ? (int) $request->query['address'] : 0;
                $pick = null;
                foreach ($saved as $a) {
                    if (($want > 0 && (int) $a['id'] === $want) || ($want === 0 && (int) $a['is_default'] === 1)) {
                        $pick = $a;
                    }
                }
                if ($pick !== null) {
                    $old = ['name' => $pick['name'], 'phone' => $pick['phone'], 'street' => $pick['street'], 'city' => $pick['city'], 'region' => $pick['region']];
                }
            } catch (\Throwable $e) {
                Logger::error('Saved addresses failed', ['type' => $e::class]);
            }
        }
        return Response::html(View::render('pages/account/checkout', self::checkoutVars($cart, $customer, $old, [], $saved)));
    }

    /**
     * @param array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int} $cart
     * @param array<string,string> $customer
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     * @param list<array<string,mixed>> $saved
     * @return array<string,mixed>
     */
    public static function checkoutVars(array $cart, array $customer, array $old, array $errors, array $saved = []): array
    {
        $options = Site::deliveryOptions();
        $chosen = $options[0] ?? ['key' => '', 'fee' => 0];
        foreach ($options as $o) {
            if ($o['key'] === ($old['delivery'] ?? null)) {
                $chosen = $o;
            }
        }
        return [
            'title' => 'Checkout | Belis Bell',
            'extra_js' => 'js/checkout.js',
            'cart' => $cart,
            'customer' => $customer,
            'old' => $old,
            'errors' => $errors,
            'regions' => Site::regions(),
            'options' => $options,
            'chosen' => $chosen['key'],
            'fee' => $chosen['fee'],
            'total' => $cart['subtotal'] + $chosen['fee'],
            'channels' => Site::paymentChannels(),
            'mock' => Payments::isMock(),
            'canOrder' => isset($customer['id']),
            'saved' => $saved,
        ];
    }

    /** @param array<string,string> $params */
    public function order(Request $request, array $params = []): Response
    {
        $customer = Auth::customer();
        $order = null;
        if ($customer !== null && isset($customer['id'])) {
            try {
                $row = (new Orders(Db::fromEnv()))->forUser($params['ref'] ?? '', (int) $customer['id']);
            } catch (\Throwable $e) {
                Logger::error('Order page failed', ['type' => $e::class]);
                return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
            }
            $order = $row === null ? null : self::orderView($row);
        } else {
            $state = is_string($request->query['status'] ?? null) ? $request->query['status'] : 'paid';
            $order = PreviewData::order($params['ref'] ?? '', $state);
        }
        if ($order === null) {
            return Response::html(View::render('pages/404', ['title' => 'Order not found']), 404);
        }
        return Response::html(View::render('pages/account/order', ['title' => 'Order ' . $order['ref'] . ' | Belis Bell', 'order' => $order]));
    }

    /**
     * @param array<string,mixed> $row an orders row with its items
     * @return array<string,mixed> the shape the order page (and the local preview sample) uses
     */
    public static function orderView(array $row): array
    {
        $lines = [];
        foreach ($row['items'] as $i) {
            $lines[] = ['name' => $i['product_name'], 'label' => $i['size_label'], 'qty' => (int) $i['qty'], 'unit' => (int) $i['unit_pesewas']];
        }
        return [
            'ref' => (string) $row['ref'],
            'state' => (string) $row['status'],
            'placed' => gmdate('d M Y', (int) $row['created_at']),
            'method' => (string) $row['delivery_method'],
            'address' => [(string) $row['ship_name'], (string) $row['ship_street'], $row['ship_city'] . ', ' . $row['ship_region'], (string) $row['ship_phone']],
            'lines' => $lines,
            'delivery' => (int) $row['delivery_pesewas'],
            'real' => true,
        ];
    }

    /** @param array<string,string> $params */
    public function dashboard(Request $request, array $params = []): Response
    {
        $customer = Auth::customer() ?? [];
        $orders = PreviewData::orders();
        if (isset($customer['id'])) {
            try {
                $orders = array_map(static fn (array $o): array => [
                    'ref' => (string) $o['ref'], 'date' => gmdate('d M Y', (int) $o['created_at']), 'state' => (string) $o['status'],
                    'total' => (int) $o['total_pesewas'], 'items' => (int) $o['items'],
                ], (new Orders(Db::fromEnv()))->listForUser((int) $customer['id']));
            } catch (\Throwable $e) {
                Logger::error('Account orders failed', ['type' => $e::class]);
                $orders = [];
            }
        }
        $addresses = PreviewData::addresses();
        if (isset($customer['id'])) {
            try {
                $addresses = array_map(static fn (array $a): array => [
                    'id' => (int) $a['id'], 'label' => (string) $a['label'], 'default' => (int) $a['is_default'] === 1,
                    'lines' => [(string) $a['name'] . ', ' . $a['phone'], (string) $a['street'], $a['city'] . ', ' . $a['region']],
                ], (new Addresses(Db::fromEnv()))->forUser((int) $customer['id']));
            } catch (\Throwable $e) {
                Logger::error('Account addresses failed', ['type' => $e::class]);
                $addresses = [];
            }
        }
        return Response::html(View::render('pages/account/dashboard', [
            'title' => 'My account | Belis Bell',
            'customer' => $customer,
            'orders' => $orders,
            'addresses' => $addresses,
            'regions' => Site::regions(),
            'canEdit' => isset($customer['id']),
        ]));
    }
}
