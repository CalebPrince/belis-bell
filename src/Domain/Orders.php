<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;
use Belis\Payments\PaymentAdapter;
use Belis\Support\Logger;
use Belis\Support\Mailer;
use Belis\Support\Site;
use Belis\Support\Validator;

/**
 * Orders and their payment status (CTL-BIZ-001, CTL-PAY-002, CTL-AUTHZ-001).
 * The server rebuilds every price from the catalogue; the browser sends only the address and a delivery choice.
 * An order becomes paid only when the payment provider's verify call says so with the right amount, currency and
 * reference. A browser redirect, a webhook body or a button never sets the status by itself.
 */
final class Orders
{
    public function __construct(private readonly Db $db, private readonly ?int $clock = null, private readonly ?Mailer $mailer = null)
    {
    }

    private function now(): int
    {
        return $this->clock ?? time();
    }

    /**
     * @param array<string,mixed> $in name, phone, street, city, region, delivery, notes
     * @return array{errors:array<string,string>,delivery:?array{key:string,name:string,fee:int,line:string}}
     */
    public static function validateShipping(array $in): array
    {
        $errors = Validator::check($in, ['name' => 'required|max:120', 'phone' => 'required|max:20', 'street' => 'required|max:200', 'city' => 'required|max:80', 'region' => 'required|in:' . implode(',', Site::regions()), 'notes' => 'max:500']);
        $phone = is_string($in['phone'] ?? null) ? trim($in['phone']) : '';
        if (!isset($errors['phone']) && preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone) !== 1) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $delivery = null;
        foreach (Site::deliveryOptions() as $o) {
            if ($o['key'] === ($in['delivery'] ?? null)) {
                $delivery = $o;
            }
        }
        if ($delivery === null) {
            $errors['delivery'] = 'Choose a delivery method.';
        }
        return ['errors' => $errors, 'delivery' => $delivery];
    }

    /**
     * Create a pending order from the cart lines (already priced by the server).
     *
     * @param array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int} $cart
     * @param array<string,mixed> $in validated shipping input
     * @param array{key:string,name:string,fee:int,line:string} $delivery
     * @return array{ref:string,payment_reference:string,total:int,id:int}|null null when the cart cannot be ordered
     */
    public function create(int $userId, array $cart, array $in, array $delivery): ?array
    {
        if ($cart['lines'] === []) {
            return null;
        }
        foreach ($cart['lines'] as $l) {
            if ($l['stock_status'] === 'out') {
                return null;
            }
        }
        $ref = 'BB-' . strtoupper(bin2hex(random_bytes(5)));
        $payRef = 'BBP-' . bin2hex(random_bytes(12));
        $total = $cart['subtotal'] + $delivery['fee'];
        $this->db->run(
            'INSERT INTO orders (ref, user_id, status, currency, subtotal_pesewas, delivery_pesewas, total_pesewas, delivery_method, ship_name, ship_phone, ship_street, ship_city, ship_region, notes, payment_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ref, $userId, 'pending', 'GHS', $cart['subtotal'], $delivery['fee'], $total, $delivery['name'], trim((string) $in['name']), trim((string) $in['phone']), trim((string) $in['street']), trim((string) $in['city']), (string) $in['region'], trim((string) ($in['notes'] ?? '')), $payRef, $this->now()],
        );
        $id = (int) ($this->db->one('SELECT id FROM orders WHERE ref = ?', [$ref])['id'] ?? 0);
        foreach ($cart['lines'] as $l) {
            $this->db->run(
                'INSERT INTO order_items (order_id, variant_id, product_name, size_label, qty, unit_pesewas, line_pesewas) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, (int) $l['variant_id'], (string) $l['name'], (string) $l['label'], (int) $l['qty'], (int) $l['unit_pesewas'], (int) $l['line_pesewas']],
            );
        }
        return ['ref' => $ref, 'payment_reference' => $payRef, 'total' => $total, 'id' => $id];
    }

    public const FULFILMENT = ['new' => 'To pack', 'packed' => 'Packed', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered'];
    public const PAGE_SIZE = 20;

    /**
     * Staff order list with optional filters. Every filter is a bound value.
     *
     * @param array{status?:string,fulfilment?:string,q?:string,page?:int} $f
     * @return array{items:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public function adminList(array $f): array
    {
        $status = in_array($f['status'] ?? '', ['pending', 'paid', 'failed', 'cancelled'], true) ? (string) $f['status'] : '';
        $ful = array_key_exists($f['fulfilment'] ?? '', self::FULFILMENT) ? (string) $f['fulfilment'] : '';
        $q = trim((string) ($f['q'] ?? ''));
        $like = $q === '' ? '' : '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 60)) . '%';
        $params = [$status, $status, $ful, $ful, $like, $like, $like, $like];
        $row = $this->db->one(
            "SELECT COUNT(*) AS n FROM orders o JOIN users u ON u.id = o.user_id WHERE (? = '' OR o.status = ?) AND (? = '' OR o.fulfilment = ?) AND (? = '' OR o.ref LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR o.ship_name LIKE ? ESCAPE '!')",
            $params,
        );
        $total = (int) ($row['n'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min((int) ($f['page'] ?? 1), $pages));
        $items = $this->db->all(
            "SELECT o.ref, o.status, o.fulfilment, o.needs_review, o.total_pesewas, o.created_at, o.ship_name, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE (? = '' OR o.status = ?) AND (? = '' OR o.fulfilment = ?) AND (? = '' OR o.ref LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR o.ship_name LIKE ? ESCAPE '!') ORDER BY o.id DESC LIMIT ? OFFSET ?",
            array_merge($params, [self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE]),
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array<string,mixed>|null a full order for staff: customer, lines and the payment history */
    public function adminDetail(string $ref): ?array
    {
        $o = $this->db->one('SELECT o.*, u.email AS customer_email, u.name AS customer_name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.ref = ?', [$ref]);
        if ($o === null) {
            return null;
        }
        $o['items'] = $this->db->all('SELECT product_name, size_label, qty, unit_pesewas, line_pesewas FROM order_items WHERE order_id = ? ORDER BY id', [(int) $o['id']]);
        $o['events'] = $this->db->all('SELECT source, outcome, created_at FROM payment_events WHERE order_id = ? ORDER BY id', [(int) $o['id']]);
        return $o;
    }

    /**
     * Set packing or delivery progress. Only a paid order can move.
     *
     * @return array{ok:bool,changed:bool} ok is false when refused; changed is false when it was already that value
     */
    public function setFulfilment(string $ref, string $value): array
    {
        if (!array_key_exists($value, self::FULFILMENT)) {
            return ['ok' => false, 'changed' => false];
        }
        $o = $this->db->one("SELECT id, fulfilment FROM orders WHERE ref = ? AND status = 'paid'", [$ref]);
        if ($o === null) {
            return ['ok' => false, 'changed' => false];
        }
        if ($o['fulfilment'] === $value) {
            return ['ok' => true, 'changed' => false];
        }
        $this->db->run("UPDATE orders SET fulfilment = ? WHERE id = ? AND status = 'paid'", [$value, (int) $o['id']]);
        if ($value !== 'new') {
            $line = ['packed' => 'has been packed and is getting ready to leave', 'out_for_delivery' => 'is out for delivery', 'delivered' => 'has been delivered'][$value];
            $this->emailCustomer((int) $o['id'], 'Update on your Belis Bell order', 'fulfilment', $line);
        }
        return ['ok' => true, 'changed' => true];
    }

    /**
     * Email the customer about their order. A failure is logged and never stops the payment or the change.
     */
    private function emailCustomer(int $orderId, string $subject, string $kind, string $line = ''): void
    {
        try {
            $o = $this->db->one('SELECT o.*, u.email, u.name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$orderId]);
            if ($o === null) {
                return;
            }
            $items = $this->db->all('SELECT product_name, size_label, qty, line_pesewas FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
            $url = rtrim(\Belis\Core\Env::get('APP_URL', '') ?? '', '/') . '/order/' . rawurlencode((string) $o['ref']);
            if ($kind === 'paid') {
                $lines = array_map(static fn (array $i): string => '- ' . $i['product_name'] . ' (' . $i['size_label'] . ') x ' . $i['qty'] . '  ' . money((int) $i['line_pesewas']), $items);
                $body = "Hello {$o['name']},\n\nThank you. We received your payment for order {$o['ref']}.\n\n" . implode("\n", $lines)
                    . "\n\nDelivery: " . money((int) $o['delivery_pesewas']) . " ({$o['delivery_method']})\nTotal paid: " . money((int) $o['total_pesewas'])
                    . "\n\nDelivering to: {$o['ship_name']}, {$o['ship_street']}, {$o['ship_city']}, {$o['ship_region']}\n\nWe will contact you about delivery. You can follow your order here: {$url}";
            } else {
                $body = "Hello {$o['name']},\n\nYour order {$o['ref']} {$line}.\n\nYou can see it here: {$url}";
            }
            ($this->mailer ?? Mailer::fromEnv())->send((string) $o['email'], $subject, $body . "\n\nBelis Bell will never ask for your password or a code by phone, WhatsApp or email.");
        } catch (\Throwable $e) {
            Logger::error('Order email could not be sent', ['type' => $e::class]);
        }
    }

    /**
     * Figures for the admin dashboard, from real orders.
     *
     * @return array{today:int,week_pesewas:int,to_pack:int,review:int,unpaid:int}
     */
    public function adminFigures(): array
    {
        $now = $this->now();
        $one = fn (string $sql, array $p = []): int => (int) (array_values($this->db->one($sql, $p) ?? [0])[0] ?? 0);
        return [
            'today' => $one('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$now - ($now % 86400)]),
            'week_pesewas' => $one("SELECT COALESCE(SUM(total_pesewas), 0) FROM orders WHERE status = 'paid' AND paid_at >= ?", [$now - 7 * 86400]),
            'to_pack' => $one("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND fulfilment = 'new'"),
            'review' => $one('SELECT COUNT(*) FROM orders WHERE needs_review = 1'),
            'unpaid' => $one("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
        ];
    }

    /** @return array<string,mixed>|null only the person's own order */
    public function forUser(string $ref, int $userId): ?array
    {
        $o = $this->db->one('SELECT * FROM orders WHERE ref = ? AND user_id = ?', [$ref, $userId]);
        if ($o === null) {
            return null;
        }
        $o['items'] = $this->db->all('SELECT product_name, size_label, qty, unit_pesewas, line_pesewas FROM order_items WHERE order_id = ? ORDER BY id', [(int) $o['id']]);
        return $o;
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->db->all('SELECT o.ref, o.status, o.total_pesewas, o.created_at, (SELECT COALESCE(SUM(qty), 0) FROM order_items i WHERE i.order_id = o.id) AS items FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC LIMIT 50', [$userId]);
    }

    /**
     * Ask the provider about an order's payment and update the order. Safe to call any number of times and from
     * several places at once: the status moves to paid only once.
     *
     * @return string the order status afterwards (pending, paid, failed, cancelled), or 'unknown' if there is no such order
     */
    public function confirm(string $paymentReference, PaymentAdapter $adapter, string $source): string
    {
        $o = $this->db->one('SELECT id, status, total_pesewas, currency, payment_reference FROM orders WHERE payment_reference = ?', [$paymentReference]);
        if ($o === null) {
            return 'unknown';
        }
        $result = $adapter->verify((string) $o['payment_reference']); // may throw: then nothing changes
        $id = (int) $o['id'];
        if ($result['reference'] !== $o['payment_reference'] && $result['status'] !== 'pending') {
            $this->event($id, $source, 'reference_mismatch');
            $this->flag($id);
            Logger::error('Payment reference mismatch', ['order' => $id]);
            return (string) $o['status'];
        }
        if ($result['status'] === 'success') {
            if ($result['amount'] !== (int) $o['total_pesewas'] || $result['currency'] !== $o['currency']) {
                $this->event($id, $source, 'amount_mismatch');
                $this->flag($id);
                Logger::error('Payment amount or currency mismatch', ['order' => $id]);
                return (string) $o['status'];
            }
            $n = $this->db->run("UPDATE orders SET status = 'paid', paid_at = ? WHERE id = ? AND status <> 'paid'", [$this->now(), $id]);
            $this->event($id, $source, $n > 0 ? 'paid' : 'already_paid');
            if ($n > 0) {
                $this->emailCustomer((int) $id, 'Your Belis Bell order is confirmed', 'paid');
            }
            return 'paid';
        }
        if ($result['status'] === 'failed') {
            $this->db->run("UPDATE orders SET status = 'failed' WHERE id = ? AND status = 'pending'", [$id]);
            $this->event($id, $source, 'failed');
        }
        return (string) ($this->db->one('SELECT status FROM orders WHERE id = ?', [$id])['status'] ?? 'unknown');
    }

    /** Cancel an unpaid order the customer no longer wants. Verifies first, so a payment made meanwhile is not lost. */
    public function cancel(string $ref, int $userId, PaymentAdapter $adapter): ?string
    {
        $o = $this->forUser($ref, $userId);
        if ($o === null) {
            return null;
        }
        $status = $this->confirm((string) $o['payment_reference'], $adapter, 'customer');
        if ($status === 'pending' || $status === 'failed') {
            $this->db->run("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status <> 'paid'", [(int) $o['id']]);
            $this->event((int) $o['id'], 'customer', 'cancelled');
            return 'cancelled';
        }
        return $status;
    }

    /**
     * For the scheduled job: check every unpaid order older than $olderThan seconds, and cancel the ones still unpaid
     * after $expireAfter seconds.
     *
     * @return array{checked:int,paid:int,expired:int,errors:int}
     */
    public function reconcile(PaymentAdapter $adapter, int $olderThan = 300, int $expireAfter = 86400): array
    {
        $now = $this->now();
        $rows = $this->db->all("SELECT id, payment_reference, created_at FROM orders WHERE status IN ('pending', 'failed') AND created_at < ? ORDER BY id LIMIT 200", [$now - $olderThan]);
        $out = ['checked' => 0, 'paid' => 0, 'expired' => 0, 'errors' => 0];
        foreach ($rows as $r) {
            $out['checked']++;
            try {
                $status = $this->confirm((string) $r['payment_reference'], $adapter, 'reconcile');
            } catch (\Throwable) {
                $out['errors']++;
                continue;
            }
            if ($status === 'paid') {
                $out['paid']++;
            } elseif ($now - (int) $r['created_at'] > $expireAfter) {
                $this->db->run("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status <> 'paid'", [(int) $r['id']]);
                $this->event((int) $r['id'], 'reconcile', 'expired');
                $out['expired']++;
            }
        }
        return $out;
    }

    private function event(int $orderId, string $source, string $outcome): void
    {
        $this->db->run('INSERT INTO payment_events (order_id, source, outcome, created_at) VALUES (?, ?, ?, ?)', [$orderId, $source, $outcome, $this->now()]);
    }

    private function flag(int $orderId): void
    {
        $this->db->run('UPDATE orders SET needs_review = 1 WHERE id = ?', [$orderId]);
    }
}
