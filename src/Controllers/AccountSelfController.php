<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\Session;
use Belis\Domain\Accounts;
use Belis\Domain\Addresses;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Mailer;

/** A customer's own details, password and saved addresses. Everything is limited to the signed-in person. */
final class AccountSelfController
{
    /** @param array<string,string> $params */
    public function details(Request $request, array $params = []): Response
    {
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/account');
        }
        try {
            $errors = (new Accounts(Db::fromEnv(), Mailer::fromEnv()))->updateDetails($uid, $request->post);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Flash::notice($errors === [] ? 'Your details were saved.' : 'Not saved. ' . implode(' ', $errors));
        return Response::redirect('/account#details');
    }

    /** @param array<string,string> $params */
    public function password(Request $request, array $params = []): Response
    {
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/account');
        }
        $str = static fn (string $k): string => is_string($request->post[$k] ?? null) ? mb_substr($request->post[$k], 0, 200) : '';
        try {
            $db = Db::fromEnv();
            $error = (new Accounts($db, Mailer::fromEnv()))->changePassword($uid, $str('current'), $str('password'), $str('password2'));
            if ($error === null) {
                Audit::add($db, $uid, 'auth.password_changed', 'customer', $request->ip);
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($error === null) {
            Session::start();
            $_SESSION['auth']['started'] = time(); // this session stays; every older one ends
            Flash::notice('Your password was changed. Any other device is signed out.');
        } else {
            Flash::notice('Password not changed. ' . $error);
        }
        return Response::redirect('/account#details');
    }

    /** @param array<string,string> $params */
    public function addAddress(Request $request, array $params = []): Response
    {
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/account');
        }
        try {
            $errors = (new Addresses(Db::fromEnv()))->add($uid, $request->post);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Flash::notice($errors === [] ? 'Address saved.' : 'Not saved. ' . implode(' ', $errors));
        return Response::redirect('/account#addresses');
    }

    /** @param array<string,string> $params */
    public function deleteAddress(Request $request, array $params = []): Response
    {
        return $this->change($params, static fn (Addresses $a, int $uid, int $id): bool => $a->delete($uid, $id), 'Address removed.');
    }

    /** @param array<string,string> $params */
    public function defaultAddress(Request $request, array $params = []): Response
    {
        return $this->change($params, static fn (Addresses $a, int $uid, int $id): bool => $a->setDefault($uid, $id), 'Default address changed.');
    }

    /** @param array<string,string> $params @param callable(Addresses,int,int):bool $do */
    private function change(array $params, callable $do, string $done): Response
    {
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/account');
        }
        $id = ctype_digit($params['id'] ?? '') ? (int) $params['id'] : 0;
        try {
            $ok = $do(new Addresses(Db::fromEnv()), $uid, $id);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        Flash::notice($ok ? $done : 'That address was not found.');
        return Response::redirect('/account#addresses');
    }

    private function uid(): ?int
    {
        $c = Auth::customer();
        if ($c === null || !isset($c['id'])) {
            Flash::notice('The local preview person cannot change account details.');
            return null;
        }
        return (int) $c['id'];
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Account change failed', ['type' => $e::class]);
        Flash::notice('Something went wrong. Please try again.');
        return Response::redirect('/account');
    }
}
