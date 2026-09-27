<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\Session;
use Belis\Core\View;
use Belis\Domain\Accounts;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Mailer;

/**
 * The forms behind sign-in, register, code entry, resend and sign-out for customers and staff. Pages that show
 * the forms live in AccountController and AdminController. Every route here is public because the person is not
 * signed in yet, and every POST is CSRF-checked by the router. Answers never say whether an address has an account.
 */
final class AuthController
{
    private const PENDING_SECONDS = 900;
    private const WRONG = 'That did not work. Check your details and try again.';

    /** @param array<string,string> $params */
    public function customerSignIn(Request $request, array $params = []): Response
    {
        return $this->signIn($request, false);
    }

    /** @param array<string,string> $params */
    public function staffSignIn(Request $request, array $params = []): Response
    {
        return $this->signIn($request, true);
    }

    /** @param array<string,string> $params */
    public function register(Request $request, array $params = []): Response
    {
        try {
            $result = $this->accounts()->register($request->post, $request->ip);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($result['uid'] === null) {
            $old = ['name' => (string) ($request->post['name'] ?? ''), 'email' => (string) ($request->post['email'] ?? ''), 'phone' => (string) ($request->post['phone'] ?? '')];
            return Response::html(View::render('pages/auth/register', ['title' => 'Create an account | Belis Bell', 'errors' => $result['errors'], 'old' => $old]), 422);
        }
        $this->audit('auth.register', $this->who((string) ($request->post['email'] ?? '')), null, $request->ip);
        $this->setPending($result['uid'], 'verify_email', false);
        return Response::redirect('/account/verify');
    }

    /** @param array<string,string> $params */
    public function customerVerify(Request $request, array $params = []): Response
    {
        return $this->verify($request, false);
    }

    /** @param array<string,string> $params */
    public function staffVerify(Request $request, array $params = []): Response
    {
        return $this->verify($request, true);
    }

    /** @param array<string,string> $params */
    public function customerResend(Request $request, array $params = []): Response
    {
        return $this->resend(false);
    }

    /** @param array<string,string> $params */
    public function staffResend(Request $request, array $params = []): Response
    {
        return $this->resend(true);
    }

    /** @param array<string,string> $params */
    public function customerSignOut(Request $request, array $params = []): Response
    {
        $this->audit('auth.signout', 'customer', isset(Auth::customer()['id']) ? (int) Auth::customer()['id'] : null, $request->ip);
        Auth::signOut();
        Flash::notice('You have been signed out.');
        return Response::redirect('/');
    }

    /** @param array<string,string> $params */
    public function staffSignOut(Request $request, array $params = []): Response
    {
        $this->audit('auth.signout', 'staff', isset(Auth::staff()['id']) ? (int) Auth::staff()['id'] : null, $request->ip);
        Auth::signOut();
        Flash::notice('You have been signed out.');
        return Response::redirect('/admin/sign-in');
    }

    private function signIn(Request $request, bool $staff): Response
    {
        $email = is_string($request->post['email'] ?? null) ? mb_substr($request->post['email'], 0, 190) : '';
        $password = is_string($request->post['password'] ?? null) ? mb_substr($request->post['password'], 0, 200) : '';
        try {
            $result = $this->accounts()->signIn($email, $password, $staff, $request->ip);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($result['throttled']) {
            $this->audit('auth.throttled', $this->who($email), null, $request->ip);
            return $this->signInPage($staff, 'Too many attempts. Please wait a few minutes and try again.', $email, 429);
        }
        if ($result['uid'] === 0) {
            $this->audit('auth.signin_failed', $this->who($email), null, $request->ip);
            if ($staff) {
                $this->alertOwners($this->who($email), $request->ip);
            }
        }
        // The same next step whether or not the details were right: the code page.
        $this->setPending($result['uid'], $result['purpose'], $staff);
        return Response::redirect($staff ? '/admin/verify' : '/account/verify');
    }

    private function verify(Request $request, bool $staff): Response
    {
        $pending = self::pending($staff);
        $home = $staff ? '/admin/sign-in' : '/account/sign-in';
        if ($pending === null) {
            return Response::redirect($home);
        }
        if ($pending['purpose'] === 'reset') {
            return Response::redirect($staff ? '/admin/reset' : '/account/reset');
        }
        $code = is_string($request->post['code'] ?? null) ? preg_replace('/\s+/', '', $request->post['code']) : '';
        try {
            $accounts = $this->accounts();
            $ok = $accounts->checkCode($pending['uid'], $pending['purpose'], (string) $code, $request->ip);
            if ($ok && $pending['purpose'] === 'verify_email') {
                $accounts->markVerified($pending['uid']);
                unset($_SESSION['pending']);
                Flash::notice('Your email address is confirmed. Sign in to continue.');
                return Response::redirect($home);
            }
            if ($ok) {
                $user = Db::fromEnv()->one('SELECT id, role, staff_role, is_active FROM users WHERE id = ?', [$pending['uid']]);
                if ($user !== null && (int) $user['is_active'] === 1 && (in_array($user['role'], ['staff', 'owner'], true)) === $staff) {
                    Auth::signIn((int) $user['id'], (string) $user['role'], $user['staff_role'] === null ? null : (string) $user['staff_role']);
                    $this->audit('auth.signin', $staff ? 'staff' : 'customer', (int) $user['id'], $request->ip);
                    return Response::redirect($staff ? '/admin' : '/account');
                }
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        $this->audit('auth.code_failed', $staff ? 'staff' : 'customer', $pending['uid'] > 0 ? $pending['uid'] : null, $request->ip);
        return Response::html(View::render('pages/auth/verify', [
            'title' => 'Enter your code | Belis Bell',
            'admin' => $staff,
            'error' => 'That code did not work. It may have expired or been used. You can ask for a new one.',
        ]), 422);
    }

    private function resend(bool $staff): Response
    {
        $pending = self::pending($staff);
        if ($pending === null) {
            return Response::redirect($staff ? '/admin/sign-in' : '/account/sign-in');
        }
        try {
            if ($pending['uid'] > 0) {
                $this->accounts()->issueCode($pending['uid'], $pending['purpose']);
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Flash::notice('If the details were right, a new code is on its way. Only the newest code works.');
        if ($pending['purpose'] === 'reset') {
            return Response::redirect($staff ? '/admin/reset' : '/account/reset');
        }
        return Response::redirect($staff ? '/admin/verify' : '/account/verify');
    }

    /** @param array<string,string> $params */
    public function customerForgot(Request $request, array $params = []): Response
    {
        return $this->forgot($request, false);
    }

    /** @param array<string,string> $params */
    public function staffForgot(Request $request, array $params = []): Response
    {
        return $this->forgot($request, true);
    }

    /** @param array<string,string> $params */
    public function customerReset(Request $request, array $params = []): Response
    {
        return $this->reset($request, false);
    }

    /** @param array<string,string> $params */
    public function staffReset(Request $request, array $params = []): Response
    {
        return $this->reset($request, true);
    }

    private function forgot(Request $request, bool $staff): Response
    {
        $email = is_string($request->post['email'] ?? null) ? mb_substr($request->post['email'], 0, 190) : '';
        try {
            $uid = $this->accounts()->requestReset($email, $staff, $request->ip);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        $this->audit('auth.reset_requested', $this->who($email), $uid > 0 ? $uid : null, $request->ip);
        // The same next step whether or not there is an account.
        $this->setPending($uid, 'reset', $staff);
        return Response::redirect($staff ? '/admin/reset' : '/account/reset');
    }

    private function reset(Request $request, bool $staff): Response
    {
        $pending = self::pending($staff);
        $home = $staff ? '/admin/sign-in' : '/account/sign-in';
        if ($pending === null || $pending['purpose'] !== 'reset') {
            return Response::redirect($home);
        }
        $str = static fn (string $k): string => is_string($request->post[$k] ?? null) ? mb_substr($request->post[$k], 0, 200) : '';
        $code = preg_replace('/\s+/', '', $str('code')) ?? '';
        try {
            $error = $this->accounts()->resetPassword($pending['uid'], $code, $str('password'), $str('password2'), $request->ip);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($error !== null) {
            $this->audit('auth.reset_failed', $staff ? 'staff' : 'customer', $pending['uid'] > 0 ? $pending['uid'] : null, $request->ip);
            return Response::html(View::render('pages/auth/reset', ['title' => 'Choose a new password | Belis Bell', 'admin' => $staff, 'error' => $error]), 422);
        }
        $this->audit('auth.reset', $staff ? 'staff' : 'customer', $pending['uid'], $request->ip);
        unset($_SESSION['pending']);
        Flash::notice('Your password was changed. Sign in with the new one.');
        return Response::redirect($home);
    }

    /** Write a sign-in event to the audit log. A failure to log is reported but never blocks the person. */
    private function audit(string $action, string $target, ?int $uid, string $ip): void
    {
        try {
            Audit::add(Db::fromEnv(), $uid, $action, $target, $ip);
        } catch (\Throwable $e) {
            Logger::error('Audit entry could not be written', ['type' => $e::class]);
        }
    }

    /** A short fingerprint of an email address, so the audit log can group attempts without holding the address. */
    private function who(string $email): string
    {
        return 'acct:' . substr(hash('sha256', strtolower(trim($email))), 0, 12);
    }

    /** Email the owners once when one staff address has 5 failed sign-ins in 15 minutes (MON-002). */
    private function alertOwners(string $who, string $ip): void
    {
        try {
            $db = Db::fromEnv();
            $n = (int) ($db->one("SELECT COUNT(*) AS n FROM audit_log WHERE action = 'auth.signin_failed' AND target = ? AND created_at > ?", [$who, time() - 900])['n'] ?? 0);
            if ($n !== 5) {
                return;
            }
            $mailer = Mailer::fromEnv();
            foreach ($db->all("SELECT email FROM users WHERE role = 'owner' AND is_active = 1") as $o) {
                $mailer->send((string) $o['email'], 'Repeated failed staff sign-ins', "There were 5 failed staff sign-ins for the same address ({$who}) in 15 minutes, the latest from {$ip}.\n\nIf you do not recognise this, someone may be guessing a password. Check the activity list under Settings, and reset the password if unsure.");
            }
        } catch (\Throwable $e) {
            Logger::error('Failed-sign-in alert could not be sent', ['type' => $e::class]);
        }
    }

    private function signInPage(bool $staff, string $error, string $email, int $status): Response
    {
        return Response::html(View::render('pages/auth/sign-in', [
            'title' => ($staff ? 'Staff sign in' : 'Sign in') . ' | Belis Bell',
            'admin' => $staff,
            'error' => $error,
            'oldEmail' => $email,
        ]), $status);
    }

    private function setPending(int $uid, string $purpose, bool $staff): void
    {
        Session::rotate();
        $_SESSION['pending'] = ['uid' => $uid, 'purpose' => $purpose, 'staff' => $staff, 'at' => time()];
    }

    public static function hasPending(bool $staff, ?string $purpose = null): bool
    {
        $p = self::pending($staff);
        return $p !== null && ($purpose === null || $p['purpose'] === $purpose);
    }

    /** @return array{uid:int,purpose:string}|null */
    private static function pending(bool $staff): ?array
    {
        if (!Session::hasCookie()) {
            return null;
        }
        Session::start();
        $p = $_SESSION['pending'] ?? null;
        if (!is_array($p) || ($p['staff'] ?? null) !== $staff || time() - (int) ($p['at'] ?? 0) > self::PENDING_SECONDS) {
            return null;
        }
        $purpose = in_array($p['purpose'] ?? null, ['login', 'verify_email', 'reset'], true) ? (string) $p['purpose'] : null;
        return $purpose === null ? null : ['uid' => (int) ($p['uid'] ?? 0), 'purpose' => $purpose];
    }

    private function accounts(): Accounts
    {
        return new Accounts(Db::fromEnv(), Mailer::fromEnv());
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Account request failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
