<?php
declare(strict_types=1);

// Deletes every row flagged is_mock = 1. Run before launch, then load the real catalogue.
require __DIR__ . '/_boot.php';

use Belis\Core\Db;

$db = Db::fromEnv();
$p = $db->run('DELETE FROM products WHERE is_mock = 1');
$c = $db->run('DELETE FROM categories WHERE is_mock = 1');
echo "removed {$p} mock products and {$c} mock categories\n";
