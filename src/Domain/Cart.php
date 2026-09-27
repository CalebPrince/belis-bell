<?php
declare(strict_types=1);

namespace Belis\Domain;

use Belis\Core\Session;
use Belis\Support\Site;

/**
 * The shopping cart (PG-052). It lives in the visitor's session as size id => quantity and nothing else.
 * Names, prices and totals are never stored or trusted from the browser: every figure is rebuilt from the
 * database on each view (CTL-BIZ-001), so a changed page or form cannot change a price.
 */
final class Cart
{
    public const MAX_LINES = 50;
    public const MAX_QTY = 10000;

    /** @return array<int,int> size id => quantity */
    public static function lines(): array
    {
        if (!Session::hasCookie()) {
            return [];
        }
        Session::start();
        $raw = $_SESSION['cart'] ?? [];
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $variantId => $qty) {
                if (is_int($variantId) && $variantId > 0 && is_int($qty) && $qty > 0) {
                    $out[$variantId] = min($qty, self::MAX_QTY);
                }
            }
        }
        return $out;
    }

    public static function count(): int
    {
        return array_sum(self::lines());
    }

    /** Adds to a size. Returns false when the cart is full. */
    public static function add(int $variantId, int $qty): bool
    {
        $lines = self::lines();
        if (!isset($lines[$variantId]) && count($lines) >= self::MAX_LINES) {
            return false;
        }
        $lines[$variantId] = min(($lines[$variantId] ?? 0) + self::clampQty($qty), self::MAX_QTY);
        self::save($lines);
        return true;
    }

    /** Sets a quantity; zero or less removes the line. */
    public static function set(int $variantId, int $qty): void
    {
        $lines = self::lines();
        if ($qty <= 0) {
            unset($lines[$variantId]);
        } elseif (isset($lines[$variantId])) {
            $lines[$variantId] = min($qty, self::MAX_QTY);
        }
        self::save($lines);
    }

    public static function clear(): void
    {
        self::save([]);
    }

    public static function clampQty(int $qty): int
    {
        return max(1, min($qty, self::MAX_QTY));
    }

    /**
     * Cart lines with prices from the database. Sizes that no longer exist are dropped.
     *
     * @return array{lines:list<array<string,mixed>>,items:int,subtotal:int,delivery:?int,total:int}
     */
    public static function detailed(Catalogue $catalogue): array
    {
        $lines = [];
        $items = 0;
        $subtotal = 0;
        foreach (self::lines() as $variantId => $qty) {
            $v = $catalogue->variantWithProduct($variantId);
            if ($v === null) {
                continue;
            }
            $tiers = $catalogue->tiers($variantId);
            $unit = Pricing::unitPrice((int) $v['price_pesewas'], $tiers, $qty);
            $line = $unit * $qty;
            $lines[] = [
                'variant_id' => $variantId, 'slug' => $v['slug'], 'name' => $v['name'], 'label' => $v['label'], 'stock_status' => $v['stock_status'], 'stock_qty' => (int) ($v['stock_qty'] ?? 0),
                'qty' => $qty, 'unit_pesewas' => $unit, 'line_pesewas' => $line,
            ];
            $items += $qty;
            $subtotal += $line;
        }
        $delivery = $lines === [] ? null : Site::deliveryFeePesewas();
        return ['lines' => $lines, 'items' => $items, 'subtotal' => $subtotal, 'delivery' => $delivery, 'total' => $subtotal + ($delivery ?? 0)];
    }

    /** @param array<int,int> $lines */
    private static function save(array $lines): void
    {
        Session::start();
        $_SESSION['cart'] = $lines;
    }
}
