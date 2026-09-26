<?php
declare(strict_types=1);

use Belis\Controllers\HomeController;
use Belis\Core\Router;

/**
 * Route table. Every route declares a policy (see src/Core/Policy.php). Unsafe methods are
 * CSRF-checked unless a reason is given as the last argument.
 * Not built yet (see GUI.md): category, product, search, cart, checkout, quotes, account, admin.
 */
return static function (Router $router): void {
    $router->add('GET', '/', [HomeController::class, 'index'], 'public');
    $router->add('GET', '/health', [HomeController::class, 'health'], 'public');
};
