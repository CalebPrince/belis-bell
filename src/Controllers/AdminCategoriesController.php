<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\CategoryAdmin;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;

/**
 * Category and subcategory management for staff and the owner. New categories start hidden. Nothing is deleted.
 * Every change is audited. The local preview people can look but not change anything.
 */
final class AdminCategoriesController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        return $this->listPage([], [], 200);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params = []): Response
    {
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/categories');
        }
        $parent = is_string($request->post['parent_id'] ?? null) && ctype_digit($request->post['parent_id']) && (int) $request->post['parent_id'] > 0 ? (int) $request->post['parent_id'] : null;
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $r = (new CategoryAdmin($db))->create($request->post, $parent);
            if ($r['errors'] !== []) {
                $db->pdo()->rollBack();
                if ($parent === null) {
                    return $this->listPage($request->post, $r['errors'], 422);
                }
                $prefixed = [];
                foreach ($r['errors'] as $k => $msg) {
                    $prefixed[$k === 'parent' ? 'parent' : 'sub_' . $k] = $msg;
                }
                return $this->editPage($parent, [], $prefixed, 422);
            }
            Audit::add($db, $me, $parent === null ? 'category.create' : 'subcategory.create', 'category:' . $r['id'], $request->ip, null, 'created hidden');
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice('Created. It is hidden until you tick Show in the shop.');
        return Response::redirect('/admin/categories/' . $r['id']);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        return $this->editPage((int) ($params['id'] ?? 0), [], [], 200);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/categories/' . $id);
        }
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $r = (new CategoryAdmin($db))->update($id, $request->post);
            if (!$r['found']) {
                $db->pdo()->rollBack();
                return Response::html(View::render('pages/404', ['title' => 'Category not found']), 404);
            }
            if ($r['errors'] !== []) {
                $db->pdo()->rollBack();
                return $this->editPage($id, $request->post, $r['errors'], 422);
            }
            if ($r['changed'] !== []) {
                Audit::add($db, $me, 'category.update', 'category:' . $id, $request->ip, null, implode(', ', $r['changed']));
            }
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice($r['changed'] === [] ? 'Nothing to save.' : 'Saved.');
        return Response::redirect('/admin/categories/' . $id);
    }

    private function actor(): ?int
    {
        $s = Auth::staff();
        if ($s === null || !isset($s['id'])) {
            Flash::notice('The local preview person cannot change categories.');
            return null;
        }
        return (int) $s['id'];
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function listPage(array $old, array $errors, int $status): Response
    {
        try {
            $tree = (new CategoryAdmin(Db::fromEnv()))->tree();
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return $this->view('pages/admin/categories', ['title' => 'Categories | Belis Bell', 'tree' => $tree, 'old' => $old, 'errors' => $errors], $status);
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function editPage(int $id, array $old, array $errors, int $status): Response
    {
        try {
            $cat = (new CategoryAdmin(Db::fromEnv()))->find($id);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($cat === null) {
            return Response::html(View::render('pages/404', ['title' => 'Category not found']), 404);
        }
        return $this->view('pages/admin/category', ['title' => $cat['name'] . ' | Belis Bell', 'cat' => $cat, 'old' => $old, 'errors' => $errors, 'canEdit' => isset(Auth::staff()['id']), 'photo' => \Belis\Support\Images::exists('categories/' . $cat['slug'])], $status);
    }

    /** @param array<string,mixed> $vars */
    private function view(string $template, array $vars, int $status): Response
    {
        $staff = Auth::staff() ?? [];
        return Response::html(View::render($template, $vars + ['canEdit' => isset($staff['id']), 'staff' => array_replace($staff, ['role' => ucfirst((string) ($staff['role'] ?? ''))])]), $status);
    }

    private function failed(\Throwable $e, ?Db $db = null): Response
    {
        if ($db !== null && $db->pdo()->inTransaction()) {
            $db->pdo()->rollBack();
        }
        Logger::error('Admin categories failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
