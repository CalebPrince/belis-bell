<?php
declare(strict_types=1);

// Applies database/migrations/*.sql in order and records them. Run with the migration database user.
require __DIR__ . '/_boot.php';

use Belis\Core\Db;

$db = Db::fromEnv();
$db->run('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$done = array_column($db->all('SELECT name FROM schema_migrations'), 'name');
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files);
$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    $sqlText = (string) file_get_contents($file);
    $sqlText = preg_replace('/^\s*--.*$/m', '', $sqlText) ?? $sqlText;
    foreach (preg_split('/;\s*\n/', $sqlText) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $db->run($statement);
        }
    }
    $db->run('INSERT INTO schema_migrations (name) VALUES (?)', [$name]);
    echo "applied {$name}\n";
    $count++;
}
echo $count === 0 ? "nothing to apply\n" : "done\n";
