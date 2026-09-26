<?php
declare(strict_types=1);

namespace Belis\Domain;

/**
 * Price maths in whole pesewas, no floating point (CTL-BIZ-001). The server is the only place that
 * decides a price or a total: the page shows these figures, and the browser only previews them.
 */
final class Pricing
{
    public const MAX_QTY = 100000;

    /**
     * Unit price for a quantity: the tier with the largest min_qty that the quantity reaches,
     * otherwise the base price.
     *
     * @param list<array{min_qty:int,unit_price_pesewas:int}> $tiers
     */
    public static function unitPrice(int $basePesewas, array $tiers, int $qty): int
    {
        $qty = self::clampQty($qty);
        $best = null;
        foreach ($tiers as $t) {
            $min = (int) $t['min_qty'];
            if ($min >= 1 && $qty >= $min && ($best === null || $min > (int) $best['min_qty'])) {
                $best = $t;
            }
        }
        return $best === null ? $basePesewas : (int) $best['unit_price_pesewas'];
    }

    /** @param list<array{min_qty:int,unit_price_pesewas:int}> $tiers */
    public static function total(int $basePesewas, array $tiers, int $qty): int
    {
        $qty = self::clampQty($qty);
        return self::unitPrice($basePesewas, $tiers, $qty) * $qty;
    }

    public static function clampQty(int $qty): int
    {
        return max(1, min($qty, self::MAX_QTY));
    }

    /**
     * Rows for the bulk price table: quantity ranges with the unit price and the percent saved.
     * Empty when the size has no tiers.
     *
     * @param list<array{min_qty:int,unit_price_pesewas:int}> $tiers
     * @return list<array{label:string,unit_price_pesewas:int,save_percent:int}>
     */
    public static function rows(int $basePesewas, array $tiers): array
    {
        $tiers = array_values(array_filter($tiers, static fn (array $t): bool => (int) $t['min_qty'] >= 1));
        usort($tiers, static fn (array $a, array $b): int => (int) $a['min_qty'] <=> (int) $b['min_qty']);
        if ($tiers === []) {
            return [];
        }
        $rows = [];
        $first = (int) $tiers[0]['min_qty'];
        if ($first > 1) {
            $rows[] = ['label' => self::range(1, $first - 1), 'unit_price_pesewas' => $basePesewas, 'save_percent' => 0];
        }
        foreach ($tiers as $i => $tier) {
            $from = (int) $tier['min_qty'];
            $to = isset($tiers[$i + 1]) ? (int) $tiers[$i + 1]['min_qty'] - 1 : null;
            $unit = (int) $tier['unit_price_pesewas'];
            $rows[] = ['label' => self::range($from, $to), 'unit_price_pesewas' => $unit, 'save_percent' => self::savePercent($basePesewas, $unit)];
        }
        return $rows;
    }

    private static function range(int $from, ?int $to): string
    {
        if ($to === null) {
            return $from . '+';
        }
        return $from === $to ? (string) $from : $from . ' to ' . $to;
    }

    /** Whole-number percent saved by a tier price against the base price (rounded down). */
    public static function savePercent(int $basePesewas, int $unitPesewas): int
    {
        if ($basePesewas <= 0 || $unitPesewas >= $basePesewas) {
            return 0;
        }
        return intdiv(($basePesewas - $unitPesewas) * 100, $basePesewas);
    }

    /**
     * Mock tier prices: 5, 10 and 15 percent off from 10, 50 and 100 units. Used only by the mock seed.
     *
     * @return list<array{min_qty:int,unit_price_pesewas:int}>
     */
    public static function mockTiers(int $basePesewas): array
    {
        $out = [];
        foreach ([10 => 5, 50 => 10, 100 => 15] as $min => $off) {
            $out[] = ['min_qty' => $min, 'unit_price_pesewas' => intdiv($basePesewas * (100 - $off) + 50, 100)];
        }
        return $out;
    }
}
