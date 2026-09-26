<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\Session;
use Belis\Core\View;
use Belis\Domain\Cart;
use Belis\Domain\Catalogue;
use Belis\Domain\Orders;
use Belis\Payments\MockAdapter;
use Belis\Payments\Payments;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Site;

/**
 * Placing an order and paying for it with Paystack (redirect flow). The customer enters card or mobile money
 * details only on the provider's page. The order is marked paid only after the server asks the provider
 * (Domain\Orders::confirm), never because of a redirect back or a webhook body alone.
 */
final class CheckoutController
{
    /** @param array<string,string> $params */
    public function place(Request $request, array $params = []): Response
    {
        $customer = Auth::customer();
        if ($customer === null || !isset($customer['id'])) {
            Flash::notice('Orders can only be placed from a real account. The local preview person cannot order.');
            return Response::redirect('/cart');
        }
        try {
            $db = Db::fromEnv();
            $cart = Cart::detailed(new Catalogue($db));
            if ($cart['lines'] === []) {
                return Response::redirect('/cart');
            }
            $check = Orders::validateShipping($request->post);
            if ($check['errors'] !== [] || $check['delivery'] === null) {
                return $this->form($cart, $customer, $request->post, $check['errors'], 422);
            }
            $orders = new Orders($db);
            $order = $orders->create((int) $customer['id'], $cart, $request->post, $check['delivery']);
            if ($order === null) {
                Flash::notice('Something in your cart is out of stock. Please review it and try again.');
                return Response::redirect('/cart');
            }
            try {
                $url = Payments::adapter()->initialize($customer['email'], $order['total'], $order['payment_reference'], self::callbackUrl());
            } catch (\Throwable $e) {
                $db->run("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'", [$order['id']]);
                Logger::error('Could not start payment', ['type' => $e::class]);
                Flash::notice('We could not start the payment. You have not been charged. Please try again in a moment.');
                return Response::redirect('/checkout');
            }
        } catch (\Throwable $e) {
            return $this->unavailable($e);
        }
        Session::start();
        $_SESSION['pay'][$order['ref']] = $url;
        return Response::redirect('/pay/' . rawurlencode($order['ref']));
    }

    /** The page that hands the customer to Paystack. A plain link, so the browser leaves this site by a normal click. @param array<string,string> $params */
    public function pay(Request $request, array $params = []): Response
    {
        $customer = Auth::customer();
        try {
            $order = $customer === null || !isset($customer['id']) ? null : (new Orders(Db::fromEnv()))->forUser($params['ref'] ?? '', (int) $customer['id']);
        } catch (\Throwable $e) {
            return $this->unavailable($e);
        }
        Session::start();
        $url = $order !== null ? ($_SESSION['pay'][$order['ref']] ?? null) : null;
        if ($order === null || $order['status'] === 'paid') {
            return $order === null ? Response::html(View::render('pages/404', ['title' => 'Order not found']), 404) : Response::redirect('/order/' . rawurlencode((string) $order['ref']));
        }
        if (!is_string($url) || !self::allowedPayUrl($url)) {
            return Response::redirect('/order/' . rawurlencode((string) $order['ref']));
        }
        return Response::html(View::render('pages/account/pay', ['title' => 'Continue to payment | Belis Bell', 'order' => $order, 'url' => $url, 'mock' => Payments::isMock()]));
    }

    /** Where Paystack sends the browser back. The address only says which order to look at: the status is asked from Paystack. @param array<string,string> $params */
    public function callback(Request $request, array $params = []): Response
    {
        $ref = is_string($request->query['reference'] ?? null) ? $request->query['reference'] : '';
        $customer = Auth::customer();
        try {
            $orders = new Orders(Db::fromEnv());
            $order = $customer === null || !isset($customer['id']) ? null : $this->ownedByPaymentReference($ref, (int) $customer['id']);
            if ($order === null) {
                return Response::redirect('/account');
            }
            $status = $orders->confirm($ref, Payments::adapter(), 'callback');
        } catch (\Throwable $e) {
            Logger::error('Payment callback failed', ['type' => $e::class]);
            Flash::notice('We could not check your payment just now. It will be checked again shortly.');
            return Response::redirect('/order/' . rawurlencode((string) ($order['ref'] ?? '')));
        }
        if ($status === 'paid') {
            Cart::clear();
        }
        return Response::redirect('/order/' . rawurlencode((string) $order['ref']));
    }

    /** Re-check a payment that is still pending. @param array<string,string> $params */
    public function refresh(Request $request, array $params = []): Response
    {
        $customer = Auth::customer();
        $ref = $params['ref'] ?? '';
        try {
            $orders = new Orders(Db::fromEnv());
            $order = $customer === null || !isset($customer['id']) ? null : $orders->forUser($ref, (int) $customer['id']);
            if ($order === null) {
                return Response::html(View::render('pages/404', ['title' => 'Order not found']), 404);
            }
            if ($orders->confirm((string) $order['payment_reference'], Payments::adapter(), 'customer') === 'paid') {
                Cart::clear();
            }
        } catch (\Throwable $e) {
            Logger::error('Payment re-check failed', ['type' => $e::class]);
            Flash::notice('We could not check your payment just now. Please try again in a minute.');
        }
        return Response::redirect('/order/' . rawurlencode($ref));
    }

