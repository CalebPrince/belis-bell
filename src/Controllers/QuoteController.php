<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Auth;
use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Quotes;
use Belis\Support\Flash;
use Belis\Support\Logger;

/**
 * A customer's own quote requests. Everything is limited to the signed-in person: another customer's quote is a 404.
 * Text only, no files. The local preview person can look but not send anything.
 */
final class QuoteController
{
    /** @param array<string,string> $params */
    public function newForm(Request $request, array $params = []): Response
    {
        return $this->formPage([], [], 200);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params = []): Response
    {
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/quotes');
        }
        try {
            $r = (new Quotes(Db::fromEnv()))->create($uid, $request->post, $request->ip);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($r['errors'] !== []) {
            return $this->formPage($request->post, $r['errors'], 422);
        }
        Flash::notice('Your request ' . $r['ref'] . ' was sent. We will reply here and by email.');
        return Response::redirect('/quotes/' . rawurlencode($r['ref']));
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $uid = $this->uid(false);
        try {
            $rows = $uid === null ? [] : (new Quotes(Db::fromEnv()))->forUser($uid);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return Response::html(View::render('pages/quote/index', ['title' => 'My quotes | Belis Bell', 'quotes' => $rows, 'preview' => $uid === null]));
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $uid = $this->uid(false);
        try {
            $quote = $uid === null ? null : (new Quotes(Db::fromEnv()))->findForUser($params['ref'] ?? '', $uid);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($quote === null) {
            return Response::html(View::render('pages/404', ['title' => 'Quote not found']), 404);
        }
        return Response::html(View::render('pages/quote/show', ['title' => 'Quote ' . $quote['ref'] . ' | Belis Bell', 'quote' => $quote]));
    }

    /** @param array<string,string> $params */
    public function reply(Request $request, array $params = []): Response
    {
        $ref = $params['ref'] ?? '';
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/quotes');
        }
        try {
            $error = (new Quotes(Db::fromEnv()))->addMessage($ref, is_string($request->post['body'] ?? null) ? $request->post['body'] : '', 'customer', $uid);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($error === 'That quote was not found.') {
            return Response::html(View::render('pages/404', ['title' => 'Quote not found']), 404);
        }
        Flash::notice($error ?? 'Message sent.');
        return Response::redirect('/quotes/' . rawurlencode($ref) . '#thread');
    }

    /** @param array<string,string> $params */
    public function answer(Request $request, array $params = []): Response
    {
        $ref = $params['ref'] ?? '';
        $uid = $this->uid();
        if ($uid === null) {
            return Response::redirect('/quotes');
        }
        $choice = $request->post['choice'] ?? '';
        if ($choice !== 'accept' && $choice !== 'decline') {
            Flash::notice('Choose accept or decline.');
            return Response::redirect('/quotes/' . rawurlencode($ref));
        }
        try {
            $error = (new Quotes(Db::fromEnv()))->answer($ref, $uid, $choice === 'accept');
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        if ($error === 'That quote was not found.') {
            return Response::html(View::render('pages/404', ['title' => 'Quote not found']), 404);
        }
        Flash::notice($error ?? ($choice === 'accept' ? 'Thank you. You accepted the quote. Our team will be in touch to arrange your order.' : 'You declined the quote.'));
        return Response::redirect('/quotes/' . rawurlencode($ref));
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,string> $errors
     */
    private function formPage(array $old, array $errors, int $status): Response
    {
        $c = Auth::customer() ?? [];
        return Response::html(View::render('pages/quote/new', ['title' => 'Request a quote | Belis Bell', 'old' => $old, 'errors' => $errors, 'canSend' => isset($c['id'])]), $status);
    }

    private function uid(bool $notice = true): ?int
    {
        $c = Auth::customer();
        if ($c === null || !isset($c['id'])) {
            if ($notice) {
                Flash::notice('The local preview person cannot send quote requests.');
            }
            return null;
        }
        return (int) $c['id'];
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Quote request failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
