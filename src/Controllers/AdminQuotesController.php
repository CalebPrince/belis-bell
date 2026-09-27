<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Quotes;
use Belis\Support\Audit;
use Belis\Support\Flash;
use Belis\Support\Logger;

/**
 * Staff view of quote requests: list, one quote with its thread, replies, status and priced offers. Available to the
 * sales role and the owner. Customer details are personal data, so opening a quote and every action are audited.
 * The local preview person sees an empty list and cannot change anything.
 */
final class AdminQuotesController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $staff = Auth::staff() ?? [];
        $filters = [
            'status' => is_string($request->query['status'] ?? null) ? $request->query['status'] : '',
            'q' => is_string($request->query['q'] ?? null) ? $request->query['q'] : '',
            'page' => is_string($request->query['page'] ?? null) && ctype_digit($request->query['page']) ? (int) $request->query['page'] : 1,
        ];
        try {
            $result = isset($staff['id']) ? (new Quotes(Db::fromEnv()))->adminList($filters) : ['items' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return $this->view('pages/admin/quotes', ['title' => 'Quotes | Belis Bell', 'result' => $result, 'filters' => $filters, 'preview' => !isset($staff['id'])]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $me = $this->actor(false);
        $ref = $params['ref'] ?? '';
        try {
            $db = Db::fromEnv();
            $quote = $me === null ? null : (new Quotes($db))->adminFind($ref);
            if ($quote !== null) {
                Audit::add($db, $me, 'quote.view', $ref, $request->ip);
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($quote === null) {
            return Response::html(View::render('pages/404', ['title' => 'Quote not found']), 404);
        }
        return $this->view('pages/admin/quote', ['title' => 'Quote ' . $ref . ' | Belis Bell', 'quote' => $quote]);
    }

    /** @param array<string,string> $params */
    public function reply(Request $request, array $params = []): Response
    {
        return $this->act($request, $params, 'quote.reply', 'Reply sent.', static fn (Quotes $q, string $ref, int $me): ?string => $q->addMessage($ref, is_string($request->post['body'] ?? null) ? $request->post['body'] : '', 'staff', $me));
    }

    /** @param array<string,string> $params */
    public function status(Request $request, array $params = []): Response
    {
        $to = is_string($request->post['status'] ?? null) ? $request->post['status'] : '';
        return $this->act($request, $params, 'quote.status', 'Status updated.', static fn (Quotes $q, string $ref, int $me): ?string => $q->setStatus($ref, $to, $me), 'set to ' . $to);
    }

    /** @param array<string,string> $params */
    public function offer(Request $request, array $params = []): Response
    {
        $prices = [];
        $raw = is_array($request->post['unit'] ?? null) ? $request->post['unit'] : [];
        foreach ($raw as $itemId => $text) {
            if (is_string($text) && (is_int($itemId) || ctype_digit((string) $itemId))) {
                $prices[(int) $itemId] = $text;
            }
        }
        $until = is_string($request->post['valid_until'] ?? null) ? $request->post['valid_until'] : '';
        $note = is_string($request->post['note'] ?? null) ? $request->post['note'] : '';
        return $this->act($request, $params, 'quote.offer', 'Quote sent to the customer.', static fn (Quotes $q, string $ref, int $me): ?string => $q->createOffer($ref, $prices, $until, $note, $me), 'priced offer sent');
    }

    /**
     * Run one staff action on a quote inside a transaction, audit it when it worked, and go back to the quote.
     *
     * @param array<string,string> $params
     * @param callable(Quotes,string,int):?string $do returns a message when refused
     */
    private function act(Request $request, array $params, string $action, string $done, callable $do, ?string $detail = null): Response
    {
        $ref = $params['ref'] ?? '';
        $back = Response::redirect('/admin/quotes/' . rawurlencode($ref));
        $me = $this->actor();
        if ($me === null) {
            return $back;
        }
        try {
            $db = Db::fromEnv();
            $db->pdo()->beginTransaction();
            $error = $do(new Quotes($db), $ref, $me);
            if ($error === null) {
                Audit::add($db, $me, $action, $ref, $request->ip, null, $detail);
            }
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            return $this->failed($e, $db ?? null);
        }
        if ($error === 'That quote was not found.') {
            return Response::html(View::render('pages/404', ['title' => 'Quote not found']), 404);
        }
        Flash::notice($error ?? $done);
        return $back;
    }

    private function actor(bool $notice = true): ?int
    {
        $s = Auth::staff();
        if ($s === null || !isset($s['id'])) {
            if ($notice) {
                Flash::notice('The local preview person cannot change quotes.');
            }
            return null;
        }
        return (int) $s['id'];
    }

    /** @param array<string,mixed> $vars */
    private function view(string $template, array $vars): Response
    {
        $staff = Auth::staff() ?? [];
        return Response::html(View::render($template, $vars + ['staff' => array_replace($staff, ['role' => ucfirst((string) ($staff['role'] ?? ''))])]));
    }

    private function failed(\Throwable $e, ?Db $db = null): Response
    {
        if ($db !== null && $db->pdo()->inTransaction()) {
            $db->pdo()->rollBack();
        }
        Logger::error('Admin quotes failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
