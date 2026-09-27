<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Orders;
use Belis\Domain\Refunds;
use Belis\Payments\Payments;
use Belis\Support\StepUp;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\PreviewData;

/**
 * Staff view of orders: list with filters, one order in full, and packing or delivery progress for paid orders.
 * Staff see customer names, emails, phones and addresses, so opening an order and every change is written to the
 * audit log (CTL-AUDIT-001, CTL-DATA-001). Nothing here can change money, payment status or prices.
 * The local preview person sees sample rows only and cannot change anything.
 */
final class AdminOrdersController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $staff = Auth::staff() ?? [];
        $filters = [
            'status' => is_string($request->query['status'] ?? null) ? $request->query['status'] : '',
            'fulfilment' => is_string($request->query['fulfilment'] ?? null) ? $request->query['fulfilment'] : '',
            'q' => is_string($request->query['q'] ?? null) ? $request->query['q'] : '',
            'page' => is_string($request->query['page'] ?? null) && ctype_digit($request->query['page']) ? (int) $request->query['page'] : 1,
        ];
        if (!isset($staff['id'])) {
            $rows = array_map(static fn (array $o): array => ['ref' => $o['ref'], 'status' => $o['state'], 'fulfilment' => 'new', 'needs_review' => 0, 'total_pesewas' => $o['total'], 'created_at' => 1790000000, 'ship_name' => $o['customer'], 'email' => 'sample@example.test'], PreviewData::adminOrders());
            $result = ['items' => $rows, 'total' => count($rows), 'pages' => 1, 'page' => 1];
        } else {
            try {
                $result = (new Orders(Db::fromEnv()))->adminList($filters);
            } catch (\Throwable $e) {
                return $this->failed($e);
            }
        }
        return Response::html(View::render('pages/admin/orders', [
            'title' => 'Orders | Belis Bell',
            'staff' => self::staffView($staff),
            'result' => $result,
            'filters' => $filters,
            'preview' => !isset($staff['id']),
        ]));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $staff = Auth::staff() ?? [];
        $ref = $params['ref'] ?? '';
        if (!isset($staff['id'])) {
            $sample = PreviewData::order($ref);
            if ($sample === null) {
                return Response::html(View::render('pages/404', ['title' => 'Order not found']), 404);
            }
            $order = ['ref' => $sample['ref'], 'status' => $sample['state'], 'fulfilment' => 'new', 'needs_review' => 0, 'created_at' => 1790000000, 'paid_at' => 1790000100, 'delivery_method' => $sample['method'],
                'ship_name' => $sample['address'][0], 'ship_street' => $sample['address'][1], 'ship_city' => 'Accra', 'ship_region' => 'Greater Accra', 'ship_phone' => $sample['address'][3], 'notes' => '',
                'customer_email' => 'sample@example.test', 'customer_name' => $sample['address'][0], 'payment_reference' => 'BBP-sample', 'delivery_pesewas' => $sample['delivery'], 'subtotal_pesewas' => 0, 'total_pesewas' => 0, 'events' => [], 'items' => [], 'refunds' => [], 'refunded' => 0];
            foreach ($sample['lines'] as $l) {
                $order['items'][] = ['product_name' => $l['name'], 'size_label' => $l['label'], 'qty' => $l['qty'], 'unit_pesewas' => $l['unit'], 'line_pesewas' => $l['qty'] * $l['unit']];
                $order['subtotal_pesewas'] += $l['qty'] * $l['unit'];
            }
            $order['total_pesewas'] = $order['subtotal_pesewas'] + $order['delivery_pesewas'];
        } else {
            try {
                $db = Db::fromEnv();
                $order = (new Orders($db))->adminDetail($ref);
                if ($order !== null) {
                    Audit::add($db, (int) $staff['id'], 'order.view', $ref, $request->ip);
                    $refunds = new Refunds($db);
                    $order['refunds'] = $refunds->forOrder((int) $order['id']);
                    $order['refunded'] = $refunds->refundedTotal((int) $order['id']);
                }
            } catch (\Throwable $e) {
                return $this->failed($e);
            }
            if ($order === null) {
                return Response::html(View::render('pages/404', ['title' => 'Order not found']), 404);
            }
        }
        return Response::html(View::render('pages/admin/order', ['title' => 'Order ' . $order['ref'] . ' | Belis Bell', 'staff' => self::staffView($staff), 'order' => $order, 'preview' => !isset($staff['id'])]));
    }

    /** @param array<string,string> $params */
    public function fulfilment(Request $request, array $params = []): Response
    {
        $staff = Auth::staff() ?? [];
        $ref = $params['ref'] ?? '';
        $to = is_string($request->post['fulfilment'] ?? null) ? $request->post['fulfilment'] : '';
        if (!isset($staff['id'])) {
            Flash::notice('The local preview person cannot change orders.');
            return Response::redirect('/admin/orders/' . rawurlencode($ref));
        }
        try {
            $db = Db::fromEnv();
            $pdo = $db->pdo();
            $pdo->beginTransaction();
            $res = (new Orders($db))->setFulfilment($ref, $to);
            $ok = $res['ok'];
            if ($res['ok'] && $res['changed']) {
                Audit::add($db, (int) $staff['id'], 'order.fulfilment.' . $to, $ref, $request->ip);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $this->failed($e);
        }
        Flash::notice($ok ? 'Order updated.' : 'That could not be changed. Only paid orders can be moved along.');
        return Response::redirect('/admin/orders/' . rawurlencode($ref));
    }

    /** @param array<string,string> $params */
    public function refund(Request $request, array $params = []): Response
    {
        $owner = Auth::owner();
        $ref = $params['ref'] ?? '';
        $back = Response::redirect('/admin/orders/' . rawurlencode($ref));
        if ($owner === null || !isset($owner['id'])) {
            Flash::notice('The local preview person cannot refund orders.');
            return $back;
        }
        $me = (int) $owner['id'];
        if (!StepUp::fresh($me)) {
            Flash::notice('Confirm with an emailed code, then request the refund again.');
            return Response::redirect('/admin/confirm?next=' . rawurlencode('/admin/orders/' . $ref));
        }
        $amount = \Belis\Domain\CatalogueAdmin::parsePrice(is_string($request->post['amount'] ?? null) ? $request->post['amount'] : '');
        if ($amount === null) {
            Flash::notice('Enter the refund amount in cedis, for example 25.00.');
            return $back;
        }
        try {
            $db = Db::fromEnv();
            $refunds = new Refunds($db);
            $r = $refunds->request($ref, $amount, (string) ($request->post['reason'] ?? ''), $me, Payments::adapter());
            if ($r['ok']) {
                Audit::add($db, $me, 'refund.create', $ref, $request->ip, null, money($amount) . ' ' . $r['status']);
                // Once more than the daily limit has been refunded, the confirmation is spent: the next refund needs a new code.
                if ($refunds->overLimit()) {
                    StepUp::consume($me);
                }
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Flash::notice($r['ok'] ? ($r['status'] === 'processed' ? 'Refund processed.' : 'Refund started. It is waiting for Paystack and will update by itself.') : (string) $r['error']);
        return $back;
    }

    /** @param array<string,string> $staff @return array<string,string> */
    private static function staffView(array $staff): array
    {
        return array_replace($staff, ['role' => ucfirst((string) ($staff['role'] ?? ''))]);
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Admin orders failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
