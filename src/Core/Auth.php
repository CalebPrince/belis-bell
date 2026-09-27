<?php
declare(strict_types=1);

namespace Belis\Core;

/**
 * Who is signed in. A person is signed in only after password and emailed code (see Domain\Accounts); the
 * session then holds their id, and every request re-reads the account, so a removed or deactivated person, or
 * one whose role changed, loses access at once (CTL-SESS-001, CTL-ORG-001). Staff sessions are short: 15 minutes
 * idle, 4 hours in total, no remember-me (CTL-AUTH-002). Authentication never implies authorisation: policies
 * and controllers must still check the specific object.
 *
 * PREVIEW MODE: so the signed-in pages can be looked at without an account, a developer can set
 * PREVIEW_LOGIN=customer|staff|owner in a LOCAL environment with mock data. The environment guard refuses to
 * start with PREVIEW_LOGIN set anywhere else, and tests cover both. The person shown is a mock person.
 */
final class Auth
{
    public const MOCK_CUSTOMER = ['name' => 'Prince Caleb', 'email' => 'you@example.test', 'phone' => '+233 24 123 4567', 'member_since' => '2026-08-02', 'type' => 'Individual'];
    public const MOCK_STAFF = ['name' => 'Prince Caleb', 'email' => 'admin@example.test', 'role' => 'Owner'];
    public const STAFF_IDLE_SECONDS = 900;
    public const STAFF_ABSOLUTE_SECONDS = 14400;

    /** @var array<string,string>|null|false false = not looked up yet */
    private static array|null|false $cache = false;

    /** The preview role, or null when preview mode is off. Only ever set in local mock mode. */
    public static function previewRole(): ?string
    {
        $role = Env::get('PREVIEW_LOGIN', '') ?? '';
        if ($role === '' || (Env::get('APP_ENV', 'production') ?? 'production') !== 'local' || !Env::bool('MOCK_DATA')) {
            return null;
        }
        return in_array($role, ['customer', 'staff', 'owner'], true) ? $role : null;
    }

    /** Record a completed sign-in (after the code). Rotates the session id. */
    public static function signIn(int $uid, string $role, ?string $staffRole = null): void
    {
        Session::rotate();
        $now = time();
        $_SESSION['auth'] = ['uid' => $uid, 'role' => $role, 'staff_role' => $staffRole ?? '', 'started' => $now, 'seen' => $now];
        unset($_SESSION['pending']);
        self::$cache = false;
    }

    public static function signOut(): void
    {
        Session::start();
        Session::destroy();
        self::$cache = false;
    }

    /** Forget the per-request lookup. Tests and sign-out use it. */
    public static function reset(): void
    {
        self::$cache = false;
    }

    /** @return array<string,string>|null */
    public static function customer(): ?array
    {
        if (self::previewRole() !== null) {
            return self::MOCK_CUSTOMER;
        }
        $u = self::current();
        return $u !== null && $u['role'] === 'customer' ? $u : null;
    }

    /** @return array<string,string>|null */
    public static function staff(): ?array
    {
        $r = self::previewRole();
        if ($r === 'staff' || $r === 'owner') {
            return self::MOCK_STAFF;
        }
        $u = self::current();
        return $u !== null && in_array($u['role'], ['staff', 'owner'], true) ? $u : null;
    }

    /** What each staff role may open. The owner may open everything. */
    public const ROLE_AREAS = ['content' => ['content'], 'fulfilment' => ['orders', 'fulfilment'], 'sales' => ['orders']];

    /**
     * May the signed-in staff member or owner use this area (content, orders or fulfilment)? A staff account with no
     * role can use nothing. The local preview people can look at everything and change nothing.
     */
    public static function can(string $area): bool
    {
        $r = self::previewRole();
        if ($r === 'staff' || $r === 'owner') {
            return true;
        }
        $u = self::current();
        if ($u === null) {
            return false;
        }
        if ($u['role'] === 'owner') {
            return true;
        }
        return $u['role'] === 'staff' && in_array($area, self::ROLE_AREAS[$u['staff_role']] ?? [], true);
    }

    /** @return array<string,string>|null */
    public static function owner(): ?array
    {
        if (self::previewRole() === 'owner') {
            return self::MOCK_STAFF;
        }
        $u = self::current();
        return $u !== null && $u['role'] === 'owner' ? $u : null;
    }

    /** @return array<string,string>|null */
    private static function current(): ?array
    {
        if (self::$cache !== false) {
            return self::$cache;
        }
        if (!Session::hasCookie()) {
            return self::$cache = null;
        }
        Session::start();
        $a = $_SESSION['auth'] ?? null;
        if (!is_array($a) || !isset($a['uid'], $a['role'], $a['started'], $a['seen'])) {
            return self::$cache = null;
        }
        $now = time();
        $staff = $a['role'] !== 'customer';
        $idle = $staff ? self::STAFF_IDLE_SECONDS : Session::IDLE_SECONDS;
        $total = $staff ? self::STAFF_ABSOLUTE_SECONDS : Session::ABSOLUTE_SECONDS;
        if ($now - (int) $a['seen'] > $idle || $now - (int) $a['started'] > $total) {
            unset($_SESSION['auth']);
            return self::$cache = null;
        }
        try {
            $row = Db::fromEnv()->one('SELECT id, email, name, phone, role, staff_role, is_active, created_at, pw_changed_at FROM users WHERE id = ?', [(int) $a['uid']]);
        } catch (\Throwable) {
            return self::$cache = null; // fail closed when the database cannot answer
        }
        if ($row === null || (int) $row['is_active'] !== 1 || $row['role'] !== $a['role'] || (string) ($row['staff_role'] ?? '') !== (string) ($a['staff_role'] ?? '') || (int) ($row['pw_changed_at'] ?? 0) > (int) $a['started']) {
            unset($_SESSION['auth']);
            return self::$cache = null;
        }
        $_SESSION['auth']['seen'] = $now;
        return self::$cache = [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'role' => (string) $row['role'],
            'staff_role' => (string) ($row['staff_role'] ?? ''),
            'member_since' => gmdate('Y-m-d', (int) $row['created_at']),
            'type' => 'Individual',
        ];
    }
}
