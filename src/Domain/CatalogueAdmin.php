<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Db;

/**
 * Staff and owner changes to products, sizes, prices and bulk tiers (CTL-BIZ-001). Prices are whole pesewas and are
 * parsed here from cedis text without floating point. Every price or tier change is written to price_history
 * (append-only), and the product's own "from" price and stock label are kept in step with its sizes, because the shop
 * lists them. The controller decides who may call what: this class only checks that the values make sense.
 */
final class CatalogueAdmin
{
    public const MAX_PRICE = 10000000; // GHS 100,000.00
    public const MAX_TIERS = 6;
    public const BIG_CHANGE_PERCENT = 50;
    public const STOCK = ['in_stock' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'];
    public const PAGE_SIZE = 20;
    public const LOW_STOCK = 10;
    private const LIST_WHERE = "WHERE (? = '' OR p.name LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!') AND (? = 0 OR p.category_id = ?) AND (? = '' OR p.is_published = ?)";

    public function __construct(private readonly Db $db, private readonly ?int $clock = null)
    {
    }

    private function now(): int
    {
        return $this->clock ?? time();
    }

    /** "45", "45.5" or "45.50" to pesewas. Anything else, including 0, is null. */
    public static function parsePrice(string $text): ?int
    {
        $text = trim($text);
        if (preg_match('/^(\d{1,6})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            return null;
        }
        $p = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
        return $p >= 1 && $p <= self::MAX_PRICE ? $p : null;
    }

    public static function plainPrice(int $pesewas): string
    {
        return intdiv($pesewas, 100) . '.' . str_pad((string) ($pesewas % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array{q?:string,category?:int,published?:string,page?:int} $f
     * @return array{items:list<array<string,mixed>>,total:int,pages:int,page:int}
     */
    public function list(array $f): array
    {
        $q = trim((string) ($f['q'] ?? ''));
        $like = $q === '' ? '' : '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 60)) . '%';
        $cat = (int) ($f['category'] ?? 0);
        $pub = in_array($f['published'] ?? '', ['1', '0'], true) ? (string) $f['published'] : '';
        $params = [$like, $like, $like, $cat, $cat, $pub, $pub];
        $total = (int) ($this->db->one('SELECT COUNT(*) AS n FROM products p ' . self::LIST_WHERE, $params)['n'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min((int) ($f['page'] ?? 1), $pages));
        $items = $this->db->all(
            'SELECT p.id, p.slug, p.name, p.brand, p.is_published, p.stock_status, c.name AS category, '
            . '(SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS sizes, '
            . '(SELECT MIN(v.price_pesewas) FROM product_variants v WHERE v.product_id = p.id) AS low, '
            . '(SELECT MAX(v.price_pesewas) FROM product_variants v WHERE v.product_id = p.id) AS high '
            . 'FROM products p JOIN categories c ON c.id = p.category_id ' . self::LIST_WHERE . ' ORDER BY p.name LIMIT ? OFFSET ?',
            array_merge($params, [self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE]),
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return list<array{id:int,name:string,parent_id:?int}> every category and subcategory, parents first */
    public function categories(): array
    {
        return $this->db->all('SELECT id, name, parent_id FROM categories ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, name');
    }

    /** @return array<string,mixed>|null the product with its sizes and each size's bulk tiers */
    public function find(int $id): ?array
    {
        $p = $this->db->one('SELECT p.id, p.slug, p.name, p.brand, p.summary, p.description, p.usage_notes, p.category_id, p.subcategory_id, p.is_published, p.is_mock FROM products p WHERE p.id = ?', [$id]);
        if ($p === null) {
            return null;
        }
        $p['sizes'] = $this->db->all('SELECT id, label, price_pesewas, stock_status, stock_qty FROM product_variants WHERE product_id = ? ORDER BY sort_order, id', [$id]);
        foreach ($p['sizes'] as $i => $s) {
            $p['sizes'][$i]['tiers'] = $this->db->all('SELECT min_qty, unit_price_pesewas FROM bulk_tiers WHERE variant_id = ? ORDER BY min_qty', [(int) $s['id']]);
        }
        return $p;
    }

    /** @return list<array<string,mixed>> newest first, with the size label */
    public function history(int $productId, int $limit = 15): array
    {
        return $this->db->all(
            'SELECT h.kind, h.old_value, h.new_value, h.created_at, v.label, u.email FROM price_history h JOIN product_variants v ON v.id = h.variant_id LEFT JOIN users u ON u.id = h.changed_by WHERE v.product_id = ? ORDER BY h.id DESC LIMIT ?',
            [$productId, max(1, min($limit, 50))],
        );
    }

    /**
     * Check the content fields of a product.
     *
     * @param array<string,mixed> $in
     * @return array{errors:array<string,string>,clean:array<string,mixed>}
     */
    public function validateContent(array $in): array
    {
        $str = static fn (string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
        $clean = ['name' => $str('name'), 'brand' => $str('brand'), 'summary' => $str('summary'), 'description' => $str('description'), 'usage_notes' => $str('usage_notes'),
            'category_id' => ctype_digit($str('category_id')) ? (int) $str('category_id') : 0, 'subcategory_id' => ctype_digit($str('subcategory_id')) ? (int) $str('subcategory_id') : 0,
            'is_published' => ($in['is_published'] ?? '') === '1' ? 1 : 0];
        $errors = [];
        if ($clean['name'] === '' || mb_strlen($clean['name']) > 160) {
            $errors['name'] = 'Enter a name of up to 160 characters.';
        }
        if (mb_strlen($clean['brand']) > 80) {
            $errors['brand'] = 'Use 80 characters or fewer.';
        }
        if (mb_strlen($clean['summary']) > 255) {
            $errors['summary'] = 'Use 255 characters or fewer.';
        }
        if (mb_strlen($clean['description']) > 5000) {
            $errors['description'] = 'Use 5,000 characters or fewer.';
        }
        if (mb_strlen($clean['usage_notes']) > 2000) {
            $errors['usage_notes'] = 'Use 2,000 characters or fewer.';
        }
        $cat = $clean['category_id'] > 0 ? $this->db->one('SELECT id, parent_id FROM categories WHERE id = ?', [$clean['category_id']]) : null;
        if ($cat === null || $cat['parent_id'] !== null) {
            $errors['category_id'] = 'Choose a category.';
        }
        if ($clean['subcategory_id'] > 0) {
            $sub = $this->db->one('SELECT parent_id FROM categories WHERE id = ?', [$clean['subcategory_id']]);
            if ($sub === null || (int) $sub['parent_id'] !== $clean['category_id']) {
                $errors['subcategory_id'] = 'That subcategory does not belong to the chosen category.';
            }
        }
        return ['errors' => $errors, 'clean' => $clean];
    }

    /**
     * Save content fields. Returns the names of the fields that changed (empty when nothing did).
     *
     * @param array<string,mixed> $clean from validateContent
     * @return list<string>
     */
    public function updateContent(int $id, array $clean): array
    {
        $old = $this->db->one('SELECT name, brand, summary, description, usage_notes, category_id, subcategory_id, is_published FROM products WHERE id = ?', [$id]);
        if ($old === null) {
            return [];
        }
        $changed = [];
        foreach (['name', 'brand', 'summary', 'description', 'usage_notes', 'category_id', 'subcategory_id', 'is_published'] as $k) {
            $a = $old[$k] === null ? '' : (string) $old[$k];
            $b = $k === 'subcategory_id' && (int) $clean[$k] === 0 ? '' : (string) $clean[$k];
            if ($a !== $b) {
                $changed[] = $k;
            }
        }
        if ($changed !== []) {
            $this->db->run(
                'UPDATE products SET name = ?, brand = ?, summary = ?, description = ?, usage_notes = ?, category_id = ?, subcategory_id = ?, is_published = ? WHERE id = ?',
                [$clean['name'], $clean['brand'] === '' ? null : $clean['brand'], $clean['summary'] === '' ? null : $clean['summary'], $clean['description'] === '' ? null : $clean['description'], $clean['usage_notes'] === '' ? null : $clean['usage_notes'],
                    $clean['category_id'], (int) $clean['subcategory_id'] === 0 ? null : (int) $clean['subcategory_id'], $clean['is_published'], $id],
            );
        }
        return $changed;
    }

    /**
     * Change one size. Label and stock are content. The price is only touched when $newPrice is given (the caller has
     * checked the person may). A change of more than 50 percent needs $confirmBig.
     *
     * @return array{ok:bool,error:?string,changed:list<string>}
     */
    public function updateSize(int $productId, int $sizeId, string $label, string $stockText, string $reason, ?int $newPrice, bool $confirmBig, ?int $by): array
    {
        $row = $this->db->one('SELECT id, label, price_pesewas, stock_status, stock_qty FROM product_variants WHERE id = ? AND product_id = ?', [$sizeId, $productId]);
        $label = trim($label);
        $reason = trim($reason);
        if ($row === null) {
            return ['ok' => false, 'error' => 'That size does not exist.', 'changed' => []];
        }
        if ($label === '' || mb_strlen($label) > 80 || preg_match('/^\d{1,6}$/', trim($stockText)) !== 1) {
            return ['ok' => false, 'error' => 'Enter a size name (up to 80 characters) and a stock count as a whole number.', 'changed' => []];
        }
        $qty = (int) trim($stockText);
        if ($qty !== (int) $row['stock_qty'] && ($reason === '' || mb_strlen($reason) > 200)) {
            return ['ok' => false, 'error' => 'Say why the stock count is changing (up to 200 characters).', 'changed' => []];
        }
        $changed = [];
        $price = (int) $row['price_pesewas'];
        if ($newPrice !== null && $newPrice !== $price) {
            $big = abs($newPrice - $price) * 100 > $price * self::BIG_CHANGE_PERCENT;
            if ($big && !$confirmBig) {
                return ['ok' => false, 'error' => 'That changes the price by more than ' . self::BIG_CHANGE_PERCENT . ' percent. Tick the box to confirm it is right.', 'changed' => []];
            }
            if ($this->tierConflict($sizeId, $newPrice)) {
                return ['ok' => false, 'error' => 'A bulk price on this size would be higher than the new price. Update the bulk prices first.', 'changed' => []];
            }
            $this->record($sizeId, 'price', self::plainPrice($price), self::plainPrice($newPrice), $by);
            $price = $newPrice;
            $changed[] = 'price';
        }
        if ($label !== $row['label']) {
            $changed[] = 'label';
        }
        if ($qty !== (int) $row['stock_qty']) {
            $changed[] = 'stock';
            $this->stockChange($sizeId, $qty - (int) $row['stock_qty'], $qty, 'adjust', $reason, null, $by);
        }
        if ($changed !== []) {
            $this->db->run('UPDATE product_variants SET label = ?, price_pesewas = ?, stock_qty = ?, stock_status = ? WHERE id = ?', [$label, $price, $qty, self::labelFor($qty), $sizeId]);
            $this->sync($productId);
        }
        return ['ok' => true, 'error' => null, 'changed' => $changed];
    }

    private function tierConflict(int $sizeId, int $newBase): bool
    {
        return ((int) ($this->db->one('SELECT COUNT(*) AS n FROM bulk_tiers WHERE variant_id = ? AND unit_price_pesewas >= ?', [$sizeId, $newBase])['n'] ?? 0)) > 0;
    }

    /**
     * Replace the bulk tiers of a size. Rows with both fields empty are ignored.
     *
     * @param list<array{min:string,price:string}> $rows
     * @return array{ok:bool,error:?string}
     */
    public function setTiers(int $productId, int $sizeId, array $rows, ?int $by): array
    {
        $size = $this->db->one('SELECT price_pesewas FROM product_variants WHERE id = ? AND product_id = ?', [$sizeId, $productId]);
        if ($size === null) {
            return ['ok' => false, 'error' => 'That size does not exist.'];
        }
        $base = (int) $size['price_pesewas'];
        $tiers = [];
        foreach ($rows as $r) {
            $min = trim($r['min']);
            $price = trim($r['price']);
            if ($min === '' && $price === '') {
                continue;
            }
            $p = self::parsePrice($price);
            if (preg_match('/^\d{1,6}$/', $min) !== 1 || (int) $min < 2 || (int) $min > Pricing::MAX_QTY || $p === null) {
                return ['ok' => false, 'error' => 'Each bulk row needs a quantity of 2 or more and a price such as 40.00.'];
            }
            $tiers[] = ['min_qty' => (int) $min, 'unit_price_pesewas' => $p];
        }
        if (count($tiers) > self::MAX_TIERS) {
            return ['ok' => false, 'error' => 'Use at most ' . self::MAX_TIERS . ' bulk rows.'];
        }
        usort($tiers, static fn (array $a, array $b): int => $a['min_qty'] <=> $b['min_qty']);
        $prev = $base;
        $seen = [];
        foreach ($tiers as $t) {
            if (isset($seen[$t['min_qty']])) {
                return ['ok' => false, 'error' => 'Each bulk quantity can only appear once.'];
            }
            $seen[$t['min_qty']] = true;
            if ($t['unit_price_pesewas'] >= $prev) {
                return ['ok' => false, 'error' => 'Bulk prices must be lower than the price before them, and fall as the quantity rises.'];
            }
            $prev = $t['unit_price_pesewas'];
        }
        $describe = static fn (array $list): string => $list === [] ? 'none' : implode(', ', array_map(static fn (array $t): string => $t['min_qty'] . '+ at ' . self::plainPrice((int) $t['unit_price_pesewas']), $list));
        $old = $this->db->all('SELECT min_qty, unit_price_pesewas FROM bulk_tiers WHERE variant_id = ? ORDER BY min_qty', [$sizeId]);
        if ($describe($old) !== $describe($tiers)) {
            $this->db->run('DELETE FROM bulk_tiers WHERE variant_id = ?', [$sizeId]);
            foreach ($tiers as $t) {
                $this->db->run('INSERT INTO bulk_tiers (variant_id, min_qty, unit_price_pesewas, is_mock) VALUES (?, ?, ?, 0)', [$sizeId, $t['min_qty'], $t['unit_price_pesewas']]);
            }
            $this->record($sizeId, 'tiers', mb_substr($describe($old), 0, 500), mb_substr($describe($tiers), 0, 500), $by);
        }
        return ['ok' => true, 'error' => null];
    }

    /**
     * Add a size to a product.
     *
     * @return array{ok:bool,error:?string,id:int}
     */
    public function addSize(int $productId, string $label, string $priceText, string $stockText, ?int $by): array
    {
        $label = trim($label);
        $price = self::parsePrice($priceText);
        if ($this->db->one('SELECT id FROM products WHERE id = ?', [$productId]) === null) {
            return ['ok' => false, 'error' => 'That product does not exist.', 'id' => 0];
        }
        if ($label === '' || mb_strlen($label) > 80 || $price === null || preg_match('/^\d{1,6}$/', trim($stockText)) !== 1) {
            return ['ok' => false, 'error' => 'Enter a size name, a price such as 45.00, and a stock count as a whole number.', 'id' => 0];
        }
        $qty = (int) trim($stockText);
        $order = (int) ($this->db->one('SELECT COALESCE(MAX(sort_order), 0) AS m FROM product_variants WHERE product_id = ?', [$productId])['m'] ?? 0) + 1;
        $this->db->run('INSERT INTO product_variants (product_id, label, price_pesewas, stock_status, stock_qty, sort_order, is_mock) VALUES (?, ?, ?, ?, ?, ?, 0)', [$productId, $label, $price, self::labelFor($qty), $qty, $order]);
        $id = (int) ($this->db->one('SELECT MAX(id) AS id FROM product_variants WHERE product_id = ?', [$productId])['id'] ?? 0);
        $this->record($id, 'created', null, self::plainPrice($price), $by);
        $this->stockChange($id, $qty, $qty, 'initial', 'First count when the size was added', null, $by);
        $this->sync($productId);
        return ['ok' => true, 'error' => null, 'id' => $id];
    }

    /**
     * Create an unpublished product with its first size.
     *
     * @param array<string,mixed> $in
     * @return array{errors:array<string,string>,id:int}
     */
    public function create(array $in, ?int $by): array
    {
        $v = $this->validateContent($in);
        $errors = $v['errors'];
        $price = self::parsePrice(is_string($in['price'] ?? null) ? $in['price'] : '');
        $label = trim(is_string($in['label'] ?? null) ? $in['label'] : '');
        $stock = is_string($in['stock_qty'] ?? null) ? trim($in['stock_qty']) : '';
        if ($price === null) {
            $errors['price'] = 'Enter a price such as 45.00.';
        }
        if ($label === '' || mb_strlen($label) > 80) {
            $errors['label'] = 'Enter the first size or pack, for example 5 L.';
        }
        if (preg_match('/^\d{1,6}$/', $stock) !== 1) {
            $errors['stock_qty'] = 'Enter how many you have, as a whole number.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => 0];
        }
        $c = $v['clean'];
        $slug = $this->uniqueSlug((string) $c['name']);
        $this->db->run(
            'INSERT INTO products (category_id, subcategory_id, brand, slug, name, pack_size, summary, description, usage_notes, price_pesewas, stock_status, is_published, is_mock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)',
            [$c['category_id'], (int) $c['subcategory_id'] === 0 ? null : (int) $c['subcategory_id'], $c['brand'] === '' ? null : $c['brand'], $slug, $c['name'], $label, $c['summary'] === '' ? null : $c['summary'], $c['description'] === '' ? null : $c['description'], $c['usage_notes'] === '' ? null : $c['usage_notes'], $price, self::labelFor((int) $stock)],
        );
        $id = (int) ($this->db->one('SELECT id FROM products WHERE slug = ?', [$slug])['id'] ?? 0);
        $this->addSize($id, $label, self::plainPrice((int) $price), $stock, $by);
        return ['errors' => [], 'id' => $id];
    }

    public static function slugify(string $name): string
    {
        $s = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));
        return $s === '' ? 'product' : substr($s, 0, 100);
    }

    private function uniqueSlug(string $name): string
    {
        $base = self::slugify($name);
        $slug = $base;
        for ($i = 2; $this->db->one('SELECT id FROM products WHERE slug = ?', [$slug]) !== null; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    /** The shop label that goes with a count: out at 0, low at 10 or fewer. */
    public static function labelFor(int $qty): string
    {
        return $qty <= 0 ? 'out' : ($qty <= self::LOW_STOCK ? 'low' : 'in_stock');
    }

    /** Write one stock movement to the append-only history. */
    public function stockChange(int $variantId, int $delta, int $qtyAfter, string $kind, string $reason, ?int $orderId, ?int $by): void
    {
        $this->db->run('INSERT INTO stock_history (variant_id, delta, qty_after, kind, reason, order_id, changed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$variantId, $delta, max(0, $qtyAfter), $kind, mb_substr($reason, 0, 200), $orderId, $by, $this->now()]);
    }

    /** Make the size's shop label and its product's label follow the stored count. */
    public function applyStockLabel(int $variantId): void
    {
        $v = $this->db->one('SELECT product_id, stock_qty FROM product_variants WHERE id = ?', [$variantId]);
        if ($v === null) {
            return;
        }
        $this->db->run('UPDATE product_variants SET stock_status = ? WHERE id = ?', [self::labelFor((int) $v['stock_qty']), $variantId]);
        $this->sync((int) $v['product_id']);
    }

    /** @return list<array<string,mixed>> newest first */
    public function stockHistory(int $productId, int $limit = 15): array
    {
        return $this->db->all('SELECT h.delta, h.qty_after, h.kind, h.reason, h.created_at, v.label, u.email FROM stock_history h JOIN product_variants v ON v.id = h.variant_id LEFT JOIN users u ON u.id = h.changed_by WHERE v.product_id = ? ORDER BY h.id DESC LIMIT ?', [$productId, max(1, min($limit, 50))]);
    }

    /** Keep the product's "from" price, pack label and stock label in step with its sizes, because the shop lists them. */
    private function sync(int $productId): void
    {
        $sizes = $this->db->all('SELECT label, price_pesewas, stock_status FROM product_variants WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
        if ($sizes === []) {
            return;
        }
        $stocks = array_column($sizes, 'stock_status');
        $stock = in_array('in_stock', $stocks, true) ? 'in_stock' : (in_array('low', $stocks, true) ? 'low' : 'out');
        $this->db->run('UPDATE products SET price_pesewas = ?, pack_size = ?, stock_status = ? WHERE id = ?', [min(array_map('intval', array_column($sizes, 'price_pesewas'))), (string) $sizes[0]['label'], $stock, $productId]);
    }

    private function record(int $sizeId, string $kind, ?string $old, string $new, ?int $by): void
    {
        $this->db->run('INSERT INTO price_history (variant_id, kind, old_value, new_value, changed_by, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$sizeId, $kind, $old, $new, $by, $this->now()]);
    }
}
