<?php
declare(strict_types=1);

/**
 * MOCK DATA. Synthetic products with invented prices in whole pesewas (GHS), invented descriptions
 * and generic usage notes. The six category names come from the owner's mockup, but the products
 * under them are made up. Nothing here is real, and the usage notes are NOT safety guidance: real
 * product safety text must come from the supplier's data sheets. Every row is inserted with
 * is_mock = 1. This file and bin/seed.php are excluded from release builds, and the app refuses to
 * start in production if mock rows exist (CTL-ENV-001, GATE-005). Replace with the real catalogue.
 */
return [
    // [slug, name, blurb, sort order]
    'categories' => [
        ['cleaning-products', 'Cleaning Products', 'Cleaners, bleach and disinfectant', 1],
        ['paper-products-disposables', 'Paper Products & Disposables', 'Tissue, towels and disposables', 2],
        ['washroom-supplies', 'Washroom Supplies', 'Soap, sanitiser and dispensers', 3],
        ['bins-waste-management', 'Bins & Waste Management', 'Bin liners and waste bins', 4],
        ['cleaning-tools-accessories', 'Cleaning Tools & Accessories', 'Mops, buckets and brushes', 5],
        ['general-supplies', 'General Supplies', 'Everyday extras for any space', 6],
    ],
    // [category slug, product slug, name, pack size, price in pesewas, stock status, description, usage notes]
    'products' => [
        ['cleaning-products', 'sample-bleach-5l', 'Household bleach', '5 litre', 6200, 'in_stock',
            'General-purpose bleach for whitening and disinfecting hard surfaces. Sample text for the mock catalogue.',
            "Sample usage note only. Never mix bleach with other cleaners. Use the real product's data sheet for dilution and safety."],
        ['cleaning-products', 'sample-floor-disinfectant-5l', 'Floor disinfectant', '5 litre', 8900, 'low',
            'Concentrated floor disinfectant for tiled and sealed floors. Sample text for the mock catalogue.',
            'Sample usage note. Dilution and safety come from the real data sheet.'],
        ['cleaning-products', 'sample-multi-surface-5l', 'Multi-surface cleaner', '5 litre', 8400, 'in_stock',
            'Everyday cleaner for counters, sinks and washroom surfaces. Sample text for the mock catalogue.',
            'Sample usage note. Test on a small area first.'],
        ['paper-products-disposables', 'sample-toilet-tissue-48', 'Toilet tissue, 2-ply', '48 rolls', 18500, 'in_stock',
            'Soft two-ply tissue in a bulk carton for offices, schools and busy washrooms. Sample text for the mock catalogue.',
            'Store in a dry place. Sample usage note.'],
        ['paper-products-disposables', 'sample-paper-towel-6', 'Paper towel rolls', '6 rolls', 9800, 'in_stock',
            'Absorbent paper towel rolls that fit standard wall dispensers. Sample text for the mock catalogue.',
            'Keep dry. Sample usage note.'],
        ['paper-products-disposables', 'sample-facial-tissue-12', 'Facial tissue boxes', '12 boxes', 7200, 'in_stock',
            'Soft facial tissue in boxes for reception areas and offices. Sample text for the mock catalogue.',
            'Keep dry. Sample usage note.'],
        ['washroom-supplies', 'sample-hand-soap-5l', 'Liquid hand soap refill', '5 litre', 7500, 'in_stock',
            'Mild liquid hand soap in a 5 litre refill container for dispensers. Sample text for the mock catalogue.',
            'For external use. Sample usage note. Follow the real product label once the catalogue is real.'],
        ['washroom-supplies', 'sample-sanitiser-1l', 'Foam hand sanitiser', '1 litre', 4800, 'out',
            'Alcohol-based foam hand sanitiser refill. Sample text for the mock catalogue.',
            'Keep away from flames. Sample usage note.'],
        ['washroom-supplies', 'sample-soap-dispenser', 'Wall-mounted soap dispenser', '1 unit', 12500, 'in_stock',
            'Lockable wall-mounted dispenser for liquid soap refills. Sample text for the mock catalogue.',
            'Fix to a solid wall. Sample usage note.'],
        ['bins-waste-management', 'sample-bin-liners-100', 'Heavy-duty bin liners', '100 bags', 5400, 'in_stock',
            'Strong bin liners for offices and washrooms. Sample text for the mock catalogue.',
            'Choose the size that fits your bin. Sample usage note.'],
        ['bins-waste-management', 'sample-pedal-bin-30l', 'Pedal bin', '30 litre', 13500, 'in_stock',
            'Lidded pedal bin for washrooms and kitchens. Sample text for the mock catalogue.',
            'Empty and rinse regularly. Sample usage note.'],
        ['cleaning-tools-accessories', 'sample-mop-bucket-set', 'Mop and bucket set', '1 set', 14500, 'in_stock',
            'Mop head, handle and wringer bucket for daily floor cleaning. Sample text for the mock catalogue.',
            'Rinse and dry after use. Sample usage note.'],
        ['cleaning-tools-accessories', 'sample-toilet-brush-set', 'Toilet brush and holder', '1 set', 3600, 'in_stock',
            'Sturdy toilet brush with a matching holder. Sample text for the mock catalogue.',
            'Rinse after use. Sample usage note.'],
        ['cleaning-tools-accessories', 'sample-microfibre-cloths-10', 'Microfibre cloths', '10 cloths', 4200, 'in_stock',
            'Reusable microfibre cloths for wiping and polishing. Sample text for the mock catalogue.',
            'Machine wash after use. Sample usage note.'],
        ['general-supplies', 'sample-gloves-100', 'Disposable gloves', '100 gloves', 6800, 'in_stock',
            'Disposable gloves for cleaning and handling. Sample text for the mock catalogue.',
            'Use once and dispose. Sample usage note.'],
        ['general-supplies', 'sample-wet-floor-sign', 'Wet floor sign', '1 sign', 5200, 'in_stock',
            'Folding caution sign for freshly cleaned floors. Sample text for the mock catalogue.',
            'Fold flat for storage. Sample usage note.'],
    ],
];
