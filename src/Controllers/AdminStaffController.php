<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Env;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\StaffAdmin;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\Mailer;
use Belis\Support\StepUp;

/**
 * Owner-only staff accounts and audit log views. Adding staff and switching accounts on or off need a fresh
 * emailed code, and every action is audited. Nobody else can see or change staff accounts.
 */
final class AdminStaffController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        return $this->staffPage([], [], 200);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params = []): Response
    {
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/staff');
        }
        if (!StepUp::fresh($me)) {
            Flash::notice('Confirm with an emailed code, then add the person again.');
            return Response::redirect('/admin/confirm?next=' . rawurlencode('/admin/staff'));
        }
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $r = (new StaffAdmin($db, Mailer::fromEnv()))->create($request->post, Env::get('APP_URL', '') ?? '');
            if ($r['errors'] !== []) {
                $db->pdo()->rollBack();
                return $this->staffPage($request->post, $r['errors'], 422);
            }
            Audit::add($db, $me, 'staff.create', 'user:' . $r['id'], $request->ip, null, 'staff account created');
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice('Staff account created. We emailed them how to choose a password.');
        return Response::redirect('/admin/staff');
    }

    /** @param array<string,string> $params */
    public function setActive(Request $request, array $params = []): Response
    {
        $me = $this->actor();
        $id = ctype_digit($params['id'] ?? '') ? (int) $params['id'] : 0;
        if ($me === null) {
            return Response::redirect('/admin/staff');
        }
        if (!StepUp::fresh($me)) {
            Flash::notice('Confirm with an emailed code, then try again.');
            return Response::redirect('/admin/confirm?next=' . rawurlencode('/admin/staff'));
        }
        $on = ($request->post['active'] ?? '') === '1';
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $problem = (new StaffAdmin($db, Mailer::fromEnv()))->setActive($id, $on, $me);
            if ($problem === null) {
                Audit::add($db, $me, $on ? 'staff.activate' : 'staff.deactivate', 'user:' . $id, $request->ip);
            }
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice($problem ?? ($on ? 'Account switched on.' : 'Account switched off. They are signed out on their next request.'));
        return Response::redirect('/admin/staff');
    }

    /** @param array<string,string> $params */
    public function audit(Request $request, array $params = []): Response
    {
        $me = $this->actor(false);
        $area = is_string($request->query['area'] ?? null) ? $request->query['area'] : '';
        $page = is_string($request->query['page'] ?? null) && ctype_digit($request->query['page']) ? (int) $request->query['page'] : 1;
        try {
            $db = Db::fromEnv();
            $result = Audit::page($db, $area, $page);
            if ($me !== null) {
                Audit::add($db, $me, 'audit.view', $area === '' ? 'all' : $area, $request->ip);
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return $this->view('pages/admin/audit', ['title' => 'Activity log | Belis Bell', 'result' => $result, 'area' => array_key_exists($area, Audit::AREAS) ? $area : '', 'preview' => $me === null]);
    }

    private function actor(bool $notice = true): ?int
    {
        $o = Auth::owner();
        if ($o === null || !isset($o['id'])) {
            if ($notice) {
                Flash::notice('The local preview person cannot change staff accounts.');
            }
            return null;
        }
        return (int) $o['id'];
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function staffPage(array $old, array $errors, int $status): Response
    {
        try {
            $staff = (new StaffAdmin(Db::fromEnv(), Mailer::fromEnv()))->all();
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        $me = Auth::owner()['id'] ?? null;
        return $this->view('pages/admin/staff', ['title' => 'Staff | Belis Bell', 'members' => $staff, 'old' => $old, 'errors' => $errors, 'canEdit' => $me !== null, 'myId' => (int) ($me ?? 0), 'fresh' => $me !== null && StepUp::fresh((int) $me)], $status);
    }

    /** @param array<string,mixed> $vars */
    private function view(string $template, array $vars, int $status = 200): Response
    {
        $staff = Auth::staff() ?? [];
        return Response::html(View::render($template, $vars + ['staff' => array_replace($staff, ['role' => ucfirst((string) ($staff['role'] ?? ''))])]), $status);
    }

    private function failed(\Throwable $e, ?Db $db = null): Response
    {
        if ($db !== null && $db->pdo()->inTransaction()) {
            $db->pdo()->rollBack();
        }
        Logger::error('Admin staff failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
