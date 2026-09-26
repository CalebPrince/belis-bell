<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Db;

/**
 * Append-only audit log (CTL-AUDIT-001). This class can only add and read entries, and the database refuses updates
 * and deletes (see migration 007). It records who, what, when and from where. It must never be given a secret value.
 */
final class Audit
{
    /** $detail is for field names and short notes only: never a secret or a customer's personal data. */
    public static function add(Db $db, ?int $userId, string $action, string $target, string $ip, ?int $now = null, ?string $detail = null): void
    {
        $db->run(
            'INSERT INTO audit_log (user_id, action, target, detail, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, mb_substr($action, 0, 60), mb_substr($target, 0, 80), $detail === null ? null : mb_substr($detail, 0, 255), mb_substr($ip, 0, 45), $now ?? time()],
        );
    }

    public const AREAS = ['' => 'Everything', 'auth.' => 'Sign-ins and passwords', 'confirm.' => 'Confirmation codes', 'order.' => 'Orders', 'price.' => 'Prices', 'product.' => 'Products', 'size.' => 'Sizes', 'category.' => 'Categories', 'subcategory.' => 'Subcategories', 'settings.' => 'Settings', 'staff.' => 'Staff accounts', 'audit.' => 'Audit log views'];
    public const PAGE_SIZE = 50;

    /**
     * One page of the log for the owner, optionally limited to one area (a key of AREAS).
     *
     * @return array{items:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public static function page(Db $db, string $area, int $page): array
    {
        $like = $area === '' || !array_key_exists($area, self::AREAS) ? '' : $area . '%';
        $total = (int) ($db->one("SELECT COUNT(*) AS n FROM audit_log WHERE (? = '' OR action LIKE ?)", [$like, $like])['n'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $items = $db->all("SELECT a.action, a.target, a.detail, a.ip, a.created_at, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE (? = '' OR a.action LIKE ?) ORDER BY a.id DESC LIMIT ? OFFSET ?", [$like, $like, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE]);
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return list<array<string,mixed>> newest first */
    public static function recent(Db $db, int $limit = 20): array
    {
        return $db->all('SELECT a.action, a.target, a.detail, a.ip, a.created_at, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ?', [max(1, min($limit, 100))]);
    }
}
