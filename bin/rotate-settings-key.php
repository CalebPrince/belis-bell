<?php
declare(strict_types=1);

// Re-encrypts every saved setting under a new master key (CTL-SET-001).
// Usage:  php bin/rotate-settings-key.php
// It reads the current key from SETTINGS_KEY in the environment file, generates a new random key, re-encrypts all
// rows in one transaction, and prints the new key ONCE. Replace SETTINGS_KEY in the environment file with it straight
// away: until you do, saved settings cannot be read. Run it with the normal environment file.
require __DIR__ . '/_boot.php';

use Belis\Core\Db;
use Belis\Core\Secrets;

$db = Db::fromEnv();
$new = bin2hex(random_bytes(32));
$pdo = $db->pdo();
$pdo->beginTransaction();
try {
    $n = 0;
    foreach ($db->all('SELECT name, value_enc FROM settings') as $row) {
        $plain = Secrets::decrypt((string) $row['value_enc'], (string) $row['name']);
        $db->run('UPDATE settings SET value_enc = ? WHERE name = ?', [Secrets::encrypt($plain, (string) $row['name'], $new), (string) $row['name']]);
        $n++;
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Nothing was changed. Could not re-encrypt: " . $e->getMessage() . "\n");
    exit(1);
}
echo "Re-encrypted {$n} setting(s).\nSet this in your environment file NOW, replacing the old SETTINGS_KEY:\n\nSETTINGS_KEY={$new}\n";
