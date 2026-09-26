<?php
declare(strict_types=1);

use Belis\Controllers\CatalogueController;
use Belis\Controllers\HomeController;
use Belis\Controllers\PageController;
use Belis\Core\Router;

/**
 * Route table. Every route declares a policy (see src/Core/Policy.php). Unsafe methods are
 * CSRF-checked unless a reason is given as the last argument.
 * Not built yet (see GUI.md): search, cart, checkout, quotes, account, admin, and the policy and support pages.
 */
return static function (Router $router): void {
    $router->add('GET', '/', [HomeController::class, 'index'], 'public');
    $router->add('GET', '/health', [HomeController::class, 'health'], 'public');
    $router->add('GET', '/shop', [CatalogueController::class, 'shop'], 'public');
    $router->add('GET', '/c/{slug}', [CatalogueController::class, 'category'], 'public');
    $router->add('GET', '/p/{slug}', [CatalogueController::class, 'product'], 'public');
    $router->add('GET', '/categories', [PageController::class, 'categories'], 'public');
    $router->add('GET', '/for-businesses', [PageController::class, 'forBusinesses'], 'public');
    $router->add('GET', '/about', [PageController::class, 'about'], 'public');
    $router->add('GET', '/contact', [PageController::class, 'contact'], 'public');
};
