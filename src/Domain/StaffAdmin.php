<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;
use Belis\Support\Mailer;
use Belis\Support\Validator;

/**
 * Staff accounts, managed by the owner (CTL-AUTH-002, CTL-ORG-001). The owner adds staff and can switch a staff
 * account off or on. A new staff member gets no password from us: they set their own with the emailed reset code.
 * Owners are created only with bin/create-staff.php, and an owner can never be switched off here.
 */
final class StaffAdmin
{
    public function __construct(private readonly Db $db, private readonly Mailer $mailer, private readonly ?int $clock = null)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->all("SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at, (SELECT MAX(a.created_at) FROM audit_log a WHERE a.user_id = u.id AND a.action = 'auth.signin') AS last_signin FROM users u WHERE u.role IN ('staff', 'owner') ORDER BY u.role DESC, u.name");
    }

    /**
     * @param array<string,mixed> $in name, email
     * @return array{errors:array<string,string>,id:int}
     */
    public function create(array $in, string $siteUrl): array
    {
        $errors = Validator::check($in, ['name' => 'required|max:120', 'email' => 'required|email|max:190']);
        $email = strtolower(trim(is_string($in['email'] ?? null) ? $in['email'] : ''));
        if (!isset($errors['email']) && $this->db->one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            $errors['email'] = 'That address already has an account.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => 0];
        }
        // A random password nobody knows: the person sets a real one with the emailed reset code.
        $id = (new Accounts($this->db, $this->mailer, $this->clock))->createVerified($email, trim((string) $in['name']), 'n/a', bin2hex(random_bytes(24)), 'staff');
        try {
            $this->mailer->send($email, 'Your Belis Bell staff account', "A staff account was created for you at Belis Bell.\n\nTo choose your password, open " . rtrim($siteUrl, '/') . "/admin/forgot, enter this email address, and use the 6 digit code we send you.\n\nBelis Bell will never ask for your password.");
        } catch (\Throwable) {
            // The account exists; the owner can tell the person to use Forgot your password.
        }
        return ['errors' => [], 'id' => $id];
    }

    /** @return string|null a message when refused */
    public function setActive(int $id, bool $active, int $actorId): ?string
    {
        $u = $this->db->one('SELECT id, role FROM users WHERE id = ?', [$id]);
        if ($u === null || $u['role'] !== 'staff') {
            return 'Only staff accounts can be switched on or off here.';
        }
        if ($id === $actorId) {
            return 'You cannot switch off your own account.';
        }
        $this->db->run('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
        return null;
    }
}
