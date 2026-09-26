<?php
declare(strict_types=1);

/**
 * MOCK DATA. Synthetic categories and products with invented prices in whole pesewas (GHS).
 * Nothing here is real. Every row is inserted with is_mock = 1. This file and bin/seed.php are
 * excluded from release builds, and the app refuses to start in production if mock rows exist
 * (CTL-ENV-001, GATE-005). Remove before launch and replace with the real catalogue.
 */
return [
    // [slug, name, blurb, sort order]
    'categories' => [
        ['washroom-supplies', 'Washroom supplies', 'Tissue, towels, soap and refills', 1],
        ['cleaning-chemicals', 'Cleaning chemicals', 'Bleach, disinfectant and cleaners', 2],
        ['cleaning-equipment', 'Cleaning equipment', 'Mops, buckets and brushes', 3],
        ['hygiene-and-waste', 'Hygiene and waste', 'Bin liners, sanitiser and bins', 4],
        ['dispensers-and-fittings', 'Dispensers and fittings', 'Wall-mounted soap and towel dispensers', 5],
    ],
    // [category slug, product slug, name, pack size, price in pesewas, stock status]
    'products' => [
        ['washroom-supplies', 'sample-toilet-tissue-48', 'Toilet tissue, 2-ply', '48 rolls', 18500, 'in_stock'],
        ['washroom-supplies', 'sample-paper-towel-6', 'Paper towel rolls', '6 rolls', 9800, 'in_stock'],
        ['washroom-supplies', 'sample-hand-soap-5l', 'Liquid hand soap refill', '5 litre', 7500, 'in_stock'],
        ['cleaning-chemicals', 'sample-bleach-5l', 'Household bleach', '5 litre', 6200, 'in_stock'],
        ['cleaning-chemicals', 'sample-floor-disinfectant-5l', 'Floor disinfectant', '5 litre', 8900, 'low'],
        ['cleaning-chemicals', 'sample-multi-surface-5l', 'Multi-surface cleaner', '5 litre', 8400, 'in_stock'],
        ['cleaning-equipment', 'sample-mop-bucket-set', 'Mop and bucket set', '1 set', 14500, 'in_stock'],
        ['cleaning-equipment', 'sample-toilet-brush-set', 'Toilet brush and holder', '1 set', 3600, 'in_stock'],
        ['hygiene-and-waste', 'sample-bin-liners-100', 'Heavy-duty bin liners', '100 bags', 5400, 'in_stock'],
        ['hygiene-and-waste', 'sample-sanitiser-1l', 'Foam hand sanitiser', '1 litre', 4800, 'out'],
        ['dispensers-and-fittings', 'sample-soap-dispenser', 'Wall-mounted soap dispenser', '1 unit', 12500, 'in_stock'],
        ['dispensers-and-fittings', 'sample-towel-dispenser', 'Wall-mounted towel dispenser', '1 unit', 16800, 'in_stock'],
    ],
];
