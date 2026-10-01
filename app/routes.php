<?php

declare(strict_types=1);

/**
 * Every URL the application answers.
 *
 * The fourth argument on GET, and the fifth on POST, is the permission code.
 * ADMIN is allowed through even when that code is missing from the role.
 * Coming-soon pages have no permission so any signed-in user can open the
 * address without an error. The menu shows them as disabled.
 *
 * @var App\Helpers\Router $router
 */

use App\Controllers\AccountController;
use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\CalculatorController;
use App\Controllers\CategoryController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\PageController;
use App\Controllers\PricingLevelController;
use App\Controllers\ProductController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\SupplierController;
use App\Controllers\UserController;

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
}, true, 'dashboard.view');

$router->get('/search', static function (): void {
    (new SearchController())->index();
});

$router->get('/account/password', static function (): void {
    (new AccountController())->showPassword();
});

$router->post('/account/password', static function (): void {
    (new AccountController())->updatePassword();
});

$router->get('/customers', static function (): void {
    (new CustomerController())->index();
}, true, 'customers.view');

$router->get('/customers/new', static function (): void {
    (new CustomerController())->create();
}, true, 'customers.manage');

$router->post('/customers', static function (): void {
    (new CustomerController())->store();
}, true, true, 'customers.manage');

$router->get('/customers/{id}', static function (string $id): void {
    (new CustomerController())->show($id);
}, true, 'customers.view');

$router->get('/customers/{id}/edit', static function (string $id): void {
    (new CustomerController())->edit($id);
}, true, 'customers.manage');

$router->post('/customers/{id}', static function (string $id): void {
    (new CustomerController())->update($id);
}, true, true, 'customers.manage');

$router->post('/customers/{id}/active', static function (string $id): void {
    (new CustomerController())->deactivate($id);
}, true, true, 'customers.manage');

$router->post('/customers/{id}/contacts', static function (string $id): void {
    (new CustomerController())->storeContact($id);
}, true, true, 'customers.manage');

$router->post('/customers/{id}/contacts/{contactId}', static function (string $id, string $contactId): void {
    (new CustomerController())->updateContact($id, $contactId);
}, true, true, 'customers.manage');

$router->post('/customers/{id}/activities', static function (string $id): void {
    (new CustomerController())->storeActivity($id);
}, true, true, 'activities.manage');

$router->get('/activities', static function (): void {
    (new ActivityController())->index();
}, true, 'activities.view');

$router->post('/activities/{id}/complete', static function (string $id): void {
    (new ActivityController())->complete($id);
}, true, true, 'activities.manage');

$router->get('/products', static function (): void {
    (new ProductController())->index();
}, true, 'products.view');

$router->get('/products/new', static function (): void {
    (new ProductController())->create();
}, true, 'products.manage');

$router->post('/products', static function (): void {
    (new ProductController())->store();
}, true, true, 'products.manage');

$router->get('/products/{id}', static function (string $id): void {
    (new ProductController())->show($id);
}, true, 'products.view');

$router->get('/products/{id}/edit', static function (string $id): void {
    (new ProductController())->edit($id);
}, true, 'products.manage');

$router->post('/products/{id}', static function (string $id): void {
    (new ProductController())->update($id);
}, true, true, 'products.manage');

$router->post('/products/{id}/active', static function (string $id): void {
    (new ProductController())->deactivate($id);
}, true, true, 'products.manage');

$router->get('/categories', static function (): void {
    (new CategoryController())->index();
}, true, 'categories.manage');

$router->get('/categories/new', static function (): void {
    (new CategoryController())->create();
}, true, 'categories.manage');

$router->post('/categories', static function (): void {
    (new CategoryController())->store();
}, true, true, 'categories.manage');

$router->get('/categories/{id}/edit', static function (string $id): void {
    (new CategoryController())->edit($id);
}, true, 'categories.manage');

$router->post('/categories/{id}', static function (string $id): void {
    (new CategoryController())->update($id);
}, true, true, 'categories.manage');

$router->post('/categories/{id}/active', static function (string $id): void {
    (new CategoryController())->deactivate($id);
}, true, true, 'categories.manage');

$router->get('/suppliers', static function (): void {
    (new SupplierController())->index();
}, true, 'suppliers.view');

$router->get('/suppliers/new', static function (): void {
    (new SupplierController())->create();
}, true, 'suppliers.manage');

$router->post('/suppliers', static function (): void {
    (new SupplierController())->store();
}, true, true, 'suppliers.manage');

$router->get('/suppliers/{id}', static function (string $id): void {
    (new SupplierController())->show($id);
}, true, 'suppliers.view');

$router->get('/suppliers/{id}/edit', static function (string $id): void {
    (new SupplierController())->edit($id);
}, true, 'suppliers.manage');

$router->post('/suppliers/{id}', static function (string $id): void {
    (new SupplierController())->update($id);
}, true, true, 'suppliers.manage');

$router->post('/suppliers/{id}/active', static function (string $id): void {
    (new SupplierController())->deactivate($id);
}, true, true, 'suppliers.manage');

$router->get('/pricing-levels', static function (): void {
    (new PricingLevelController())->index();
}, true, 'pricing.view');

$router->get('/pricing-levels/{id}/edit', static function (string $id): void {
    (new PricingLevelController())->edit($id);
}, true, 'pricing.manage');

$router->post('/pricing-levels/{id}', static function (string $id): void {
    (new PricingLevelController())->update($id);
}, true, true, 'pricing.manage');

$router->get('/calculator', static function (): void {
    (new CalculatorController())->index();
}, true, 'calculator.use');

$router->post('/calculator/price', static function (): void {
    (new CalculatorController())->price();
}, true, true, 'calculator.use');

$router->get('/users', static function (): void {
    (new UserController())->index();
}, true, 'users.manage');

$router->get('/users/new', static function (): void {
    (new UserController())->create();
}, true, 'users.manage');

$router->post('/users', static function (): void {
    (new UserController())->store();
}, true, true, 'users.manage');

$router->get('/users/{id}/edit', static function (string $id): void {
    (new UserController())->edit($id);
}, true, 'users.manage');

$router->post('/users/{id}', static function (string $id): void {
    (new UserController())->update($id);
}, true, true, 'users.manage');

$router->post('/users/{id}/active', static function (string $id): void {
    (new UserController())->deactivate($id);
}, true, true, 'users.manage');

$router->get('/settings', static function (): void {
    (new SettingsController())->edit();
}, true, 'settings.manage');

$router->post('/settings', static function (): void {
    (new SettingsController())->update();
}, true, true, 'settings.manage');

$soon = static function (string $slug): void {
    (new PageController())->upcoming($slug);
};
$router->get('/quotes', static function () use ($soon): void {
    $soon('quotes');
});
$router->get('/jobs', static function () use ($soon): void {
    $soon('jobs');
});
$router->get('/production', static function () use ($soon): void {
    $soon('production');
});
$router->get('/stock', static function () use ($soon): void {
    $soon('stock');
});
$router->get('/invoices', static function () use ($soon): void {
    $soon('invoices');
});
