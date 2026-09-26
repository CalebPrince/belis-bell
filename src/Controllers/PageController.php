<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Catalogue;
use Belis\Support\Logger;
use Belis\Support\Site;

/** Structure pages reached from the navigation (PG-037). Text is mock until the owner supplies it. */
final class PageController
{
    /** @param array<string,string> $params */
    public function categories(Request $request, array $params = []): Response
    {
        try {
            $categories = (new Catalogue(Db::fromEnv()))->categories();
        } catch (\Throwable $e) {
            Logger::error('Categories page failed', ['type' => $e::class]);
            return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
        }
        return Response::html(View::render('pages/categories', ['title' => 'Categories | Belis Bell', 'categories' => $categories]));
    }

    /** @param array<string,string> $params */
    public function forBusinesses(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/for-businesses', [
            'title' => 'For Businesses | Belis Bell',
            'audiences' => Site::audiences(),
            'whatsapp' => Site::whatsappLink('Hello Belis Bell, I would like a quote for my business.'),
            'contact' => Site::contact(),
        ]));
    }

    /** @param array<string,string> $params */
    public function about(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/about', ['title' => 'About | Belis Bell', 'why' => Site::why()]));
    }

    /** @param array<string,string> $params */
    public function contact(Request $request, array $params = []): Response
    {
        return Response::html(View::render('pages/contact', [
            'title' => 'Contact | Belis Bell',
            'contact' => Site::contact(),
            'whatsapp' => Site::whatsappLink('Hello Belis Bell, I have a question.'),
        ]));
    }
}
