<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Fixed-window rate limit kept in the database (CTL-AUTH-001). The key is hashed before it is stored, so no
 * email address or IP is kept in the table. Returns false once the limit for the window is used up.
 */
final class Throttle
{
    public static function hit(Db $db, string $key, int $limit, int $windowSeconds, int $now): bool
    {
        $hash = hash('sha256', $key);
        $row = $db->one('SELECT hits, window_start FROM throttle WHERE key_hash = ?', [$hash]);
        if ($row === null) {
            $db->run('INSERT INTO throttle (key_hash, hits, window_start) VALUES (?, 1, ?)', [$hash, $now]);
            return true;
        }
        if ($now - (int) $row['window_start'] >= $windowSeconds) {
            $db->run('UPDATE throttle SET hits = 1, window_start = ? WHERE key_hash = ?', [$now, $hash]);
            return true;
        }
        if ((int) $row['hits'] >= $limit) {
            return false;
        }
        $db->run('UPDATE throttle SET hits = hits + 1 WHERE key_hash = ?', [$hash]);
        return true;
    }
}
