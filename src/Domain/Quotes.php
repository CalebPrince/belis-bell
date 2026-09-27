<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Throttle;
use Belis\Support\Logger;
use Belis\Support\Mailer;

/**
 * Quote requests: a signed-in customer asks for prices on a list of items, staff with the sales role (and the owner)
 * answer in a message thread and send a priced offer, and the customer accepts or declines it. Text only, no files.
 * Every customer read and write is limited to that customer's own quotes (CTL-AUTHZ-001). Offer totals are always
 * worked out here from the unit prices staff typed, never taken from a browser. The controllers decide who may call what.
 */
final class Quotes
{
    public const STATUSES = ['new' => 'New', 'in_progress' => 'In progress', 'quoted' => 'Quote sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'closed' => 'Closed'];
    public const MAX_ITEMS = 20;
    public const MAX_QTY = 100000;
    public const PAGE_SIZE = 20;
    public const MAX_UNIT = 10000000; // GHS 100,000.00 each

    public function __construct(private readonly Db $db, private readonly ?int $clock = null, private readonly ?Mailer $mailer = null)
    {
    }

    private function now(): int
    {
        return $this->clock ?? time();
    }

    /**
     * Create a quote request with its first message.
     *
     * @param array<string,mixed> $in org_name, needed_by (Y-m-d), message, item_name[], item_qty[], item_note[]
     * @return array{errors:array<string,string>,ref:string}
     */
    public function create(int $userId, array $in, string $ip): array
    {
        $errors = [];
        $org = is_string($in['org_name'] ?? null) ? trim($in['org_name']) : '';
        if (mb_strlen($org) > 120) {
            $errors['org_name'] = 'Use 120 characters or fewer.';
        }
        $message = is_string($in['message'] ?? null) ? trim($in['message']) : '';
        if (mb_strlen($message) > 2000) {
            $errors['message'] = 'Use 2,000 characters or fewer.';
        }
        $neededBy = null;
        $by = is_string($in['needed_by'] ?? null) ? trim($in['needed_by']) : '';
        if ($by !== '') {
            $ts = self::parseDate($by);
            if ($ts === null || $ts < $this->now() - 86400 || $ts > $this->now() + 366 * 86400) {
                $errors['needed_by'] = 'Enter a date within the next year, such as 2026-12-15.';
            } else {
                $neededBy = $ts;
            }
        }
        $names = is_array($in['item_name'] ?? null) ? array_values($in['item_name']) : [];
        $qtys = is_array($in['item_qty'] ?? null) ? array_values($in['item_qty']) : [];
        $notes = is_array($in['item_note'] ?? null) ? array_values($in['item_note']) : [];
        $items = [];
        for ($i = 0; $i < min(count($names), 40); $i++) {
            $n = is_string($names[$i] ?? null) ? trim($names[$i]) : '';
            $q = is_string($qtys[$i] ?? null) ? trim($qtys[$i]) : '';
            $t = is_string($notes[$i] ?? null) ? trim($notes[$i]) : '';
            if ($n === '' && $q === '' && $t === '') {
                continue;
            }
            if ($n === '' || mb_strlen($n) > 160 || preg_match('/^\d{1,6}$/', $q) !== 1 || (int) $q < 1 || (int) $q > self::MAX_QTY || mb_strlen($t) > 200) {
                $errors['items'] = 'Each item needs a name (up to 160 characters) and a quantity from 1 to ' . self::MAX_QTY . '. Notes can be up to 200 characters.';
                break;
            }
            $items[] = ['name' => $n, 'qty' => (int) $q, 'note' => $t];
        }
        if ($items === [] && !isset($errors['items'])) {
            $errors['items'] = 'Add at least one item.';
        }
        if (count($items) > self::MAX_ITEMS) {
            $errors['items'] = 'Please list up to ' . self::MAX_ITEMS . ' items. Put the rest in the message.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'ref' => ''];
        }
        $now = $this->now();
        if (!Throttle::hit($this->db, 'quote-user:' . $userId, 5, 86400, $now) || !Throttle::hit($this->db, 'quote-ip:' . $ip, 20, 3600, $now)) {
            return ['errors' => ['form' => 'You have sent several requests today. Please wait, or reply in an existing conversation.'], 'ref' => ''];
        }
        $ref = 'QT-' . strtoupper(bin2hex(random_bytes(4)));
        $this->db->run('INSERT INTO quotes (ref, user_id, org_name, status, needed_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ref, $userId, $org === '' ? null : $org, 'new', $neededBy, $now, $now]);
        $id = (int) ($this->db->one('SELECT id FROM quotes WHERE ref = ?', [$ref])['id'] ?? 0);
        foreach ($items as $it) {
            $this->db->run('INSERT INTO quote_items (quote_id, name, qty, note) VALUES (?, ?, ?, ?)', [$id, $it['name'], $it['qty'], $it['note'] === '' ? null : $it['note']]);
        }
        if ($message !== '') {
            $this->db->run('INSERT INTO quote_messages (quote_id, author_id, author_role, body, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $userId, 'customer', $message, $now]);
        }
        $this->tellStaff($ref, 'A new quote request ' . $ref . ' was sent.');
        return ['errors' => [], 'ref' => $ref];
    }

    /** "2026-12-15" as the unix time at the end of that UTC day, or null. */
    public static function parseDate(string $text): ?int
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return (int) gmmktime(23, 59, 59, (int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return $this->db->all('SELECT q.ref, q.status, q.created_at, q.updated_at, (SELECT COUNT(*) FROM quote_items i WHERE i.quote_id = q.id) AS items FROM quotes q WHERE q.user_id = ? ORDER BY q.updated_at DESC, q.id DESC LIMIT 50', [$userId]);
    }

    /** @return array<string,mixed>|null a quote with items, thread and latest offer, only for its owner */
    public function findForUser(string $ref, int $userId): ?array
    {
        $q = $this->db->one('SELECT * FROM quotes WHERE ref = ? AND user_id = ?', [$ref, $userId]);
        return $q === null ? null : $this->fill($q);
    }

    /** @return array<string,mixed>|null */
    public function adminFind(string $ref): ?array
    {
        $q = $this->db->one('SELECT q.*, u.email AS customer_email, u.name AS customer_name, u.phone AS customer_phone, (SELECT a.name FROM users a WHERE a.id = q.assigned_to) AS assigned_name FROM quotes q JOIN users u ON u.id = q.user_id WHERE q.ref = ?', [$ref]);
        return $q === null ? null : $this->fill($q);
    }

    /** @param array<string,mixed> $q @return array<string,mixed> */
    private function fill(array $q): array
    {
        $id = (int) $q['id'];
        $q['items'] = $this->db->all('SELECT id, name, qty, note FROM quote_items WHERE quote_id = ? ORDER BY id', [$id]);
        $q['messages'] = $this->db->all('SELECT m.author_role, m.body, m.created_at, u.name AS author_name FROM quote_messages m LEFT JOIN users u ON u.id = m.author_id WHERE m.quote_id = ? ORDER BY m.id', [$id]);
        $offer = $this->db->one('SELECT id, total_pesewas, valid_until, note, created_at FROM quote_offers WHERE quote_id = ? ORDER BY id DESC LIMIT 1', [$id]);
        if ($offer !== null) {
            $offer['lines'] = $this->db->all('SELECT name, qty, unit_pesewas FROM quote_offer_lines WHERE offer_id = ? ORDER BY id', [(int) $offer['id']]);
            $offer['expired'] = (int) $offer['valid_until'] < $this->now();
        }
        $q['offer'] = $offer;
        return $q;
    }

    /**
     * Staff quote list with optional filters. Every filter is a bound value.
     *
     * @param array{status?:string,q?:string,page?:int} $f
     * @return array{items:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public function adminList(array $f): array
    {
        $status = array_key_exists($f['status'] ?? '', self::STATUSES) ? (string) $f['status'] : '';
        $q = trim((string) ($f['q'] ?? ''));
        $like = $q === '' ? '' : '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 60)) . '%';
        $params = [$status, $status, $like, $like, $like, $like];
        $row = $this->db->one("SELECT COUNT(*) AS n FROM quotes q JOIN users u ON u.id = q.user_id WHERE (? = '' OR q.status = ?) AND (? = '' OR q.ref LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR q.org_name LIKE ? ESCAPE '!')", $params);
        $total = (int) ($row['n'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min((int) ($f['page'] ?? 1), $pages));
        $items = $this->db->all(
            "SELECT q.ref, q.status, q.org_name, q.needed_by, q.created_at, q.updated_at, u.email, u.name, (SELECT COUNT(*) FROM quote_items i WHERE i.quote_id = q.id) AS items, (SELECT a.name FROM users a WHERE a.id = q.assigned_to) AS assigned_name FROM quotes q JOIN users u ON u.id = q.user_id WHERE (? = '' OR q.status = ?) AND (? = '' OR q.ref LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR q.org_name LIKE ? ESCAPE '!') ORDER BY q.updated_at DESC, q.id DESC LIMIT ? OFFSET ?",
            array_merge($params, [self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE]),
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array{new:int,open:int,quoted:int} figures for the dashboard */
    public function figures(): array
    {
        $n = fn (string $sql): int => (int) (array_values($this->db->one($sql) ?? [0])[0] ?? 0);
        return [
            'new' => $n("SELECT COUNT(*) FROM quotes WHERE status = 'new'"),
            'open' => $n("SELECT COUNT(*) FROM quotes WHERE status IN ('new', 'in_progress')"),
            'quoted' => $n("SELECT COUNT(*) FROM quotes WHERE status = 'quoted'"),
        ];
    }

    /**
     * Add a message. The customer side may only write to their own quote; a closed quote takes no more messages.
     *
     * @return string|null a message when refused
     */
    public function addMessage(string $ref, string $body, string $role, int $authorId): ?string
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            return 'Write a message of up to 2,000 characters.';
        }
        $q = $role === 'customer' ? $this->db->one('SELECT id, status, assigned_to FROM quotes WHERE ref = ? AND user_id = ?', [$ref, $authorId]) : $this->db->one('SELECT id, status, assigned_to FROM quotes WHERE ref = ?', [$ref]);
        if ($q === null) {
            return 'That quote was not found.';
        }
        if ($q['status'] === 'closed') {
            return 'This quote is closed. Start a new request if you need something else.';
        }
        if (!Throttle::hit($this->db, 'quote-msg:' . $authorId, 30, 3600, $this->now())) {
            return 'You are sending messages too quickly. Please wait a little.';
        }
        $this->db->run('INSERT INTO quote_messages (quote_id, author_id, author_role, body, created_at) VALUES (?, ?, ?, ?, ?)', [(int) $q['id'], $authorId, $role, $body, $this->now()]);
        $this->touch((int) $q['id'], $role === 'staff', (string) $q['status']);
        if ($role === 'customer') {
            $this->tellStaff($ref, 'The customer replied on quote ' . $ref . '.', $q['assigned_to'] === null ? null : (int) $q['assigned_to']);
        } else {
            $this->tellCustomer((int) $q['id'], 'There is a new reply on your quote ' . $ref . '.');
        }
        return null;
    }

    /**
     * Staff move a quote between new, in progress and closed, or take it on. Sent, accepted and declined are only ever set by
     * making an offer or by the customer.
     *
     * @return string|null a message when refused
     */
    public function setStatus(string $ref, string $status, int $staffId): ?string
    {
        if (!in_array($status, ['in_progress', 'closed'], true)) {
            return 'That status cannot be set by hand.';
        }
        $q = $this->db->one('SELECT id, status FROM quotes WHERE ref = ?', [$ref]);
        if ($q === null) {
            return 'That quote was not found.';
        }
        if ($q['status'] === 'closed' && $status === 'in_progress') {
            return 'A closed quote cannot be reopened. Ask the customer to start a new request.';
        }
        if ($status === 'in_progress' && in_array($q['status'], ['accepted', 'declined'], true)) {
            return 'This quote already has an answer.';
        }
        $this->db->run('UPDATE quotes SET status = ?, assigned_to = COALESCE(assigned_to, ?), updated_at = ? WHERE id = ?', [$status, $staffId, $this->now(), (int) $q['id']]);
        $this->system((int) $q['id'], $status === 'closed' ? 'Closed by our team.' : 'Our team is working on this quote.');
        return null;
    }

    /**
     * Send a priced offer. Every item needs a unit price; the total is worked out here.
     *
     * @param array<int,string> $unitPrices item id => cedis text
     * @return string|null a message when refused
     */
    public function createOffer(string $ref, array $unitPrices, string $validUntil, string $note, int $staffId): ?string
    {
        $q = $this->db->one('SELECT id, status FROM quotes WHERE ref = ?', [$ref]);
        if ($q === null) {
            return 'That quote was not found.';
        }
        if (!in_array($q['status'], ['new', 'in_progress', 'quoted'], true)) {
            return 'This quote is ' . strtolower(self::STATUSES[$q['status']]) . ' and cannot take a new offer.';
        }
        $until = self::parseDate(trim($validUntil));
        if ($until === null || $until < $this->now() || $until > $this->now() + 91 * 86400) {
            return 'Choose an offer end date within the next 90 days, such as 2026-10-31.';
        }
        $note = trim($note);
        if (mb_strlen($note) > 500) {
            return 'Use 500 characters or fewer in the note.';
        }
        $items = $this->db->all('SELECT id, name, qty FROM quote_items WHERE quote_id = ? ORDER BY id', [(int) $q['id']]);
        $lines = [];
        $total = 0;
        foreach ($items as $it) {
            $p = CatalogueAdmin::parsePrice((string) ($unitPrices[(int) $it['id']] ?? ''));
            if ($p === null || $p > self::MAX_UNIT) {
                return 'Give every item a price such as 45.00: "' . $it['name'] . '" is missing or not valid.';
            }
            $lines[] = ['id' => (int) $it['id'], 'name' => (string) $it['name'], 'qty' => (int) $it['qty'], 'unit' => $p];
            $total += $p * (int) $it['qty'];
        }
        if ($total > 4000000000) {
            return 'That total is too large.';
        }
        $now = $this->now();
        $this->db->run('INSERT INTO quote_offers (quote_id, total_pesewas, valid_until, note, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)', [(int) $q['id'], $total, $until, $note === '' ? null : $note, $staffId, $now]);
        $offerId = (int) ($this->db->one('SELECT MAX(id) AS id FROM quote_offers WHERE quote_id = ?', [(int) $q['id']])['id'] ?? 0);
        foreach ($lines as $l) {
            $this->db->run('INSERT INTO quote_offer_lines (offer_id, item_id, name, qty, unit_pesewas) VALUES (?, ?, ?, ?, ?)', [$offerId, $l['id'], $l['name'], $l['qty'], $l['unit']]);
        }
        $this->db->run('UPDATE quotes SET status = ?, assigned_to = COALESCE(assigned_to, ?), updated_at = ? WHERE id = ?', ['quoted', $staffId, $now, (int) $q['id']]);
        $this->system((int) $q['id'], 'Our team sent a quote for ' . money($total) . ', valid until ' . gmdate('d M Y', $until) . '.');
        $this->tellCustomer((int) $q['id'], 'Your quote ' . $ref . ' is ready: ' . money($total) . ', valid until ' . gmdate('d M Y', $until) . '.');
        return null;
    }

    /**
     * The customer accepts or declines the latest offer. Only while the quote is "quote sent" and the offer has not expired.
     *
     * @return string|null a message when refused
     */
    public function answer(string $ref, int $userId, bool $accept): ?string
    {
        $q = $this->db->one('SELECT id, status FROM quotes WHERE ref = ? AND user_id = ?', [$ref, $userId]);
        if ($q === null) {
            return 'That quote was not found.';
        }
        if ($q['status'] !== 'quoted') {
            return 'There is no open offer to answer.';
        }
        $offer = $this->db->one('SELECT id, valid_until FROM quote_offers WHERE quote_id = ? ORDER BY id DESC LIMIT 1', [(int) $q['id']]);
        if ($offer === null || (int) $offer['valid_until'] < $this->now()) {
            return 'That offer has expired. Ask us for a new one.';
        }
        $now = $this->now();
        if ($accept) {
            $this->db->run("UPDATE quotes SET status = 'accepted', accepted_offer_id = ?, accepted_at = ?, updated_at = ? WHERE id = ? AND status = 'quoted'", [(int) $offer['id'], $now, $now, (int) $q['id']]);
        } else {
            $this->db->run("UPDATE quotes SET status = 'declined', updated_at = ? WHERE id = ? AND status = 'quoted'", [$now, (int) $q['id']]);
        }
        $this->system((int) $q['id'], $accept ? 'The customer accepted the quote.' : 'The customer declined the quote.');
        $this->tellStaff($ref, 'Quote ' . $ref . ' was ' . ($accept ? 'accepted' : 'declined') . ' by the customer.');
        return null;
    }

    /** A staff reply moves a brand-new quote to "in progress". Every message refreshes the "last updated" time. */
    private function touch(int $quoteId, bool $staffReplied, string $status): void
    {
        $this->db->run('UPDATE quotes SET updated_at = ?, status = ? WHERE id = ?', [$this->now(), $staffReplied && $status === 'new' ? 'in_progress' : $status, $quoteId]);
    }

    private function system(int $quoteId, string $text): void
    {
        $this->db->run("INSERT INTO quote_messages (quote_id, author_id, author_role, body, created_at) VALUES (?, NULL, 'system', ?, ?)", [$quoteId, $text, $this->now()]);
    }

    /** Email the owners and the active sales staff (or just the assigned person plus owners). A failure never stops the action. */
    private function tellStaff(string $ref, string $line, ?int $assigned = null): void
    {
        try {
            $mailer = $this->mailer ?? Mailer::fromEnv();
            $rows = $this->db->all("SELECT email FROM users WHERE is_active = 1 AND (role = 'owner' OR (role = 'staff' AND staff_role = 'sales') OR id = ?)", [$assigned ?? 0]);
            $url = rtrim(Env::get('APP_URL', '') ?? '', '/') . '/admin/quotes/' . rawurlencode($ref);
            foreach ($rows as $r) {
                $mailer->send((string) $r['email'], 'Belis Bell quote ' . $ref, $line . "\n\nOpen it here: " . $url);
            }
        } catch (\Throwable $e) {
            Logger::error('Quote email to staff failed', ['type' => $e::class]);
        }
    }

    private function tellCustomer(int $quoteId, string $line): void
    {
        try {
            $q = $this->db->one('SELECT q.ref, u.email, u.name FROM quotes q JOIN users u ON u.id = q.user_id WHERE q.id = ?', [$quoteId]);
            if ($q === null) {
                return;
            }
            $url = rtrim(Env::get('APP_URL', '') ?? '', '/') . '/quotes/' . rawurlencode((string) $q['ref']);
            ($this->mailer ?? Mailer::fromEnv())->send((string) $q['email'], 'Your Belis Bell quote ' . $q['ref'], "Hello {$q['name']},\n\n{$line}\n\nYou can read it and reply here: {$url}\n\nBelis Bell will never ask for your password or a code by phone, WhatsApp or email.");
        } catch (\Throwable $e) {
            Logger::error('Quote email to customer failed', ['type' => $e::class]);
        }
    }
}
