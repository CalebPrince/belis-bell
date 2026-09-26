<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Catalogue;
use Belis\Support\Logger;

final class HomeController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        try {
            $catalogue = new Catalogue(Db::fromEnv());
            $categories = $catalogue->categories();
            $products = $catalogue->featured(8);
        } catch (\Throwable $e) {
            Logger::error('Home page data failed', ['type' => $e::class]);
            return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
        }
        return Response::html(View::render('pages/home', [
            'title' => 'Belis Bell: cleaning supplies and more',
            'categories' => $categories,
            'products' => $products,
        ]));
    }

    /** @param array<string,string> $params */
    public function health(Request $request, array $params = []): Response
    {
        return Response::json(['status' => 'ok']);
    }
}
