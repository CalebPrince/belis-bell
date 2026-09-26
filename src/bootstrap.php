<?php
declare(strict_types=1);

use Belis\Core\Env;

define('BASE_PATH', dirname(__DIR__));

// Never show errors to visitors. Details go to storage/logs/app.log.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);

// PSR-4 style autoloader for the Belis\ namespace. There are no Composer packages at runtime.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Belis\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/helpers.php';

// The environment file sits above the web root (public/). BELIS_ENV_FILE can point elsewhere.
Env::load(getenv('BELIS_ENV_FILE') ?: BASE_PATH . '/.env');
