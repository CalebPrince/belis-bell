<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;

/**
 * Read-only catalogue queries. Prices are whole pesewas, computed and shown by the server only. Every SQL
 * string is a fixed literal and every value is a bound parameter, so nothing from a query string can change
 * the shape of a query (CTL-INP-001). Filters are checked in normaliseFilters().
 */
final class Catalogue
{
    public const PAGE_SIZE = 12;
    public const SORTS = ['featured', 'price-asc', 'price-desc', 'name'];
    public const MAX_CEDIS = 1000000;

    public function __construct(private readonly Db $db)
    {
    }

    public static function normaliseSort(mixed $sort): string
    {
        return is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : 'featured';
    }

    /** 1-based page number, clamped to a sane range. */
    public static function normalisePage(mixed $page): int
    {
        $n = is_string($page) && ctype_digit($page) ? (int) $page : 1;
        return max(1, min($n, 500));
    }

    /**
     * Turns the raw query string into the only filters the queries accept. Anything odd is dropped.
     *
     * @param array<string,mixed> $q
     * @return array{sort:string,page:int,avail:?string,brand:?string,min:?int,max:?int,sub:?string}
     */
    public static function normaliseFilters(array $q): array
    {
        $cedis = static function (mixed $v): ?int {
            if (!is_string($v) || $v === '' || !ctype_digit($v) || strlen($v) > 7) {
                return null;
            }
            return min((int) $v, self::MAX_CEDIS);
        };
        $brand = isset($q['brand']) && is_string($q['brand']) ? trim($q['brand']) : '';
        $sub = isset($q['sub']) && is_string($q['sub']) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $q['sub']) === 1 ? $q['sub'] : null;
        $avail = isset($q['avail']) && is_string($q['avail']) && in_array($q['avail'], ['in', 'out'], true) ? $q['avail'] : null;
        $min = $cedis($q['min'] ?? null);
        $max = $cedis($q['max'] ?? null);
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        return [
            'sort' => self::normaliseSort($q['sort'] ?? null),
            'page' => self::normalisePage($q['page'] ?? null),
            'avail' => $avail,
            'brand' => $brand !== '' && mb_strlen($brand) <= 80 ? $brand : null,
            'min' => $min,
            'max' => $max,
            'sub' => $sub,
        ];
    }

    /** Top-level categories only. */
    /** @return list<array<string,mixed>> */
    public function categories(): array
    {
        return $this->db->all('SELECT id, slug, name, blurb FROM categories WHERE is_published = 1 AND parent_id IS NULL ORDER BY sort_order, name');
    }

    /** @return array<string,mixed>|null */
    public function category(string $slug): ?array
    {
        return $this->db->one('SELECT id, slug, name, blurb FROM categories WHERE slug = ? AND is_published = 1 AND parent_id IS NULL', [$slug]);
    }

    /** @return list<array<string,mixed>> subcategories with their product counts */
    public function subcategories(int $parentId): array
    {
        return $this->db->all(
            'SELECT c.id, c.slug, c.name, (SELECT COUNT(*) FROM products p WHERE p.subcategory_id = c.id AND p.is_published = 1) AS n '
            . 'FROM categories c WHERE c.parent_id = ? AND c.is_published = 1 ORDER BY c.sort_order, c.name',
            [$parentId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function featured(int $limit): array
    {
        $limit = max(1, min($limit, 24));
        return $this->db->all(
            'SELECT p.slug, p.name, p.pack_size, p.price_pesewas, p.stock_status, c.name AS category, '
            . '(SELECT v.id FROM product_variants v WHERE v.product_id = p.id ORDER BY v.sort_order, v.id LIMIT 1) AS variant_id '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.is_published = 1 AND c.is_published = 1 ORDER BY p.id LIMIT ?',
            [$limit],
        );
    }

    /**
     * One page of products for the shop and category pages. Every filter is optional (null means any).
     * The sort key is a bound parameter that picks a CASE branch, so no user text reaches the SQL.
     *
     * @param array{sort:string,page:int,avail:?string,brand:?string,min:?int,max:?int,sub:?string} $f
     * @return array{items:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public function listing(?int $categoryId, ?int $subId, array $f): array
    {
        $min = $f['min'] === null ? null : $f['min'] * 100;
        $max = $f['max'] === null ? null : $f['max'] * 100;
        $args = [$categoryId, $categoryId, $subId, $subId, $f['brand'], $f['brand'], $min, $min, $max, $max, $f['avail'], $f['avail'], $f['avail']];

        $countRow = $this->db->one(
            'SELECT COUNT(*) AS n FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.is_published = 1 AND c.is_published = 1 AND (? IS NULL OR p.category_id = ?) AND (? IS NULL OR p.subcategory_id = ?) '
            . 'AND (? IS NULL OR p.brand = ?) AND (? IS NULL OR p.price_pesewas >= ?) AND (? IS NULL OR p.price_pesewas <= ?) '
            . "AND (? IS NULL OR (? = 'in' AND p.stock_status <> 'out') OR (? = 'out' AND p.stock_status = 'out'))",
            $args,
        );
        $total = (int) ($countRow['n'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($f['page'], $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $items = $this->db->all(
            'SELECT p.slug, p.name, p.pack_size, p.price_pesewas, p.stock_status, c.name AS category, c.slug AS category_slug, '
            . '(SELECT v.id FROM product_variants v WHERE v.product_id = p.id ORDER BY v.sort_order, v.id LIMIT 1) AS variant_id '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.is_published = 1 AND c.is_published = 1 AND (? IS NULL OR p.category_id = ?) AND (? IS NULL OR p.subcategory_id = ?) '
            . 'AND (? IS NULL OR p.brand = ?) AND (? IS NULL OR p.price_pesewas >= ?) AND (? IS NULL OR p.price_pesewas <= ?) '
            . "AND (? IS NULL OR (? = 'in' AND p.stock_status <> 'out') OR (? = 'out' AND p.stock_status = 'out')) "
            . "ORDER BY CASE WHEN ? = 'price-asc' THEN p.price_pesewas END ASC, "
            . "CASE WHEN ? = 'price-desc' THEN p.price_pesewas END DESC, "
            . "CASE WHEN ? = 'name' THEN p.name END ASC, p.id ASC LIMIT ? OFFSET ?",
            array_merge($args, [$f['sort'], $f['sort'], $f['sort'], self::PAGE_SIZE, $offset]),
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /**
     * Counts for the filter panel, within one category (or the whole shop when null).
     *
     * @return array{all:int,categories:list<array<string,mixed>>,in_stock:int,out_of_stock:int,brands:list<array<string,mixed>>,lowest:int,highest:int}
     */
    public function facets(?int $categoryId): array
    {
        $cats = $this->db->all(
            'SELECT c.id, c.slug, c.name, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_published = 1) AS n '
            . 'FROM categories c WHERE c.is_published = 1 AND c.parent_id IS NULL ORDER BY c.sort_order, c.name',
        );
        $stock = $this->db->one(
            'SELECT COUNT(*) AS n_all, COALESCE(SUM(p.stock_status <> \'out\'), 0) AS n_in, COALESCE(SUM(p.stock_status = \'out\'), 0) AS n_out, '
            . 'COALESCE(MIN(p.price_pesewas), 0) AS lo, COALESCE(MAX(p.price_pesewas), 0) AS hi '
            . 'FROM products p WHERE p.is_published = 1 AND (? IS NULL OR p.category_id = ?)',
            [$categoryId, $categoryId],
        ) ?? [];
        $brands = $this->db->all(
            'SELECT p.brand AS name, COUNT(*) AS n FROM products p WHERE p.is_published = 1 AND p.brand IS NOT NULL '
            . 'AND (? IS NULL OR p.category_id = ?) GROUP BY p.brand ORDER BY p.brand',
            [$categoryId, $categoryId],
        );
        return [
            'all' => (int) ($stock['n_all'] ?? 0),
            'categories' => $cats,
            'in_stock' => (int) ($stock['n_in'] ?? 0),
            'out_of_stock' => (int) ($stock['n_out'] ?? 0),
            'brands' => $brands,
            'lowest' => (int) ($stock['lo'] ?? 0),
            'highest' => (int) ($stock['hi'] ?? 0),
        ];
    }

    /** @return array<string,mixed>|null */
    public function product(string $slug): ?array
    {
        return $this->db->one(
            'SELECT p.id, p.category_id, p.slug, p.name, p.pack_size, p.summary, p.description, p.usage_notes, p.specs, p.features, p.highlights, p.uses, '
            . 'p.price_pesewas, p.currency, p.stock_status, '
            . 'c.name AS category, c.slug AS category_slug '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.slug = ? AND p.is_published = 1 AND c.is_published = 1',
            [$slug],
        );
    }

    /** @return list<array{id:int,label:string,price_pesewas:int,stock_status:string}> */
    public function variants(int $productId): array
    {
        return $this->db->all(
            'SELECT id, label, price_pesewas, stock_status FROM product_variants WHERE product_id = ? ORDER BY sort_order, id',
            [$productId],
        );
    }

    /**
     * One size with its product, for the cart. Only published products are found.
     *
     * @return array<string,mixed>|null
     */
    public function variantWithProduct(int $variantId): ?array
    {
        return $this->db->one(
            'SELECT v.id AS variant_id, v.label, v.price_pesewas, v.stock_status, v.stock_qty, p.slug, p.name '
            . 'FROM product_variants v JOIN products p ON p.id = v.product_id JOIN categories c ON c.id = p.category_id '
            . 'WHERE v.id = ? AND p.is_published = 1 AND c.is_published = 1',
            [$variantId],
        );
    }

    /**
     * Bulk tiers for one size, cheapest quantity first.
     *
     * @return list<array{min_qty:int,unit_price_pesewas:int}>
     */
    public function tiers(int $variantId): array
    {
        return $this->db->all(
            'SELECT min_qty, unit_price_pesewas FROM bulk_tiers WHERE variant_id = ? ORDER BY min_qty',
            [$variantId],
        );
    }

    /** @return list<array<string,mixed>> */
    public function related(int $categoryId, int $excludeProductId, int $limit): array
    {
        $limit = max(1, min($limit, 8));
        return $this->db->all(
            'SELECT p.slug, p.name, p.pack_size, p.price_pesewas, p.stock_status, c.name AS category, '
            . '(SELECT v.id FROM product_variants v WHERE v.product_id = p.id ORDER BY v.sort_order, v.id LIMIT 1) AS variant_id '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.category_id = ? AND p.id <> ? AND p.is_published = 1 ORDER BY p.id LIMIT ?',
            [$categoryId, $excludeProductId, $limit],
        );
    }
}
