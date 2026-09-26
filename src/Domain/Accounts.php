<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Throttle;
use Belis\Support\CommonPasswords;
use Belis\Support\Mailer;
use Belis\Support\Validator;

/**
 * Accounts and emailed one-time codes (CTL-AUTH-001 customers, CTL-AUTH-002 staff).
 * A correct password alone never signs anyone in: it only sends a code, and the code is what completes sign-in.
 * Codes are 6 digits, single use, limited to 5 tries, and only an HMAC of the code is stored. Answers to the
 * visitor are uniform, so they do not reveal whether an address has an account.
 */
final class Accounts
{
    public const MAX_TRIES = 5;
    public const CUSTOMER_CODE_SECONDS = 600;
    public const STAFF_CODE_SECONDS = 300;

    private static ?string $dummyHash = null;

    public function __construct(private readonly Db $db, private readonly Mailer $mailer, private readonly ?int $clock = null)
    {
    }

    private function now(): int
    {
        return $this->clock ?? time();
    }

    /** @return string|null a message when the password is not acceptable */
    public static function passwordProblem(string $password, string $email = '', string $name = ''): ?string
    {
        $len = mb_strlen($password);
        if ($len < 10) {
            return 'Use at least 10 characters.';
        }
        if ($len > 128) {
            return 'Use 128 characters or fewer.';
        }
        if (CommonPasswords::contains($password)) {
            return 'That password is too common. Choose a different one.';
        }
        $local = strtolower(explode('@', $email)[0]);
        $lower = strtolower($password);
        if (strlen($local) >= 4 && str_contains($lower, $local)) {
            return 'Do not use your email address in your password.';
        }
        if (mb_strlen($name) >= 4 && str_contains($lower, strtolower($name))) {
            return 'Do not use your name as your password.';
        }
        return null;
    }

