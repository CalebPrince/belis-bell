<?php
declare(strict_types=1);

/**
 * MOCK DATA. Extra product page content: sizes, specifications, features, highlights and common uses.
 * Invented text and prices in whole pesewas (GHS). The claims here are NOT real product claims and the
 * text is NOT safety guidance. Real content comes from the supplier's data sheets (PG-041, PG-043).
 * Excluded from release builds and refused in production like the rest of the seed (CTL-ENV-001).
 *
 * Keys are product slugs. Anything not listed gets generic sample content from bin/seed.php.
 * Sizes are [label, price in pesewas, stock status]; the first size is the default.
 */
return [
    'sample-bleach-5l' => [
        'sub' => 'bleach-stain-removers',
        'summary' => 'Powerful cleaning and disinfecting bleach for a cleaner, fresher and healthier home. Sample text.',
        'variants' => [['1 litre', 1800, 'in_stock'], ['5 litre', 6200, 'in_stock'], ['20 litre', 22000, 'low']],
        'specs' => "Type|Household bleach\nAvailable sizes|1 litre, 5 litre, 20 litre\nUse|Disinfecting and whitening\nStorage|Cool, dry place, away from children\nNote|Sample specifications, not real",
        'features' => "Sample feature: strong on stains and germs\nSample feature: whitens clothes and surfaces\nSample feature: suitable for home and general cleaning\nSample feature: available in different sizes",
        'highlights' => "shield-check|Kills germs (sample claim)|Helps keep your home clean and hygienic.\nsparkles|Whitens and removes stains|Effective on tough stains and dirt.\nhouse|Suitable for multiple uses|Ideal for laundry, bathrooms, kitchens and more.",
        'uses' => 'laundry,bathrooms,kitchens,floors,bins',
    ],
    'sample-floor-disinfectant-5l' => [
        'sub' => 'disinfectants',
        'variants' => [['1 litre', 2200, 'in_stock'], ['5 litre', 8900, 'low']],
        'uses' => 'floors,bathrooms,kitchens',
    ],
    'sample-multi-surface-5l' => [
        'sub' => 'surface-cleaners',
        'variants' => [['500 ml', 1500, 'in_stock'], ['5 litre', 8400, 'in_stock']],
        'uses' => 'kitchens,bathrooms',
    ],
];
