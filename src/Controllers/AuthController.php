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
        Auth::signOut();
        Flash::notice('You have been signed out.');
        return Response::redirect('/');
    }

    /** @param array<string,string> $params */
    public function staffSignOut(Request $request, array $params = []): Response
    {
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
            return $this->signInPage($staff, 'Too many attempts. Please wait a few minutes and try again.', $email, 429);
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
                $user = Db::fromEnv()->one('SELECT id, role, is_active FROM users WHERE id = ?', [$pending['uid']]);
                if ($user !== null && (int) $user['is_active'] === 1 && (in_array($user['role'], ['staff', 'owner'], true)) === $staff) {
                    Auth::signIn((int) $user['id'], (string) $user['role']);
                    return Response::redirect($staff ? '/admin' : '/account');
                }
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
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
        return Response::redirect($staff ? '/admin/verify' : '/account/verify');
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

    public static function hasPending(bool $staff): bool
    {
        return self::pending($staff) !== null;
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
        $purpose = in_array($p['purpose'] ?? null, ['login', 'verify_email'], true) ? (string) $p['purpose'] : null;
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
