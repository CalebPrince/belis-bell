<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Cart;
use Belis\Domain\Catalogue;
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
        return Response::html(View::render('pages/auth/verify', ['title' => 'Enter your code | Belis Bell', 'admin' => false]));
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
        $options = Site::deliveryOptions();
        $fee = $options[0]['fee'] ?? 0;
        return Response::html(View::render('pages/account/checkout', [
            'title' => 'Checkout | Belis Bell',
            'cart' => $cart,
            'customer' => Auth::customer() ?? [],
            'regions' => Site::regions(),
            'options' => $options,
            'fee' => $fee,
            'total' => $cart['subtotal'] + $fee,
            'channels' => Site::paymentChannels(),
        ]));
    }

    /** @param array<string,string> $params */
    public function order(Request $request, array $params = []): Response
    {
        $state = is_string($request->query['status'] ?? null) ? $request->query['status'] : 'paid';
        $order = PreviewData::order($params['ref'] ?? '', $state);
        if ($order === null) {
            return Response::html(View::render('pages/404', ['title' => 'Order not found']), 404);
        }
        return Response::html(View::render('pages/account/order', ['title' => 'Order ' . $order['ref'] . ' | Belis Bell', 'order' => $order]));
    }

    /** @param array<string,string> $params */
    public function dashboard(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/account/dashboard', [
            'title' => 'My account | Belis Bell',
            'customer' => Auth::customer() ?? [],
            'orders' => PreviewData::orders(),
            'addresses' => PreviewData::addresses(),
        ]));
    }
}
