<?php
declare(strict_types=1);

use Belis\Controllers\AccountController;
use Belis\Controllers\AccountSelfController;
use Belis\Controllers\AdminCategoriesController;
use Belis\Controllers\AdminConfirmController;
use Belis\Controllers\AdminController;
use Belis\Controllers\AdminProductsController;
use Belis\Controllers\AdminStaffController;
use Belis\Controllers\AdminOrdersController;
use Belis\Controllers\AuthController;
use Belis\Controllers\CartController;
use Belis\Controllers\CheckoutController;
use Belis\Controllers\CatalogueController;
use Belis\Controllers\HomeController;
use Belis\Controllers\SettingsController;
use Belis\Controllers\PageController;
use Belis\Core\Router;

/**
 * Route table. Every route declares a policy (see src/Core/Policy.php). Unsafe methods are
 * CSRF-checked unless a reason is given as the last argument.
 * Not built yet (see GUI.md): search, quotes, admin functions, refunds, policy pages.
 * Signed-in pages use the customer or staff policy (password plus emailed code, or the local preview).
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
    $router->add('POST', '/account/sign-in', [AuthController::class, 'customerSignIn'], 'public');
    $router->add('POST', '/account/register', [AuthController::class, 'register'], 'public');
    $router->add('POST', '/account/verify', [AuthController::class, 'customerVerify'], 'public');
    $router->add('POST', '/account/resend', [AuthController::class, 'customerResend'], 'public');
    $router->add('POST', '/account/sign-out', [AuthController::class, 'customerSignOut'], 'public');
    $router->add('GET', '/account/forgot', [AccountController::class, 'forgot'], 'public');
    $router->add('POST', '/account/forgot', [AuthController::class, 'customerForgot'], 'public');
    $router->add('GET', '/account/reset', [AccountController::class, 'reset'], 'public');
    $router->add('POST', '/account/reset', [AuthController::class, 'customerReset'], 'public');
    $router->add('GET', '/admin/forgot', [AdminController::class, 'forgot'], 'public');
    $router->add('POST', '/admin/forgot', [AuthController::class, 'staffForgot'], 'public');
    $router->add('GET', '/admin/reset', [AdminController::class, 'reset'], 'public');
    $router->add('POST', '/admin/reset', [AuthController::class, 'staffReset'], 'public');
    $router->add('GET', '/account', [AccountController::class, 'dashboard'], 'customer');
    $router->add('GET', '/checkout', [AccountController::class, 'checkout'], 'customer');
    $router->add('POST', '/account/details', [AccountSelfController::class, 'details'], 'customer');
    $router->add('POST', '/account/password', [AccountSelfController::class, 'password'], 'customer');
    $router->add('POST', '/account/addresses', [AccountSelfController::class, 'addAddress'], 'customer');
    $router->add('POST', '/account/addresses/{id}/delete', [AccountSelfController::class, 'deleteAddress'], 'customer');
    $router->add('POST', '/account/addresses/{id}/default', [AccountSelfController::class, 'defaultAddress'], 'customer');
    $router->add('POST', '/checkout', [CheckoutController::class, 'place'], 'customer');
    $router->add('GET', '/pay/{ref}', [CheckoutController::class, 'pay'], 'customer');
    $router->add('GET', '/payment/callback', [CheckoutController::class, 'callback'], 'customer');
    $router->add('GET', '/order/{ref}', [AccountController::class, 'order'], 'customer');
    $router->add('POST', '/order/{ref}/refresh', [CheckoutController::class, 'refresh'], 'customer');
    $router->add('POST', '/order/{ref}/cancel', [CheckoutController::class, 'cancel'], 'customer');
    $router->add('POST', '/webhooks/paystack', [CheckoutController::class, 'webhook'], 'webhook:paystack', 'Called by Paystack, not a browser: authenticated by the HMAC signature of the raw body instead of a CSRF token');
    $router->add('GET', '/mock-pay/{reference}', [CheckoutController::class, 'mockPage'], 'public');
    $router->add('POST', '/mock-pay/{reference}', [CheckoutController::class, 'mockSettle'], 'public');
    $router->add('GET', '/admin/sign-in', [AdminController::class, 'signIn'], 'public');
    $router->add('GET', '/admin/verify', [AdminController::class, 'verify'], 'public');
    $router->add('POST', '/admin/sign-in', [AuthController::class, 'staffSignIn'], 'public');
    $router->add('POST', '/admin/verify', [AuthController::class, 'staffVerify'], 'public');
    $router->add('POST', '/admin/resend', [AuthController::class, 'staffResend'], 'public');
    $router->add('POST', '/admin/sign-out', [AuthController::class, 'staffSignOut'], 'public');
    $router->add('GET', '/admin/orders', [AdminOrdersController::class, 'index'], 'staff');
    $router->add('GET', '/admin/orders/{ref}', [AdminOrdersController::class, 'show'], 'staff');
    $router->add('POST', '/admin/orders/{ref}/fulfilment', [AdminOrdersController::class, 'fulfilment'], 'staff');
    $router->add('GET', '/admin/categories', [AdminCategoriesController::class, 'index'], 'staff');
    $router->add('POST', '/admin/categories', [AdminCategoriesController::class, 'create'], 'staff');
    $router->add('GET', '/admin/categories/{id}', [AdminCategoriesController::class, 'show'], 'staff');
    $router->add('POST', '/admin/categories/{id}', [AdminCategoriesController::class, 'update'], 'staff');
    $router->add('GET', '/admin/products', [AdminProductsController::class, 'index'], 'staff');
    $router->add('GET', '/admin/products/new', [AdminProductsController::class, 'newForm'], 'owner');
    $router->add('POST', '/admin/products/new', [AdminProductsController::class, 'create'], 'owner');
    $router->add('GET', '/admin/products/{id}', [AdminProductsController::class, 'show'], 'staff');
    $router->add('POST', '/admin/products/{id}', [AdminProductsController::class, 'update'], 'staff');
    $router->add('POST', '/admin/products/{id}/size/{size}', [AdminProductsController::class, 'updateSize'], 'staff');
    $router->add('POST', '/admin/products/{id}/tiers/{size}', [AdminProductsController::class, 'updateTiers'], 'owner');
    $router->add('POST', '/admin/products/{id}/addsize', [AdminProductsController::class, 'addSize'], 'owner');
    $router->add('GET', '/admin/confirm', [AdminConfirmController::class, 'show'], 'staff');
    $router->add('POST', '/admin/confirm/code', [AdminConfirmController::class, 'sendCode'], 'staff');
    $router->add('POST', '/admin/confirm', [AdminConfirmController::class, 'verify'], 'staff');
    $router->add('GET', '/admin/staff', [AdminStaffController::class, 'index'], 'owner');
    $router->add('POST', '/admin/staff', [AdminStaffController::class, 'create'], 'owner');
    $router->add('POST', '/admin/staff/{id}/active', [AdminStaffController::class, 'setActive'], 'owner');
    $router->add('GET', '/admin/audit', [AdminStaffController::class, 'audit'], 'owner');
    $router->add('GET', '/admin/settings', [SettingsController::class, 'show'], 'owner');
    $router->add('POST', '/admin/settings', [SettingsController::class, 'save'], 'owner');
    $router->add('POST', '/admin/settings/code', [SettingsController::class, 'sendCode'], 'owner');
    $router->add('POST', '/admin/settings/verify', [SettingsController::class, 'verifyCode'], 'owner');
    $router->add('POST', '/admin/settings/test-email', [SettingsController::class, 'testEmail'], 'owner');
    $router->add('GET', '/admin', [AdminController::class, 'dashboard'], 'staff');
};
