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

/** Public catalogue pages: all products, one category, one product. Read-only, no personal data. */
final class CatalogueController
{
    /** @param array<string,string> $params */
    public function shop(Request $request, array $params = []): Response
    {
        return $this->listing($request, null);
    }

    /** @param array<string,string> $params */
    public function category(Request $request, array $params = []): Response
    {
        return $this->listing($request, $params['slug'] ?? '');
    }

    /** @param array<string,string> $params */
    public function product(Request $request, array $params = []): Response
    {
        try {
            $catalogue = new Catalogue(Db::fromEnv());
            $product = $catalogue->product($params['slug'] ?? '');
            if ($product === null) {
                return Response::html(View::render('pages/404', ['title' => 'Product not found']), 404);
            }
            $related = $catalogue->related((int) $product['category_id'], (int) $product['id'], 4);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return Response::html(View::render('pages/product', [
            'title' => $product['name'] . ' | Belis Bell',
            'product' => $product,
            'related' => $related,
            'whatsapp' => Site::whatsappLink('Hello Belis Bell, I would like to ask about: ' . $product['name']),
        ]));
    }

    private function listing(Request $request, ?string $categorySlug): Response
    {
        $sort = Catalogue::normaliseSort($request->query['sort'] ?? null);
        $page = Catalogue::normalisePage($request->query['page'] ?? null);
        $inStock = ($request->query['stock'] ?? '') === 'in';
        try {
            $catalogue = new Catalogue(Db::fromEnv());
            $category = null;
            if ($categorySlug !== null) {
                $category = $catalogue->category($categorySlug);
                if ($category === null) {
                    return Response::html(View::render('pages/404', ['title' => 'Category not found']), 404);
                }
            }
            $result = $catalogue->listing($category === null ? null : (int) $category['id'], $sort, $inStock, $page);
            $categories = $catalogue->categories();
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        return Response::html(View::render('pages/listing', [
            'title' => ($category['name'] ?? 'All products') . ' | Belis Bell',
            'category' => $category,
            'categories' => $categories,
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'sort' => $sort,
            'inStock' => $inStock,
        ]));
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Catalogue page failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
