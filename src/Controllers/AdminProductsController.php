<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\CatalogueAdmin;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;
use Belis\Support\StepUp;

/**
 * Product and price management (CTL-BIZ-001). Staff and the owner can change product content, sizes' names and
 * stock levels and publish or hide a product. Only the owner can create products, add sizes, change prices and
 * bulk prices, and every price change also needs a fresh emailed code. Prices are parsed on the server, history is
 * append-only, and every change is audited. Nothing is deleted: hide a product instead. The local preview
 * people can look but not change anything.
 */
final class AdminProductsController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $q = $request->query;
        $filters = [
            'q' => is_string($q['q'] ?? null) ? $q['q'] : '',
            'category' => is_string($q['category'] ?? null) && ctype_digit($q['category']) ? (int) $q['category'] : 0,
            'published' => is_string($q['published'] ?? null) ? $q['published'] : '',
            'page' => is_string($q['page'] ?? null) && ctype_digit($q['page']) ? (int) $q['page'] : 1,
        ];
        try {
            $admin = new CatalogueAdmin(Db::fromEnv());
            $result = $admin->list($filters);
            $categories = array_values(array_filter($admin->categories(), static fn (array $c): bool => $c['parent_id'] === null));
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return $this->view('pages/admin/products', ['title' => 'Products | Belis Bell', 'result' => $result, 'filters' => $filters, 'categories' => $categories, 'isOwner' => self::isOwner()]);
    }

    /** @param array<string,string> $params */
    public function newForm(Request $request, array $params = []): Response
    {
        return $this->newPage([], [], 200);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params = []): Response
    {
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/products');
        }
        try {
            $db = Db::fromEnv();
            $pdo = $db->pdo();
            $pdo->beginTransaction();
            $r = (new CatalogueAdmin($db))->create($request->post, $me);
            if ($r['errors'] !== []) {
                $pdo->rollBack();
                return $this->newPage($request->post, $r['errors'], 422);
            }
            Audit::add($db, $me, 'product.create', 'product:' . $r['id'], $request->ip, null, 'created hidden, first size and price set');
            $pdo->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice('Product created. It is hidden until you tick Show in the shop.');
        return Response::redirect('/admin/products/' . $r['id']);
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
            return Response::redirect('/admin/products/' . $id);
        }
        try {
            $db = Db::fromEnv();
            $admin = new CatalogueAdmin($db);
            $v = $admin->validateContent($request->post);
            if ($v['errors'] !== []) {
                return $this->editPage($id, $request->post, $v['errors'], 422);
            }
            $db->pdo()->beginTransaction();
            $changed = $admin->updateContent($id, $v['clean']);
            if ($changed !== []) {
                Audit::add($db, $me, 'product.update', 'product:' . $id, $request->ip, null, implode(', ', $changed));
            }
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice($changed === [] ? 'Nothing to save.' : 'Product saved.');
        return Response::redirect('/admin/products/' . $id);
    }

    /** @param array<string,string> $params */
    public function updateSize(Request $request, array $params = []): Response
    {
        $pid = (int) ($params['id'] ?? 0);
        $sid = ctype_digit($params['size'] ?? '') ? (int) $params['size'] : 0;
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/products/' . $pid);
        }
        $priceText = is_string($request->post['price'] ?? null) ? trim($request->post['price']) : null;
        try {
            $db = Db::fromEnv();
            $admin = new CatalogueAdmin($db);
            $current = $admin->find($pid);
            $row = null;
            foreach ($current['sizes'] ?? [] as $s) {
                if ((int) $s['id'] === $sid) {
                    $row = $s;
                }
            }
            if ($row === null) {
                return Response::html(View::render('pages/404', ['title' => 'Not found']), 404);
            }
            $newPrice = null;
            if ($priceText !== null && $priceText !== CatalogueAdmin::plainPrice((int) $row['price_pesewas'])) {
                if (!self::isOwner()) {
                    Flash::notice('Only the owner can change prices. Nothing was saved.');
                    return Response::redirect('/admin/products/' . $pid);
                }
                $newPrice = CatalogueAdmin::parsePrice($priceText);
                if ($newPrice === null) {
                    return $this->editPage($pid, $request->post, ['size_' . $sid => 'Enter a price such as 45.00.'], 422);
                }
                if (!StepUp::fresh($me)) {
                    Flash::notice('Confirm with an emailed code, then save the price again.');
                    return Response::redirect('/admin/confirm?next=' . rawurlencode('/admin/products/' . $pid));
                }
            }
            $db->pdo()->beginTransaction();
            $r = $admin->updateSize($pid, $sid, (string) ($request->post['label'] ?? ''), (string) ($request->post['stock_qty'] ?? ''), (string) ($request->post['stock_reason'] ?? ''), $newPrice, ($request->post['confirm_big'] ?? '') === '1', $me);
            if (!$r['ok']) {
                $db->pdo()->rollBack();
                return $this->editPage($pid, $request->post, ['size_' . $sid => (string) $r['error']], 422);
            }
            if ($r['changed'] !== []) {
                Audit::add($db, $me, in_array('price', $r['changed'], true) ? 'price.update' : 'size.update', 'product:' . $pid . '/size:' . $sid, $request->ip, null, implode(', ', $r['changed']));
            }
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice($r['changed'] === [] ? 'Nothing to save.' : 'Size saved.');
        return Response::redirect('/admin/products/' . $pid);
    }

    /** @param array<string,string> $params */
    public function updateTiers(Request $request, array $params = []): Response
    {
        $pid = (int) ($params['id'] ?? 0);
        $sid = ctype_digit($params['size'] ?? '') ? (int) $params['size'] : 0;
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/products/' . $pid);
        }
        if (!StepUp::fresh($me)) {
            Flash::notice('Confirm with an emailed code, then save the bulk prices again.');
            return Response::redirect('/admin/confirm?next=' . rawurlencode('/admin/products/' . $pid));
        }
        $mins = is_array($request->post['tier_min'] ?? null) ? array_values($request->post['tier_min']) : [];
        $prices = is_array($request->post['tier_price'] ?? null) ? array_values($request->post['tier_price']) : [];
        $rows = [];
        for ($i = 0; $i < min(count($mins), count($prices), 12); $i++) {
            $rows[] = ['min' => is_string($mins[$i]) ? $mins[$i] : '', 'price' => is_string($prices[$i]) ? $prices[$i] : ''];
        }
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $r = (new CatalogueAdmin($db))->setTiers($pid, $sid, $rows, $me);
            if (!$r['ok']) {
                $db->pdo()->rollBack();
                return $this->editPage($pid, $request->post, ['tiers_' . $sid => (string) $r['error']], 422);
            }
            Audit::add($db, $me, 'price.tiers', 'product:' . $pid . '/size:' . $sid, $request->ip, null, 'bulk prices replaced');
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice('Bulk prices saved.');
        return Response::redirect('/admin/products/' . $pid);
    }

    /** @param array<string,string> $params */
    public function addSize(Request $request, array $params = []): Response
    {
        $pid = (int) ($params['id'] ?? 0);
        $me = $this->actor();
        if ($me === null) {
            return Response::redirect('/admin/products/' . $pid);
        }
        if (!StepUp::fresh($me)) {
            Flash::notice('Confirm with an emailed code, then add the size again.');
            return Response::redirect('/admin/confirm?next=' . rawurlencode('/admin/products/' . $pid));
        }
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $r = (new CatalogueAdmin($db))->addSize($pid, (string) ($request->post['label'] ?? ''), (string) ($request->post['price'] ?? ''), (string) ($request->post['stock_qty'] ?? ''), $me);
            if (!$r['ok']) {
                $db->pdo()->rollBack();
                return $this->editPage($pid, $request->post, ['add' => (string) $r['error']], 422);
            }
            Audit::add($db, $me, 'size.add', 'product:' . $pid . '/size:' . $r['id'], $request->ip, null, 'size and price added');
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        Flash::notice('Size added.');
        return Response::redirect('/admin/products/' . $pid);
    }

    /** The signed-in staff member's id, or null for a preview person (who cannot change anything). */
    private function actor(): ?int
    {
        $s = Auth::staff();
        if ($s === null || !isset($s['id'])) {
            Flash::notice('The local preview person cannot change products.');
            return null;
        }
        return (int) $s['id'];
    }

    private static function isOwner(): bool
    {
        return Auth::owner() !== null;
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function editPage(int $id, array $old, array $errors, int $status): Response
    {
        try {
            $admin = new CatalogueAdmin(Db::fromEnv());
            $product = $admin->find($id);
            if ($product === null) {
                return Response::html(View::render('pages/404', ['title' => 'Product not found']), 404);
            }
            $categories = $admin->categories();
            $history = $admin->history($id);
            $stockHistory = $admin->stockHistory($id);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        $me = Auth::staff()['id'] ?? null;
        return $this->view('pages/admin/product', [
            'title' => $product['name'] . ' | Belis Bell', 'product' => $product, 'categories' => $categories, 'history' => $history, 'stockHistory' => $stockHistory, 'old' => $old, 'errors' => $errors,
            'isOwner' => self::isOwner(), 'fresh' => $me !== null && StepUp::fresh((int) $me), 'isNew' => false, 'canEdit' => $me !== null,
        ], $status);
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function newPage(array $old, array $errors, int $status): Response
    {
        try {
            $categories = (new CatalogueAdmin(Db::fromEnv()))->categories();
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return $this->view('pages/admin/product', [
            'title' => 'New product | Belis Bell', 'product' => null, 'categories' => $categories, 'history' => [], 'stockHistory' => [], 'old' => $old, 'errors' => $errors,
            'isOwner' => true, 'fresh' => false, 'isNew' => true, 'canEdit' => isset(Auth::staff()['id']),
        ], $status);
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
        Logger::error('Admin products failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