    /** @param array<string,string> $params */
    public function cancel(Request $request, array $params = []): Response
    {
        $customer = Auth::customer();
        $ref = $params['ref'] ?? '';
        try {
            $status = $customer === null || !isset($customer['id']) ? null : (new Orders(Db::fromEnv()))->cancel($ref, (int) $customer['id'], Payments::adapter());
        } catch (\Throwable $e) {
            Logger::error('Order cancel failed', ['type' => $e::class]);
            Flash::notice('We could not cancel just now. Please try again in a minute.');
            return Response::redirect('/order/' . rawurlencode($ref));
        }
        if ($status === null) {
            return Response::html(View::render('pages/404', ['title' => 'Order not found']), 404);
        }
        return Response::redirect('/order/' . rawurlencode($ref));
    }

    /**
     * Paystack calls this. The signature is checked by the route policy before we get here. The body only tells us
     * which payment to look at: the outcome is asked from Paystack, so a replayed or edited body cannot mark an order
     * paid. A failed lookup answers 500 so Paystack tries again.
     *
     * @param array<string,string> $params
     */
    public function webhook(Request $request, array $params = []): Response
    {
        $event = json_decode($request->rawBody(), true);
        $ref = is_array($event) && is_array($event['data'] ?? null) && is_string($event['data']['reference'] ?? null) ? $event['data']['reference'] : '';
        if ($ref === '' || !is_string($event['event'] ?? null) || !str_starts_with($event['event'], 'charge.')) {
            return new Response(200, 'ignored', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        try {
            (new Orders(Db::fromEnv()))->confirm($ref, Payments::adapter(), 'webhook');
        } catch (\Throwable $e) {
            Logger::error('Webhook could not be processed', ['type' => $e::class]);
            return new Response(500, 'retry', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return new Response(200, 'ok', ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /** The pretend payment page, local development only. @param array<string,string> $params */
    public function mockPage(Request $request, array $params = []): Response
    {
        $ref = $params['reference'] ?? '';
        $mock = self::mock();
        $data = $mock?->read($ref);
        if ($mock === null || $data === null) {
            return Response::html(View::render('pages/404', ['title' => 'Page not found']), 404);
        }
        return Response::html(View::render('pages/account/mock-pay', ['title' => 'Pretend payment | Belis Bell', 'reference' => $ref, 'amount' => (int) $data['amount']]));
    }

    /** @param array<string,string> $params */
    public function mockSettle(Request $request, array $params = []): Response
    {
        $ref = $params['reference'] ?? '';
        $mock = self::mock();
        if ($mock === null || $mock->read($ref) === null) {
            return Response::html(View::render('pages/404', ['title' => 'Page not found']), 404);
        }
        $choice = $request->post['outcome'] ?? '';
        if ($choice === 'success' || $choice === 'failed') {
            $mock->settle($ref, $choice);
        }
        return Response::redirect('/payment/callback?reference=' . rawurlencode($ref));
    }

    private static function mock(): ?MockAdapter
    {
        if (!Payments::isMock()) {
            return null;
        }
        $a = Payments::adapter();
        return $a instanceof MockAdapter ? $a : null;
    }

    /** @return array<string,mixed>|null */
    private function ownedByPaymentReference(string $reference, int $userId): ?array
    {
        $row = Db::fromEnv()->one('SELECT ref FROM orders WHERE payment_reference = ? AND user_id = ?', [$reference, $userId]);
        return $row;
    }

    private static function callbackUrl(): string
    {
        return rtrim(Env::get('APP_URL', '') ?? '', '/') . '/payment/callback';
    }

    /** Only the payment provider's own page, or the local pretend page, may be linked to. */
    private static function allowedPayUrl(string $url): bool
    {
        if (str_starts_with($url, '/mock-pay/')) {
            return Payments::isMock();
        }
        $host = parse_url($url, PHP_URL_HOST);
        return str_starts_with($url, 'https://') && is_string($host) && (str_ends_with($host, '.paystack.com') || str_ends_with($host, '.paystack.co'));
    }

    /**
     * @param array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int} $cart
     * @param array<string,string> $customer
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function form(array $cart, array $customer, array $old, array $errors, int $status): Response
    {
        return Response::html(View::render('pages/account/checkout', AccountController::checkoutVars($cart, $customer, $old, $errors)), $status);
    }

    private function unavailable(\Throwable $e): Response
    {
        Logger::error('Checkout failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
