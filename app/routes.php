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
use App\Controllers\AttachmentController;
use App\Controllers\AuthController;
use App\Controllers\CalculatorController;
use App\Controllers\JobController;
use App\Controllers\OpportunityController;
use App\Controllers\QuoteController;
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

$router->post('/calculator/quote', static function (): void {
    (new CalculatorController())->addToQuote();
}, true, true, 'quotes.manage');

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

$router->get('/opportunities', static function (): void {
    (new OpportunityController())->index();
}, true, 'opportunities.view');
$router->get('/opportunities/new', static function (): void {
    (new OpportunityController())->create();
}, true, 'opportunities.manage');
$router->post('/opportunities', static function (): void {
    (new OpportunityController())->store();
}, true, true, 'opportunities.manage');
$router->get('/opportunities/{id}', static function (string $id): void {
    (new OpportunityController())->show($id);
}, true, 'opportunities.view');
$router->get('/opportunities/{id}/edit', static function (string $id): void {
    (new OpportunityController())->edit($id);
}, true, 'opportunities.manage');
$router->post('/opportunities/{id}', static function (string $id): void {
    (new OpportunityController())->update($id);
}, true, true, 'opportunities.manage');
$router->post('/opportunities/{id}/win', static function (string $id): void {
    (new OpportunityController())->win($id);
}, true, true, 'opportunities.manage');
$router->post('/opportunities/{id}/lose', static function (string $id): void {
    (new OpportunityController())->lose($id);
}, true, true, 'opportunities.manage');
$router->post('/opportunities/{id}/activity', static function (string $id): void {
    (new OpportunityController())->activity($id);
}, true, true, 'activities.manage');

