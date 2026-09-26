<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;

/** Read-only catalogue queries. Prices are whole pesewas, computed and shown by the server only. */
final class Catalogue
{
    public function __construct(private readonly Db $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function categories(): array
    {
        return $this->db->all('SELECT slug, name, blurb FROM categories WHERE is_published = 1 ORDER BY sort_order, name');
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
}
