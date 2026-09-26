<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;

/** Read-only catalogue queries. Prices are whole pesewas, computed and shown by the server only. */
final class Catalogue
{
    public const PAGE_SIZE = 8;
    public const SORTS = ['featured', 'price-asc', 'price-desc', 'name'];

    public function __construct(private readonly Db $db)
    {
    }

    /** Anything not on the allowlist becomes the default, so a query string can never reach the SQL. */
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

    /** @return list<array<string,mixed>> */
    public function categories(): array
    {
        return $this->db->all('SELECT id, slug, name, blurb FROM categories WHERE is_published = 1 ORDER BY sort_order, name');
    }

    /** @return array<string,mixed>|null */
    public function category(string $slug): ?array
    {
        return $this->db->one('SELECT id, slug, name, blurb FROM categories WHERE slug = ? AND is_published = 1', [$slug]);
    }

    /** @return list<array<string,mixed>> */
    public function featured(int $limit): array
    {
        $limit = max(1, min($limit, 24));
        return $this->db->all(
            'SELECT p.slug, p.name, p.pack_size, p.price_pesewas, p.stock_status, c.name AS category '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.is_published = 1 AND c.is_published = 1 ORDER BY p.id LIMIT ?',
            [$limit],
        );
    }

    /**
     * One page of products, optionally limited to a category and to items that are not out of stock.
     * Every SQL string below is a fixed literal. The sort key is a bound parameter that selects a
     * CASE branch, so no user text is ever added to the query.
     *
     * @return array{items:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public function listing(?int $categoryId, string $sort, bool $inStockOnly, int $page): array
    {
        $sort = self::normaliseSort($sort);
        $stock = $inStockOnly ? 1 : 0;

        $countRow = $this->db->one(
            'SELECT COUNT(*) AS n FROM products p JOIN categories c ON c.id = p.category_id '
            . "WHERE p.is_published = 1 AND c.is_published = 1 AND (? IS NULL OR p.category_id = ?) AND (? = 0 OR p.stock_status <> 'out')",
            [$categoryId, $categoryId, $stock],
        );
        $total = (int) ($countRow['n'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $items = $this->db->all(
            'SELECT p.slug, p.name, p.pack_size, p.price_pesewas, p.stock_status, c.name AS category, c.slug AS category_slug '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . "WHERE p.is_published = 1 AND c.is_published = 1 AND (? IS NULL OR p.category_id = ?) AND (? = 0 OR p.stock_status <> 'out') "
            . "ORDER BY CASE WHEN ? = 'price-asc' THEN p.price_pesewas END ASC, "
            . "CASE WHEN ? = 'price-desc' THEN p.price_pesewas END DESC, "
            . "CASE WHEN ? = 'name' THEN p.name END ASC, p.id ASC LIMIT ? OFFSET ?",
            [$categoryId, $categoryId, $stock, $sort, $sort, $sort, self::PAGE_SIZE, $offset],
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array<string,mixed>|null */
    public function product(string $slug): ?array
    {
        return $this->db->one(
            'SELECT p.id, p.category_id, p.slug, p.name, p.pack_size, p.description, p.usage_notes, p.price_pesewas, p.currency, p.stock_status, '
            . 'c.name AS category, c.slug AS category_slug '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.slug = ? AND p.is_published = 1 AND c.is_published = 1',
            [$slug],
        );
    }

    /** @return list<array<string,mixed>> */
    public function related(int $categoryId, int $excludeProductId, int $limit): array
    {
        $limit = max(1, min($limit, 8));
        return $this->db->all(
            'SELECT p.slug, p.name, p.pack_size, p.price_pesewas, p.stock_status, c.name AS category '
            . 'FROM products p JOIN categories c ON c.id = p.category_id '
            . 'WHERE p.category_id = ? AND p.id <> ? AND p.is_published = 1 ORDER BY p.id LIMIT ?',
            [$categoryId, $excludeProductId, $limit],
        );
    }
}