$router->get('/quotes', static function (): void {
    (new QuoteController())->index();
}, true, 'quotes.view');
$router->get('/quotes/new', static function (): void {
    (new QuoteController())->create();
}, true, 'quotes.manage');
$router->post('/quotes', static function (): void {
    (new QuoteController())->store();
}, true, true, 'quotes.manage');
$router->get('/quotes/{id}', static function (string $id): void {
    (new QuoteController())->show($id);
}, true, 'quotes.view');
$router->get('/quotes/{id}/edit', static function (string $id): void {
    (new QuoteController())->edit($id);
}, true, 'quotes.manage');
$router->post('/quotes/{id}', static function (string $id): void {
    (new QuoteController())->save($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/autosave', static function (string $id): void {
    (new QuoteController())->autosave($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/lines', static function (string $id): void {
    (new QuoteController())->addLine($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/lines/custom', static function (string $id): void {
    (new QuoteController())->addCustom($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/lines/{lineId}', static function (string $id, string $lineId): void {
    (new QuoteController())->updateLine($id, $lineId);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/lines/{lineId}/duplicate', static function (string $id, string $lineId): void {
    (new QuoteController())->duplicateLine($id, $lineId);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/lines/{lineId}/delete', static function (string $id, string $lineId): void {
    (new QuoteController())->removeLine($id, $lineId);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/reorder', static function (string $id): void {
    (new QuoteController())->reorder($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/sections', static function (string $id): void {
    (new QuoteController())->addSection($id);
}, true, true, 'quotes.manage');
$router->get('/quotes/{id}/refresh', static function (string $id): void {
    (new QuoteController())->refresh($id);
}, true, 'quotes.manage');
$router->post('/quotes/{id}/refresh', static function (string $id): void {
    (new QuoteController())->applyRefresh($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/revision', static function (string $id): void {
    (new QuoteController())->revise($id);
}, true, true, 'quotes.manage');
$router->get('/quotes/{id}/revisions/{number}', static function (string $id, string $number): void {
    (new QuoteController())->revision($id, $number);
}, true, 'quotes.view');
$router->get('/quotes/{id}/duplicate', static function (string $id): void {
    (new QuoteController())->duplicateForm($id);
}, true, 'quotes.manage');
$router->post('/quotes/{id}/duplicate', static function (string $id): void {
    (new QuoteController())->duplicate($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/status', static function (string $id): void {
    (new QuoteController())->status($id);
}, true, true, 'quotes.manage');
$router->get('/quotes/{id}/accept', static function (string $id): void {
    (new QuoteController())->acceptForm($id);
}, true, 'quotes.accept');
$router->post('/quotes/{id}/accept', static function (string $id): void {
    (new QuoteController())->accept($id);
}, true, true, 'quotes.accept');
$router->get('/quotes/{id}/convert', static function (string $id): void {
    (new QuoteController())->convertForm($id);
}, true, 'quotes.convert');
$router->post('/quotes/{id}/convert', static function (string $id): void {
    (new QuoteController())->convert($id);
}, true, true, 'quotes.convert');
$router->get('/quotes/{id}/pdf', static function (string $id): void {
    (new QuoteController())->pdf($id);
}, true, 'quotes.view');
$router->get('/quotes/{id}/print', static function (string $id): void {
    (new QuoteController())->printView($id);
}, true, 'quotes.view');

$router->get('/jobs', static function (): void {
    (new JobController())->index();
}, true, 'jobs.view');
$router->get('/jobs/board', static function (): void {
    (new JobController())->board();
}, true, 'production.view');
$router->get('/jobs/workshop', static function (): void {
    (new JobController())->workshop();
}, true, 'production.view');
$router->get('/jobs/design', static function (): void {
    (new JobController())->design();
}, true, 'jobs.view');
$router->get('/jobs/installations', static function (): void {
    (new JobController())->installations();
}, true, 'installations.view');
$router->get('/jobs/schedule', static function (): void {
    (new JobController())->schedule();
}, true, 'production.view');
$router->get('/jobs/setup', static function (): void {
    (new JobController())->setup();
}, true, 'settings.manage');
$router->post('/jobs/setup/stages', static function (): void {
    (new JobController())->saveStage();
}, true, true, 'settings.manage');
$router->post('/jobs/setup/templates', static function (): void {
    (new JobController())->saveTemplate();
}, true, true, 'settings.manage');
$router->post('/jobs/setup/teams', static function (): void {
    (new JobController())->saveTeam();
}, true, true, 'settings.manage');
$router->get('/jobs/{id}', static function (string $id): void {
    (new JobController())->show($id);
}, true, 'jobs.view');
$router->post('/jobs/{id}', static function (string $id): void {
    (new JobController())->save($id);
}, true, true, 'jobs.edit');
$router->post('/jobs/{id}/status', static function (string $id): void {
    (new JobController())->status($id);
}, true, true, 'jobs.view');
$router->post('/jobs/{id}/tasks', static function (string $id): void {
    (new JobController())->task($id);
}, true, true, 'jobs.view');
$router->post('/jobs/{id}/tasks/{taskId}', static function (string $id, string $taskId): void {
    (new JobController())->taskStatus($id, $taskId);
}, true, true, 'jobs.view');
$router->post('/jobs/{id}/route', static function (string $id): void {
    (new JobController())->route($id);
}, true, true, 'production.update');
$router->post('/jobs/{id}/stages/{stageId}', static function (string $id, string $stageId): void {
    (new JobController())->stage($id, $stageId);
}, true, true, 'production.update');
$router->post('/jobs/{id}/items/{itemId}', static function (string $id, string $itemId): void {
    (new JobController())->itemStatus($id, $itemId);
}, true, true, 'production.update');
$router->post('/jobs/{id}/artwork', static function (string $id): void {
    (new JobController())->artwork($id);
}, true, true, 'artwork.upload');
$router->post('/jobs/{id}/artwork/{artworkId}/status', static function (string $id, string $artworkId): void {
    (new JobController())->artworkStatus($id, $artworkId);
}, true, true, 'artwork.upload');
$router->post('/jobs/{id}/artwork/{artworkId}/approval', static function (string $id, string $artworkId): void {
    (new JobController())->approval($id, $artworkId);
}, true, true, 'artwork.approve_record');
$router->get('/jobs/{id}/artwork/{artworkId}/file', static function (string $id, string $artworkId): void {
    (new JobController())->artworkFile($id, $artworkId);
}, true, 'jobs.view');
$router->post('/jobs/{id}/material', static function (string $id): void {
    (new JobController())->material($id);
}, true, true, 'materials.record_usage');
$router->post('/jobs/{id}/requirements', static function (string $id): void {
    (new JobController())->requirement($id);
}, true, true, 'jobs.view');
$router->post('/jobs/{id}/time', static function (string $id): void {
    (new JobController())->time($id);
}, true, true, 'time.record');
$router->post('/jobs/{id}/timer/start', static function (string $id): void {
    (new JobController())->timerStart($id);
}, true, true, 'time.record');
$router->post('/jobs/{id}/timer/stop', static function (string $id): void {
    (new JobController())->timerStop($id);
}, true, true, 'time.record');
$router->post('/jobs/{id}/costs', static function (string $id): void {
    (new JobController())->other($id);
}, true, true, 'costing.edit');
$router->post('/jobs/{id}/quality', static function (string $id): void {
    (new JobController())->quality($id);
}, true, true, 'production.update');
$router->post('/jobs/{id}/installations', static function (string $id): void {
    (new JobController())->installation($id);
}, true, true, 'installations.schedule');
$router->post('/jobs/{id}/installations/{installationId}', static function (string $id, string $installationId): void {
    (new JobController())->installationUpdate($id, $installationId);
}, true, true, 'installations.view');
$router->post('/jobs/{id}/installations/{installationId}/checklist/{itemId}', static function (string $id, string $installationId, string $itemId): void {
    (new JobController())->checklist($id, $installationId, $itemId);
}, true, true, 'installations.view');
$router->post('/jobs/{id}/installations/{installationId}/photo', static function (string $id, string $installationId): void {
    (new JobController())->photo($id, $installationId);
}, true, true, 'installations.view');
$router->post('/jobs/{id}/files', static function (string $id): void {
    (new JobController())->jobFile($id);
}, true, true, 'attachments.manage');
$router->post('/jobs/{id}/archive', static function (string $id): void {
    (new JobController())->archive($id);
}, true, true, 'jobs.complete');
$router->get('/jobs/{id}/card', static function (string $id): void {
    (new JobController())->card($id);
}, true, 'jobs.view');
$router->get('/jobs/{id}/card.pdf', static function (string $id): void {
    (new JobController())->cardPdf($id);
}, true, 'jobs.view');

$router->post('/attachments/{type}/{id}', static function (string $type, string $id): void {
    (new AttachmentController())->store($type, $id);
}, true, true, 'attachments.manage');
$router->get('/attachments/{id}', static function (string $id): void {
    (new AttachmentController())->download($id);
}, true, 'attachments.manage');

$soon = static function (string $slug): void {
    (new PageController())->upcoming($slug);
};
$router->get('/stock', static function () use ($soon): void {
    $soon('stock');
});
$router->get('/invoices', static function () use ($soon): void {
    $soon('invoices');
});