    /**
     * @param array<string,mixed> $in name, email, phone, password, password2
     * @return array{errors:array<string,string>,uid:int|null} uid is null when the form has errors, 0 when the address already had an account
     */
    public function register(array $in, string $ip): array
    {
        $errors = Validator::check($in, ['name' => 'required|max:120', 'email' => 'required|email|max:190', 'phone' => 'required|max:20', 'password' => 'required']);
        $phone = is_string($in['phone'] ?? null) ? trim($in['phone']) : '';
        if (!isset($errors['phone']) && preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone) !== 1) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $email = strtolower(trim(is_string($in['email'] ?? null) ? $in['email'] : ''));
        $name = trim(is_string($in['name'] ?? null) ? $in['name'] : '');
        $password = is_string($in['password'] ?? null) ? $in['password'] : '';
        if (!isset($errors['password'])) {
            $problem = self::passwordProblem($password, $email, $name);
            if ($problem !== null) {
                $errors['password'] = $problem;
            }
        }
        if (!isset($errors['password']) && ($in['password2'] ?? null) !== $password) {
            $errors['password2'] = 'The two passwords do not match.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'uid' => null];
        }
        if (!Throttle::hit($this->db, 'register-ip:' . $ip, 5, 3600, $this->now())) {
            return ['errors' => ['form' => 'Too many attempts. Please try again later.'], 'uid' => null];
        }
        if ($this->db->one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
            $this->mailer->send($email, 'You already have a Belis Bell account', "Someone used this email address to create a Belis Bell account, but it already has one.\n\nIf it was you, sign in instead. If it was not you, you can ignore this email.");
            return ['errors' => [], 'uid' => 0];
        }
        $this->db->run(
            'INSERT INTO users (email, name, phone, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$email, $name, $phone, self::hash($password), 'customer', $this->now()],
        );
        $row = $this->db->one('SELECT id FROM users WHERE email = ?', [$email]);
        $uid = (int) ($row['id'] ?? 0);
        $this->issueCode($uid, 'verify_email');
        return ['errors' => [], 'uid' => $uid];
    }

    /**
     * Step one of sign-in: check the password and send a code. Always returns the same shape, whether or not the
     * details were right. uid 0 means nothing was sent.
     *
     * @return array{uid:int,purpose:string,throttled:bool}
     */
    public function signIn(string $email, string $password, bool $staff, string $ip): array
    {
        $email = strtolower(trim($email));
        $none = ['uid' => 0, 'purpose' => 'login', 'throttled' => false];
        $now = $this->now();
        if (!Throttle::hit($this->db, 'signin-ip:' . $ip, 20, 900, $now) || !Throttle::hit($this->db, 'signin-email:' . $email, 5, 900, $now)) {
            return ['throttled' => true] + $none;
        }
        $user = $email === '' ? null : $this->db->one('SELECT id, password_hash, role, is_active, email_verified_at FROM users WHERE email = ?', [$email]);
        $ok = password_verify($password, $user['password_hash'] ?? self::dummy());
        if ($user === null || !$ok || (int) $user['is_active'] !== 1) {
            return $none;
        }
        $isStaff = in_array($user['role'], ['staff', 'owner'], true);
        if ($isStaff !== $staff) {
            return $none;
        }
        $uid = (int) $user['id'];
        $purpose = $user['email_verified_at'] === null ? 'verify_email' : 'login';
        $this->issueCode($uid, $purpose);
        return ['uid' => $uid, 'purpose' => $purpose, 'throttled' => false];
    }

    /** Send a fresh code (any older unused code stops working). Limited to 3 per 10 minutes per person. */
    public function issueCode(int $uid, string $purpose): bool
    {
        $now = $this->now();
        if (!Throttle::hit($this->db, 'code-send:' . $uid, 3, 600, $now)) {
            return false;
        }
        $user = $this->db->one('SELECT email, role FROM users WHERE id = ?', [$uid]);
        if ($user === null) {
            return false;
        }
        $staff = in_array($user['role'], ['staff', 'owner'], true);
        $ttl = $staff ? self::STAFF_CODE_SECONDS : self::CUSTOMER_CODE_SECONDS;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->run('UPDATE auth_codes SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$now, $uid, $purpose]);
        $this->db->run(
            'INSERT INTO auth_codes (user_id, purpose, code_hash, expires_at, attempts, created_at) VALUES (?, ?, ?, ?, 0, ?)',
            [$uid, $purpose, self::codeHash($uid, $purpose, $code), $now + $ttl, $now],
        );
        $minutes = intdiv($ttl, 60);
        $what = $purpose === 'verify_email' ? 'confirm your email address' : 'finish signing in';
        $this->mailer->send((string) $user['email'], 'Your Belis Bell code', "Your code to {$what} is {$code}.\n\nIt works once and expires in {$minutes} minutes. Belis Bell will never ask you for this code by phone, WhatsApp or email.");
        return true;
    }

    /** Check a code. It is spent when right, and locked after 5 wrong tries. */
    public function checkCode(int $uid, string $purpose, string $code, string $ip): bool
    {
        $now = $this->now();
        if (!Throttle::hit($this->db, 'code-ip:' . $ip, 30, 900, $now)) {
            return false;
        }
        $row = $uid > 0 ? $this->db->one('SELECT id, code_hash, expires_at, attempts FROM auth_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$uid, $purpose]) : null;
        if ($row === null || (int) $row['expires_at'] < $now || (int) $row['attempts'] >= self::MAX_TRIES || preg_match('/^[0-9]{6}$/', $code) !== 1) {
            return false;
        }
        $this->db->run('UPDATE auth_codes SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);
        if (!hash_equals((string) $row['code_hash'], self::codeHash($uid, $purpose, $code))) {
            return false;
        }
        $this->db->run('UPDATE auth_codes SET used_at = ? WHERE id = ?', [$now, (int) $row['id']]);
        return true;
    }

    public function markVerified(int $uid): void
    {
        $this->db->run('UPDATE users SET email_verified_at = ? WHERE id = ? AND email_verified_at IS NULL', [$this->now(), $uid]);
    }

    /** Create a verified account directly (used by the staff creation script only). */
    public function createVerified(string $email, string $name, string $phone, string $password, string $role): int
    {
        $email = strtolower(trim($email));
        $this->db->run(
            'INSERT INTO users (email, name, phone, password_hash, role, is_active, email_verified_at, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
            [$email, trim($name), trim($phone), self::hash($password), $role, $this->now(), $this->now()],
        );
        return (int) ($this->db->one('SELECT id FROM users WHERE email = ?', [$email])['id'] ?? 0);
    }

    private static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    /** A real hash to compare against when the address is unknown, so both cases take about the same time. */
    private static function dummy(): string
    {
        return self::$dummyHash ??= self::hash('not-a-real-password-' . bin2hex(random_bytes(8)));
    }

    private static function codeHash(int $uid, string $purpose, string $code): string
    {
        $pepper = Env::get('AUTH_PEPPER', '') ?? '';
        if (strlen($pepper) < 32) {
            throw new \RuntimeException('AUTH_PEPPER is missing or too short');
        }
        return hash_hmac('sha256', $uid . '|' . $purpose . '|' . $code, $pepper);
    }
}
