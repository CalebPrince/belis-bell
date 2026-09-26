<?php
declare(strict_types=1);

use Belis\Controllers\AccountController;
use Belis\Controllers\AdminController;
use Belis\Controllers\CartController;
use Belis\Controllers\CatalogueController;
use Belis\Controllers\HomeController;
use Belis\Controllers\PageController;
use Belis\Core\Router;

/**
 * Route table. Every route declares a policy (see src/Core/Policy.php). Unsafe methods are
 * CSRF-checked unless a reason is given as the last argument.
 * Not built yet (see GUI.md): search, quotes, real sign-in, orders, Paystack, admin functions, policy pages.
 * Signed-in pages use the customer or staff policy, which only allows the local preview until sign-in exists.
 */
return static function (Router $router): void {
    $router->add('GET', '/', [HomeController::class, 'index'], 'public');
    $router->add('GET', '/health', [HomeController::class, 'health'], 'public');
    $router->add('GET', '/shop', [CatalogueController::class, 'shop'], 'public');
    $router->add('GET', '/c/{slug}', [CatalogueController::class, 'category'], 'public');
    $router->add('GET', '/p/{slug}', [CatalogueController::class, 'product'], 'public');
    $router->add('GET', '/cart', [CartController::class, 'show'], 'public');
    $router->add('POST', '/cart/add', [CartController::class, 'add'], 'public');
    $router->add('POST', '/cart/update', [CartController::class, 'update'], 'public');
    $router->add('POST', '/cart/remove', [CartController::class, 'remove'], 'public');
    $router->add('GET', '/categories', [PageController::class, 'categories'], 'public');
    $router->add('GET', '/for-businesses', [PageController::class, 'forBusinesses'], 'public');
    $router->add('GET', '/about', [PageController::class, 'about'], 'public');
    $router->add('GET', '/contact', [PageController::class, 'contact'], 'public');
    $router->add('GET', '/account/sign-in', [AccountController::class, 'signIn'], 'public');
    $router->add('GET', '/account/register', [AccountController::class, 'register'], 'public');
    $router->add('GET', '/account/verify', [AccountController::class, 'verify'], 'public');
    $router->add('GET', '/account', [AccountController::class, 'dashboard'], 'customer');
    $router->add('GET', '/checkout', [AccountController::class, 'checkout'], 'customer');
    $router->add('GET', '/order/{ref}', [AccountController::class, 'order'], 'customer');
    $router->add('GET', '/admin/sign-in', [AdminController::class, 'signIn'], 'public');
    $router->add('GET', '/admin/verify', [AdminController::class, 'verify'], 'public');
    $router->add('GET', '/admin', [AdminController::class, 'dashboard'], 'staff');
};
