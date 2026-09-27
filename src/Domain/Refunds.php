<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;
use Belis\Payments\PaymentAdapter;
use Belis\Support\Logger;
use Belis\Support\Mailer;
use Belis\Support\Settings;

/**
 * Refunds (CTL-PAY-003). Only paid orders can be refunded, only back to the original payment through the provider,
 * and never for more than was paid minus what has already been refunded. Every refund and every step of its progress
 * is an append-only row. Progress is read back from the provider, never set by a button. The controller decides who
 * may ask and enforces the fresh code; this class holds the money rules.
 */
final class Refunds
{
    public const DEFAULT_DAILY_LIMIT = 200000; // GHS 2,000.00

    public function __construct(private readonly Db $db, private readonly ?int $clock = null, private readonly ?Mailer $mailer = null)
    {
    }

    private function now(): int
    {
        return $this->clock ?? time();
    }

    /** The daily refund limit in pesewas: the owner's setting, else GHS 2,000.00. */
    public static function dailyLimit(): int
    {
        $text = trim(Settings::get('REFUND_DAILY_LIMIT', '') ?? '');
        if (preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            return self::DEFAULT_DAILY_LIMIT;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /** Refunded so far on one order, counting refunds that are pending or processed but not failed ones. */
    public function refundedTotal(int $orderId): int
    {
        return (int) ($this->db->one('SELECT COALESCE(SUM(r.amount_pesewas), 0) AS n FROM refunds r WHERE r.order_id = ? AND ' . self::NOT_FAILED, [$orderId])['n'] ?? 0);
    }

    /** Refunded since midnight UTC across all orders (not counting failed ones). */
    public function dailyTotal(): int
    {
        $now = $this->now();
        return (int) ($this->db->one('SELECT COALESCE(SUM(r.amount_pesewas), 0) AS n FROM refunds r WHERE r.created_at >= ? AND ' . self::NOT_FAILED, [$now - ($now % 86400)])['n'] ?? 0);
    }

    /** True when more than the daily limit has already been refunded today: each further refund needs its own fresh code. */
    public function overLimit(): bool
    {
        return $this->dailyTotal() > self::dailyLimit();
    }

    private const NOT_FAILED = "(SELECT e.status FROM refund_events e WHERE e.refund_id = r.id ORDER BY e.id DESC LIMIT 1) <> 'failed'";

    /** @return list<array<string,mixed>> */
    public function forOrder(int $orderId): array
    {
        return $this->db->all('SELECT r.id, r.amount_pesewas, r.reason, r.created_at, u.email AS by_email, (SELECT e.status FROM refund_events e WHERE e.refund_id = r.id ORDER BY e.id DESC LIMIT 1) AS status FROM refunds r LEFT JOIN users u ON u.id = r.requested_by WHERE r.order_id = ? ORDER BY r.id DESC', [$orderId]);
    }

    /**
     * Start a refund. The checks come first; then the row is written, then the provider is asked.
     *
     * @return array{ok:bool,error:?string,status:string}
     */
    public function request(string $ref, int $amountPesewas, string $reason, int $by, PaymentAdapter $adapter): array
    {
        $reason = trim($reason);
        $o = $this->db->one("SELECT id, status, total_pesewas, payment_reference, user_id FROM orders WHERE ref = ?", [$ref]);
        if ($o === null || $o['status'] !== 'paid') {
            return ['ok' => false, 'error' => 'Only a paid order can be refunded.', 'status' => ''];
        }
        if ($reason === '' || mb_strlen($reason) > 200) {
            return ['ok' => false, 'error' => 'Give a reason of up to 200 characters.', 'status' => ''];
        }
        $left = (int) $o['total_pesewas'] - $this->refundedTotal((int) $o['id']);
        if ($amountPesewas < 1 || $amountPesewas > $left) {
            return ['ok' => false, 'error' => $left <= 0 ? 'This order has already been refunded in full.' : 'Enter an amount from 0.01 up to ' . money($left) . '.', 'status' => ''];
        }
        $this->db->run('INSERT INTO refunds (order_id, amount_pesewas, reason, requested_by, created_at) VALUES (?, ?, ?, ?, ?)', [(int) $o['id'], $amountPesewas, $reason, $by, $this->now()]);
        $id = (int) ($this->db->one('SELECT MAX(id) AS id FROM refunds WHERE order_id = ?', [(int) $o['id']])['id'] ?? 0);
        $this->event($id, 'pending', 'admin');
        $status = 'pending';
        try {
            $status = $adapter->refund((string) $o['payment_reference'], $amountPesewas, $reason)['status'];
        } catch (\Throwable $e) {
            // The provider may or may not have accepted it. It stays pending and the provider is asked again later.
            Logger::error('Refund request could not be confirmed', ['type' => $e::class]);
            $this->event($id, 'pending', 'error');
            $this->notifyOwners($ref, $amountPesewas, 'The refund was requested but Paystack could not be reached to confirm it. It will be checked again automatically. Look at it in the Paystack dashboard if it stays pending.');
            return ['ok' => true, 'error' => null, 'status' => 'pending'];
        }
        if ($status === 'processed') {
            $this->event($id, 'processed', 'provider');
            $this->emailCustomer((int) $o['id'], $amountPesewas, true);
        } else {
            $this->emailCustomer((int) $o['id'], $amountPesewas, false);
        }
        $this->notifyOwners($ref, $amountPesewas, 'A refund was ' . ($status === 'processed' ? 'processed.' : 'started and is waiting for Paystack.'));
        return ['ok' => true, 'error' => null, 'status' => $status];
    }

    /**
     * Read the provider's view of one order's refunds and record any change. Safe to repeat and to call from a webhook:
     * the body of the webhook is never trusted, only this lookup.
     */
    public function sync(string $paymentReference, PaymentAdapter $adapter): void
    {
        $o = $this->db->one('SELECT id FROM orders WHERE payment_reference = ?', [$paymentReference]);
        if ($o === null) {
            return;
        }
        $theirs = $adapter->refunds($paymentReference);
        $pending = array_values(array_filter($this->forOrder((int) $o['id']), static fn (array $r): bool => $r['status'] === 'pending'));
        usort($pending, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);
        foreach ($pending as $r) {
            foreach ($theirs as $k => $t) {
                if ($t['amount'] === (int) $r['amount_pesewas'] && in_array($t['status'], ['processed', 'failed'], true)) {
                    unset($theirs[$k]);
                    $this->event((int) $r['id'], $t['status'], 'provider');
                    if ($t['status'] === 'processed') {
                        $this->emailCustomer((int) $o['id'], (int) $r['amount_pesewas'], true);
                    }
                    break;
                }
            }
        }
    }

    /**
     * For the scheduled job: check every order with a pending refund. A refund the provider still does not know about
     * after 2 hours is marked failed so the amount can be refunded again.
     *
     * @return array{checked:int,errors:int}
     */
    public function reconcile(PaymentAdapter $adapter): array
    {
        $out = ['checked' => 0, 'errors' => 0];
        $rows = $this->db->all("SELECT DISTINCT o.id, o.payment_reference FROM refunds r JOIN orders o ON o.id = r.order_id WHERE (SELECT e.status FROM refund_events e WHERE e.refund_id = r.id ORDER BY e.id DESC LIMIT 1) = 'pending'");
        foreach ($rows as $o) {
            $out['checked']++;
            try {
                $this->sync((string) $o['payment_reference'], $adapter);
            } catch (\Throwable) {
                $out['errors']++;
                continue;
            }
            foreach ($this->forOrder((int) $o['id']) as $r) {
                if ($r['status'] === 'pending' && $this->now() - (int) $r['created_at'] > 7200) {
                    $this->event((int) $r['id'], 'failed', 'reconcile');
                }
            }
        }
        return $out;
    }

    private function event(int $refundId, string $status, string $source): void
    {
        $this->db->run('INSERT INTO refund_events (refund_id, status, source, created_at) VALUES (?, ?, ?, ?)', [$refundId, $status, $source, $this->now()]);
    }

    private function emailCustomer(int $orderId, int $amount, bool $processed): void
    {
        try {
            $o = $this->db->one('SELECT o.ref, u.email, u.name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$orderId]);
            if ($o === null) {
                return;
            }
            $what = $processed ? 'has been sent back to your original payment method. It can take a few days to show.' : 'has been started. We will email you again when it is sent.';
            ($this->mailer ?? Mailer::fromEnv())->send((string) $o['email'], 'Your Belis Bell refund', "Hello {$o['name']},\n\nA refund of " . money($amount) . " for order {$o['ref']} {$what}\n\nBelis Bell will never ask for your password or a code by phone, WhatsApp or email.");
        } catch (\Throwable $e) {
            Logger::error('Refund email could not be sent', ['type' => $e::class]);
        }
    }

    private function notifyOwners(string $ref, int $amount, string $line): void
    {
        try {
            $mailer = $this->mailer ?? Mailer::fromEnv();
            foreach ($this->db->all("SELECT email FROM users WHERE role = 'owner' AND is_active = 1") as $u) {
                $mailer->send((string) $u['email'], 'A Belis Bell refund was made', 'Refund of ' . money($amount) . " on order {$ref}. {$line}\n\nIf you did not do this, change your password and the Paystack keys now.");
            }
        } catch (\Throwable $e) {
            Logger::error('Refund owner email could not be sent', ['type' => $e::class]);
        }
    }
}
