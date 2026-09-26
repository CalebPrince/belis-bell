<?php
declare(strict_types=1);

namespace Belis\Support;

use Belis\Core\Auth;

/**
 * Sample people, orders and figures for the signed-in pages while accounts, orders and the admin area are
 * NOT BUILT. Every method returns nothing unless the local preview is on (PREVIEW_LOGIN, see Auth), so this
 * data can never appear on a real site. Prices are whole pesewas.
 */
final class PreviewData
{
    public const ORDER_STATES = ['paid', 'pending', 'failed', 'cancelled'];

    /** @return array<string,mixed>|null */
    public static function order(string $ref, string $state = 'paid'): ?array
    {
        if (Auth::previewRole() === null || $ref !== 'BB-10482') {
            return null;
        }
        return [
            'ref' => 'BB-10482',
            'state' => in_array($state, self::ORDER_STATES, true) ? $state : 'paid',
            'placed' => '26 Sep 2026',
            'method' => 'Standard Delivery',
            'address' => ['Prince Caleb', '12 Sample Street, Osu', 'Accra, Greater Accra', '+233 24 123 4567'],
            'lines' => [
                ['name' => 'Sample Bleach', 'label' => '5 L', 'qty' => 2, 'unit' => 4500],
                ['name' => 'Sample Hand Soap', 'label' => '500 ml', 'qty' => 6, 'unit' => 1800],
            ],
            'delivery' => 2000,
        ];
    }

    /** @return list<array{ref:string,date:string,state:string,total:int,items:int}> */
    public static function orders(): array
    {
        if (Auth::previewRole() === null) {
            return [];
        }
        return [
            ['ref' => 'BB-10482', 'date' => '26 Sep 2026', 'state' => 'paid', 'total' => 21800, 'items' => 8],
            ['ref' => 'BB-10391', 'date' => '14 Sep 2026', 'state' => 'paid', 'total' => 9600, 'items' => 3],
            ['ref' => 'BB-10277', 'date' => '02 Sep 2026', 'state' => 'cancelled', 'total' => 15400, 'items' => 5],
        ];
    }

    /** @return list<array{label:string,lines:list<string>,default:bool}> */
    public static function addresses(): array
    {
        if (Auth::previewRole() === null) {
            return [];
        }
        return [
            ['label' => 'Home', 'lines' => ['12 Sample Street, Osu', 'Accra, Greater Accra'], 'default' => true],
            ['label' => 'Office', 'lines' => ['3 Sample Avenue, Airport City', 'Accra, Greater Accra'], 'default' => false],
        ];
    }

    /** @return list<array{label:string,value:string,note:string}> */
    public static function adminStats(): array
    {
        if (Auth::staff() === null) {
            return [];
        }
        return [
            ['label' => 'Orders today', 'value' => '14', 'note' => '3 waiting to be packed'],
            ['label' => 'Sales this week', 'value' => 'GH₵ 18,420.00', 'note' => 'Sample figure'],
            ['label' => 'Open quote requests', 'value' => '5', 'note' => '2 from institutions'],
            ['label' => 'Low stock items', 'value' => '4', 'note' => 'Below 10 units'],
        ];
    }

    /** @return list<array{ref:string,customer:string,state:string,total:int}> */
    public static function adminOrders(): array
    {
        if (Auth::staff() === null) {
            return [];
        }
        return [
            ['ref' => 'BB-10482', 'customer' => 'Sample Customer A', 'state' => 'paid', 'total' => 21800],
            ['ref' => 'BB-10481', 'customer' => 'Sample School B', 'state' => 'pending', 'total' => 154000],
            ['ref' => 'BB-10480', 'customer' => 'Sample Bank C', 'state' => 'paid', 'total' => 87500],
            ['ref' => 'BB-10479', 'customer' => 'Sample Customer D', 'state' => 'failed', 'total' => 4500],
        ];
    }

    /** @return list<array{name:string,left:int}> */
    public static function lowStock(): array
    {
        if (Auth::staff() === null) {
            return [];
        }
        return [
            ['name' => 'Sample Bleach 5 L', 'left' => 6],
            ['name' => 'Sample Hand Soap 500 ml', 'left' => 4],
            ['name' => 'Sample Toilet Tissue (pack of 10)', 'left' => 8],
        ];
    }

    public static function stateLabel(string $state): string
    {
        return ['paid' => 'Paid', 'pending' => 'Payment pending', 'failed' => 'Payment failed', 'cancelled' => 'Cancelled'][$state] ?? 'Unknown';
    }
}
