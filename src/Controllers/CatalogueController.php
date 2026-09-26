<?php
declare(strict_types=1);

namespace Belis\Controllers;

use Belis\Core\Db;
use Belis\Core\Request;
use Belis\Core\Response;
use Belis\Core\View;
use Belis\Domain\Catalogue;
use Belis\Domain\ProductContent;
use Belis\Support\Images;
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
            $variants = $catalogue->variants((int) $product['id']);
            if ($variants === []) {
                // A product without size rows still has one size: itself. It cannot be added to the cart.
                $variants = [['id' => 0, 'label' => (string) $product['pack_size'], 'price_pesewas' => (int) $product['price_pesewas'], 'stock_status' => (string) $product['stock_status']]];
            }
            foreach ($variants as $i => $v) {
                $variants[$i]['tiers'] = (int) $v['id'] > 0 ? $catalogue->tiers((int) $v['id']) : [];
            }
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        // The size comes from the query string, but only a size that belongs to this product is used.
        $wanted = is_string($request->query['size'] ?? null) && ctype_digit($request->query['size']) ? (int) $request->query['size'] : 0;
        $selected = 0;
        foreach ($variants as $i => $v) {
            if ($wanted > 0 && (int) $v['id'] === $wanted) {
                $selected = $i;
            }
        }
        $summary = trim((string) ($product['summary'] ?? ''));
        return Response::html(View::render('pages/product', [
            'title' => $product['name'] . ' | Belis Bell',
            'extra_js' => 'js/product.js',
            'product' => $product,
            'summary' => $summary !== '' ? $summary : ProductContent::firstSentence((string) $product['description']),
            'variants' => $variants,
            'selected' => $selected,
            'related' => $related,
            'specs' => ProductContent::specs($product['specs'] ?? null),
            'features' => ProductContent::features($product['features'] ?? null),
            'highlights' => ProductContent::highlights($product['highlights'] ?? null),
            'uses' => ProductContent::uses($product['uses'] ?? null),
            'trust' => Site::trust(),
            'deliveryReturns' => Site::deliveryReturns(),
            'whatsapp' => Site::whatsappLink('Hello Belis Bell, I would like to ask about: ' . $product['name']),
        ]));
    }

    private function listing(Request $request, ?string $categorySlug): Response
    {
        $filters = Catalogue::normaliseFilters($request->query);
        try {
            $catalogue = new Catalogue(Db::fromEnv());
            $category = null;
            $sub = null;
            $subcategories = [];
            if ($categorySlug !== null) {
                $category = $catalogue->category($categorySlug);
                if ($category === null) {
                    return Response::html(View::render('pages/404', ['title' => 'Category not found']), 404);
                }
                $subcategories = $catalogue->subcategories((int) $category['id']);
                foreach ($subcategories as $s) {
                    if ($filters['sub'] !== null && $s['slug'] === $filters['sub']) {
                        $sub = $s;
                    }
                }
                if ($sub === null) {
                    $filters['sub'] = null; // an unknown or foreign subcategory is ignored
                }
            } else {
                $filters['sub'] = null;
            }
            $categoryId = $category === null ? null : (int) $category['id'];
            $result = $catalogue->listing($categoryId, $sub === null ? null : (int) $sub['id'], $filters);
            $facets = $catalogue->facets($categoryId);
        } catch (\Throwable $e) {
            return $this->failed($e);
        }
        $heroSlot = $category !== null && Images::exists('category-heroes/' . $category['slug']) ? 'category-heroes/' . $category['slug'] : 'shop/hero';
        return Response::html(View::render('pages/listing', [
            'title' => ($category['name'] ?? 'Shop') . ' | Belis Bell',
            'category' => $category,
            'sub' => $sub,
            'subcategories' => $subcategories,
            'facets' => $facets,
            'filters' => $filters,
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'heroSlot' => $heroSlot,
            'trust' => $category === null ? Site::trust() : Site::categoryTrust(),
            'strip' => $category === null ? Site::shopStrip() : Site::categoryStrip(),
        ]));
    }

    private function failed(\Throwable $e): Response
    {
        Logger::error('Catalogue page failed', ['type' => $e::class]);
        return Response::html(View::render('pages/unavailable', ['title' => 'Back shortly']), 503);
    }
}
