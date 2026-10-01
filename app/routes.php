<?php

declare(strict_types=1);

/**
 * Every URL the application answers.
 *
 * $router is created in public/index.php. Auth routes set $auth to false.
 * POST routes check the CSRF token inside the router.
 *
 * @var App\Helpers\Router $router
 */

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PageController;

$router->get('/login', static function (): void {
    (new AuthController())->showLogin();
}, false);

$router->post('/login', static function (): void {
    (new AuthController())->login();
}, false);

$router->post('/logout', static function (): void {
    (new AuthController())->logout();
});

$router->get('/', static function (): void {
    (new DashboardController())->index();
});

$router->get('/account/password', static function (): void {
    (new AccountController())->showPassword();
});

$router->post('/account/password', static function (): void {
    (new AccountController())->updatePassword();
});

$router->get('/calculator', static function (): void {
    (new PageController())->upcoming('calculator');
});

$router->get('/quotes', static function (): void {
    (new PageController())->upcoming('quotes');
});

$router->get('/customers', static function (): void {
    (new PageController())->upcoming('customers');
});

$router->get('/products', static function (): void {
    (new PageController())->upcoming('products');
});

$router->get('/categories', static function (): void {
    (new PageController())->upcoming('categories');
});

$router->get('/pricing-levels', static function (): void {
    (new PageController())->upcoming('pricing');
});

$router->get('/settings', static function (): void {
    (new PageController())->upcoming('settings');
});
