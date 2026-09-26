<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Cart;
use Belis\Domain\Catalogue;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Site;

/** Cart pages. Writes are POST with a CSRF token; the browser only sends a size id and a quantity. */
final class CartController
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        try {
            $catalogue = new Catalogue(Db::fromEnv());
            $cart = Cart::detailed($catalogue);
            $inCart = array_column($cart['lines'], 'slug');
            $more = array_values(array_filter($catalogue->featured(8), static fn (array $p): bool => !in_array($p['slug'], $inCart, true)));
        } catch (\Throwable $e) {
            Logger::error('Cart page failed', ['type' => $e::class]);
            return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
        }
        return Response::html(View::render('pages/cart', [
            'title' => 'Your cart | Belis Bell',
            'cart' => $cart,
            'more' => array_slice($more, 0, 4),
            'trust' => Site::trust(),
            'deliveryInfo' => Site::deliveryInfo(),
            'channels' => Site::paymentChannels(),
        ]));
    }

    /** @param array<string,string> $params */
    public function add(Request $request, array $params = []): Response
    {
        $variantId = self::positiveInt($request->post['variant'] ?? null);
        $qty = self::positiveInt($request->post['qty'] ?? null) ?? 1;
        $back = self::returnTo($request->post['return_to'] ?? null);
        if ($variantId === null) {
            Flash::add('That product could not be added.');
            return Response::redirect($back);
        }
        try {
            $v = (new Catalogue(Db::fromEnv()))->variantWithProduct($variantId);
        } catch (\Throwable $e) {
            Logger::error('Add to cart failed', ['type' => $e::class]);
            Flash::add('Something went wrong. Please try again.');
            return Response::redirect($back);
        }
        if ($v === null) {
            Flash::add('That product is no longer available.');
        } elseif ($v['stock_status'] === 'out') {
            Flash::add($v['name'] . ' is out of stock.');
        } elseif (!Cart::add($variantId, $qty)) {
            Flash::add('Your cart is full. Remove something first.');
        } else {
            Flash::add('Added to your cart: ' . $v['name'] . ' (' . $v['label'] . ').');
        }
        return Response::redirect($back);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $variantId = self::positiveInt($request->post['variant'] ?? null);
        $raw = $request->post['qty'] ?? null;
        if ($variantId !== null && is_string($raw) && preg_match('/^\d{1,5}$/', $raw) === 1) {
            Cart::set($variantId, (int) $raw);
        }
        return Response::redirect('/cart');
    }

    /** @param array<string,string> $params */
    public function remove(Request $request, array $params = []): Response
    {
        $variantId = self::positiveInt($request->post['variant'] ?? null);
        if ($variantId !== null) {
            Cart::set($variantId, 0);
            Flash::add('Removed from your cart.');
        }
        return Response::redirect('/cart');
    }

    private static function positiveInt(mixed $v): ?int
    {
        return is_string($v) && preg_match('/^\d{1,9}$/', $v) === 1 && (int) $v > 0 ? (int) $v : null;
    }

    /** Only a path on this site is allowed as the place to go back to. */
    public static function returnTo(mixed $v): string
    {
        if (!is_string($v) || $v === '' || strlen($v) > 300 || $v[0] !== '/' || str_starts_with($v, '//') || preg_match('/[\\\\\r\n\0]/', $v) === 1) {
            return '/cart';
        }
        return $v;
    }
}
