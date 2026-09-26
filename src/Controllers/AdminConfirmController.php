<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Accounts;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Mailer;
use Belis\Support\StepUp;

/** Asks for a fresh emailed code before a sensitive staff action (price changes), then returns to the page. */
final class AdminConfirmController
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $staff = $this->staff();
        if ($staff === null) {
            return Response::redirect('/admin');
        }
        $next = StepUp::safeNext($request->query['next'] ?? null);
        return $this->page($staff, $next, '', 200);
    }

    /** @param array<string,string> $params */
    public function sendCode(Request $request, array $params = []): Response
    {
        $staff = $this->staff();
        if ($staff === null) {
            return Response::redirect('/admin');
        }
        $next = StepUp::safeNext($request->post['next'] ?? null);
        try {
            (new Accounts(Db::fromEnv(), Mailer::fromEnv()))->issueCode((int) $staff['id'], 'stepup');
        } catch (\Throwable $e) {
            Logger::error('Confirmation code failed', ['type' => $e::class]);
            return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
        }
        Flash::notice('We emailed a 6 digit code to your address. It expires in 5 minutes.');
        return Response::redirect('/admin/confirm?next=' . rawurlencode($next));
    }

    /** @param array<string,string> $params */
    public function verify(Request $request, array $params = []): Response
    {
        $staff = $this->staff();
        if ($staff === null) {
            return Response::redirect('/admin');
        }
        $next = StepUp::safeNext($request->post['next'] ?? null);
        $code = is_string($request->post['code'] ?? null) ? preg_replace('/\s+/', '', $request->post['code']) : '';
        try {
            $db = Db::fromEnv();
            $ok = (new Accounts($db, Mailer::fromEnv()))->checkCode((int) $staff['id'], 'stepup', (string) $code, $request->ip);
            Audit::add($db, (int) $staff['id'], $ok ? 'confirm.ok' : 'confirm.failed', 'stepup', $request->ip);
        } catch (\Throwable $e) {
            Logger::error('Confirmation failed', ['type' => $e::class]);
            return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
        }
        if (!$ok) {
            return $this->page($staff, $next, 'That code did not work. It may have expired or been used. Ask for a new one.', 422);
        }
        StepUp::mark((int) $staff['id']);
        return Response::redirect($next);
    }

    /** @return array<string,string>|null */
    private function staff(): ?array
    {
        $s = Auth::staff();
        if ($s === null || !isset($s['id'])) {
            Flash::notice('Confirmation needs a real staff account. The local preview person cannot use it.');
            return null;
        }
        return $s;
    }

    /** @param array<string,string> $staff */
    private function page(array $staff, string $next, string $error, int $status): Response
    {
        return Response::html(View::render('pages/admin/confirm', ['title' => 'Confirm it is you | Belis Bell', 'staff' => array_replace($staff, ['role' => ucfirst($staff['role'] ?? '')]), 'next' => $next, 'error' => $error, 'mock' => is_mock_mode()]), $status);
    }
}
