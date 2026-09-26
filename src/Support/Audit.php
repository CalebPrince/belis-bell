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
    public static function add(Db $db, ?int $userId, string $action, string $target, string $ip, ?int $now = null): void
    {
        $db->run(
            'INSERT INTO audit_log (user_id, action, target, ip, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, mb_substr($action, 0, 60), mb_substr($target, 0, 80), mb_substr($ip, 0, 45), $now ?? time()],
        );
    }

    /** @return list<array<string,mixed>> newest first */
    public static function recent(Db $db, int $limit = 20): array
    {
        return $db->all('SELECT a.action, a.target, a.ip, a.created_at, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ?', [max(1, min($limit, 100))]);
    }
}
