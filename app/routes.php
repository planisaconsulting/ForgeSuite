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
use App\Controllers\AdminController;
use App\Controllers\NotificationController;
use App\Controllers\ReportController;
use App\Controllers\ActivityController;
use App\Controllers\AttachmentController;
use App\Controllers\AuthController;
use App\Controllers\CalculatorController;
use App\Controllers\CreditNoteController;
use App\Controllers\FinanceReportController;
use App\Controllers\InventoryController;
use App\Controllers\InvoiceController;
use App\Controllers\PaymentController;
use App\Controllers\JobController;
use App\Controllers\PurchasingController;
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
use App\Controllers\PortalAdminController;
use App\Controllers\PlanningController;
use App\Controllers\PortalController;
use App\Controllers\RecipeController;
use App\Controllers\SiteSurveyController;
use App\Controllers\TemplateController;

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
    redirect('/schedule');
}, true, 'schedule.view');
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

$inventory = static function (): InventoryController {
    return new InventoryController();
};
$purchasing = static function (): PurchasingController {
    return new PurchasingController();
};

$router->get('/inventory', static function () use ($inventory): void { $inventory()->index(); }, true, 'inventory.view');
$router->get('/inventory/movements', static function () use ($inventory): void { $inventory()->movements(); }, true, 'inventory.view');
$router->get('/inventory/offcuts', static function () use ($inventory): void { $inventory()->offcuts(); }, true, 'inventory.view');
$router->post('/inventory/offcuts', static function () use ($inventory): void { $inventory()->offcut(); }, true, true, 'inventory.view');
$router->get('/inventory/counts', static function () use ($inventory): void { $inventory()->counts(); }, true, 'inventory.view');
$router->post('/inventory/counts', static function () use ($inventory): void { $inventory()->startCount(); }, true, true, 'inventory.count');
$router->get('/inventory/counts/{id}', static function (string $id) use ($inventory): void { $inventory()->count($id); }, true, 'inventory.view');
$router->post('/inventory/counts/{id}', static function (string $id) use ($inventory): void { $inventory()->saveCount($id); }, true, true, 'inventory.count');
$router->post('/inventory/counts/{id}/approve', static function (string $id) use ($inventory): void { $inventory()->approveCount($id); }, true, true, 'inventory.adjust');
$router->get('/inventory/requirements', static function () use ($inventory): void { $inventory()->requirements(); }, true, 'inventory.view');
$router->get('/inventory/code/{code}', static function (string $code) use ($inventory): void { $inventory()->code($code); }, true, 'inventory.view');
$router->get('/inventory/items/{id}', static function (string $id) use ($inventory): void { $inventory()->item($id); }, true, 'inventory.view');
$router->get('/inventory/items/{id}/label', static function (string $id) use ($inventory): void { $inventory()->label($id); }, true, 'inventory.view');
$router->post('/inventory/opening', static function () use ($inventory): void { $inventory()->opening(); }, true, true, 'inventory.receive');
$router->post('/inventory/adjustments', static function () use ($inventory): void { $inventory()->adjust(); }, true, true, 'inventory.adjust');
$router->post('/inventory/transfers', static function () use ($inventory): void { $inventory()->transfer(); }, true, true, 'inventory.transfer');
$router->post('/inventory/supplier-returns', static function () use ($inventory): void { $inventory()->supplierReturn(); }, true, true, 'inventory.adjust');
$router->post('/inventory/locations', static function () use ($inventory): void { $inventory()->saveLocation(); }, true, true, 'inventory.view');

$router->get('/purchasing', static function () use ($purchasing): void { $purchasing()->index(); }, true, 'purchasing.view');
$router->get('/purchasing/orders', static function () use ($purchasing): void { $purchasing()->orders(); }, true, 'purchasing.view');
$router->post('/purchasing/orders', static function () use ($purchasing): void { $purchasing()->create(); }, true, true, 'purchasing.create');
$router->get('/purchasing/orders/{id}', static function (string $id) use ($purchasing): void { $purchasing()->show($id); }, true, 'purchasing.view');
$router->post('/purchasing/orders/{id}/lines', static function (string $id) use ($purchasing): void { $purchasing()->addLine($id); }, true, true, 'purchasing.view');
$router->post('/purchasing/orders/{id}/status', static function (string $id) use ($purchasing): void { $purchasing()->status($id); }, true, true, 'purchasing.view');
$router->post('/purchasing/orders/{id}/receive', static function (string $id) use ($purchasing): void { $purchasing()->receive($id); }, true, true, 'purchasing.view');
$router->get('/purchasing/orders/{id}/pdf', static function (string $id) use ($purchasing): void { $purchasing()->pdf($id); }, true, 'purchasing.view');
$router->get('/purchasing/requests', static function () use ($purchasing): void { $purchasing()->requests(); }, true, 'purchasing.view');
$router->post('/purchasing/requests', static function () use ($purchasing): void { $purchasing()->storeRequest(); }, true, true, 'inventory.view');
$router->post('/purchasing/requests/consolidate', static function () use ($purchasing): void { $purchasing()->consolidate(); }, true, true, 'purchasing.create');
$router->post('/purchasing/requests/{id}', static function (string $id) use ($purchasing): void { $purchasing()->decide($id); }, true, true, 'purchasing.approve');
$router->post('/purchasing/supplier-prices', static function () use ($purchasing): void { $purchasing()->supplierPrice(); }, true, true, 'supplier_prices.edit');

$router->post('/jobs/{id}/reserve', static function (string $id): void {
    $jobId = route_id($id);
    $result = (new \App\Services\StockMovementService())->reserve(array_merge($_POST, ['job_id' => $jobId]), (int) auth_user()['id']);
    if ($result['errors'] !== []) {
        flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors'])));
    } else {
        flash('success', 'Stock reserved.');
    }
    redirect('/jobs/' . $jobId . '?tab=materials');
}, true, true, 'jobs.view');

$router->post('/jobs/{id}/reservations/{reservationId}/release', static function (string $id, string $reservationId): void {
    $errors = (new \App\Services\StockMovementService())->release(route_id($reservationId), (int) auth_user()['id']);
    if ($errors !== []) {
        flash('error', (string) ($errors['_form'] ?? reset($errors)));
    } else {
        flash('success', 'Reservation released.');
    }
    redirect('/jobs/' . route_id($id) . '?tab=materials');
}, true, true, 'jobs.view');

$router->post('/jobs/{id}/stock-return', static function (string $id): void {
    $jobId = route_id($id);
    $result = (new \App\Services\StockMovementService())->jobReturn(array_merge($_POST, ['job_id' => $jobId]), (int) auth_user()['id']);
    if ($result['errors'] !== []) {
        flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors'])));
    } else {
        flash('success', 'Material returned to stock.');
    }
    redirect('/jobs/' . $jobId . '?tab=materials');
}, true, true, 'jobs.view');

$router->get('/invoices', static function (): void {
    (new InvoiceController())->index();
}, true, 'invoices.view');
$router->get('/invoices/new', static function (): void {
    (new InvoiceController())->createForm();
}, true, 'invoices.create');
$router->post('/invoices', static function (): void {
    (new InvoiceController())->store();
}, true, true, 'invoices.create');
$router->get('/invoices/{id}/pdf', static function (string $id): void {
    (new InvoiceController())->pdf($id);
}, true, 'invoices.view');
$router->get('/invoices/{id}', static function (string $id): void {
    (new InvoiceController())->show($id);
}, true, 'invoices.view');
$router->post('/invoices/{id}/lines', static function (string $id): void {
    (new InvoiceController())->addLine($id);
}, true, true, 'invoices.view');
$router->post('/invoices/{id}/issue', static function (string $id): void {
    (new InvoiceController())->issue($id);
}, true, true, 'invoices.view');
$router->post('/invoices/{id}/cancel', static function (string $id): void {
    (new InvoiceController())->cancel($id);
}, true, true, 'invoices.view');
$router->post('/invoices/{id}/flag', static function (string $id): void {
    (new InvoiceController())->flag($id);
}, true, true, 'invoices.view');
$router->post('/invoices/{id}', static function (string $id): void {
    (new InvoiceController())->save($id);
}, true, true, 'invoices.view');

$router->get('/payments', static function (): void {
    (new PaymentController())->index();
}, true, 'payments.view');
$router->post('/payments', static function (): void {
    (new PaymentController())->store();
}, true, true, 'payments.view');
$router->get('/payments/{id}/receipt', static function (string $id): void {
    (new PaymentController())->receipt($id);
}, true, 'payments.view');
$router->get('/payments/{id}', static function (string $id): void {
    (new PaymentController())->show($id);
}, true, 'payments.view');
$router->post('/payments/{id}/allocate', static function (string $id): void {
    (new PaymentController())->allocate($id);
}, true, true, 'payments.view');
$router->post('/payments/{id}/reverse', static function (string $id): void {
    (new PaymentController())->reverse($id);
}, true, true, 'payments.view');

$router->get('/credit-notes', static function (): void {
    (new CreditNoteController())->index();
}, true, 'credit_notes.view');
$router->post('/credit-notes', static function (): void {
    (new CreditNoteController())->store();
}, true, true, 'credit_notes.view');
$router->get('/credit-notes/{id}/pdf', static function (string $id): void {
    (new CreditNoteController())->pdf($id);
}, true, 'credit_notes.view');
$router->get('/credit-notes/{id}', static function (string $id): void {
    (new CreditNoteController())->show($id);
}, true, 'credit_notes.view');
$router->post('/credit-notes/{id}/issue', static function (string $id): void {
    (new CreditNoteController())->issue($id);
}, true, true, 'credit_notes.view');

$router->get('/finance/debtors', static function (): void {
    (new FinanceReportController())->debtors();
}, true, 'debtors.view');
$router->get('/finance/vat', static function (): void {
    (new FinanceReportController())->vat();
}, true, 'finance.vat_report.view');
$router->get('/finance/statements', static function (): void {
    if (($_GET['customer_id'] ?? '') !== '') {
        (new FinanceReportController())->statement();
        return;
    }
    (new FinanceReportController())->statementForm();
}, true, 'statements.view');
$router->post('/customers/{id}/account', static function (string $id): void {
    (new FinanceReportController())->hold($id);
}, true, true, 'customers.view');

$router->post('/jobs/{id}/variations', static function (string $id): void {
    $result = (new \App\Services\VariationService())->create(route_id($id), $_POST, (int) auth_user()['id']);
    if ($result['errors'] !== []) {
        flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors'])));
    } else {
        flash('success', 'Variation drafted.');
    }
    redirect('/jobs/' . route_id($id) . '?tab=finance');
}, true, true, 'jobs.view');
$router->post('/jobs/{id}/variations/{variationId}/items', static function (string $id, string $variationId): void {
    $errors = (new \App\Services\VariationService())->addItem(route_id($variationId), $_POST, (int) auth_user()['id']);
    if ($errors !== []) {
        flash('error', (string) ($errors['_form'] ?? reset($errors)));
    } else {
        flash('success', 'Variation line added.');
    }
    redirect('/jobs/' . route_id($id) . '?tab=finance');
}, true, true, 'jobs.view');
$router->post('/jobs/{id}/variations/{variationId}/approve', static function (string $id, string $variationId): void {
    $errors = (new \App\Services\VariationService())->approve(route_id($variationId), (int) auth_user()['id'], (string) ($_POST['approved_by_name'] ?? ''), (string) ($_POST['approval_method'] ?? 'EMAIL'));
    if ($errors !== []) {
        flash('error', (string) ($errors['_form'] ?? reset($errors)));
    } else {
        flash('success', 'Variation approved.');
    }
    redirect('/jobs/' . route_id($id) . '?tab=finance');
}, true, true, 'jobs.view');

$reports = [
    'executive' => 'reports.executive',
    'sales' => 'reports.sales',
    'customers' => 'reports.sales',
    'jobs' => 'reports.operations',
    'profitability' => 'reports.profitability',
    'production' => 'reports.operations',
    'waste' => 'reports.operations',
    'inventory' => 'reports.inventory',
    'purchasing' => 'reports.inventory',
    'finance' => 'reports.finance',
    'debtors' => 'reports.finance',
];
foreach ($reports as $code => $permission) {
    $router->get('/reports/' . $code, static function () use ($code): void {
        (new ReportController())->show($code);
    }, true, $permission);
    $router->get('/reports/' . $code . '.csv', static function () use ($code): void {
        (new ReportController())->csv($code);
    }, true, $permission);
    $router->get('/reports/' . $code . '.pdf', static function () use ($code): void {
        (new ReportController())->pdf($code);
    }, true, $permission);
}

$router->get('/notifications', static function (): void {
    (new NotificationController())->index();
});
$router->post('/notifications/read-all', static function (): void {
    (new NotificationController())->readAll();
}, true, true);
$router->post('/notifications/{id}/read', static function (string $id): void {
    (new NotificationController())->read($id);
}, true, true);
$router->get('/notifications/preferences', static function (): void {
    (new NotificationController())->preferences();
});
$router->post('/notifications/preferences', static function (): void {
    (new NotificationController())->savePreferences();
}, true, true);
$router->get('/reminders', static function (): void {
    (new NotificationController())->reminders();
});
$router->post('/reminders/{id}/status', static function (string $id): void {
    (new NotificationController())->completeReminder($id);
}, true, true);

$router->get('/documents', static function (): void {
    (new AdminController())->documents();
});
$router->get('/admin/audit', static function (): void {
    (new AdminController())->audit();
}, true, 'audit.view');
$router->get('/admin/logins', static function (): void {
    (new AdminController())->logins();
}, true, 'audit.view');
$router->get('/admin/health', static function (): void {
    (new AdminController())->health();
}, true, 'system.health');
$router->get('/admin/logs', static function (): void {
    (new AdminController())->logs();
}, true, 'system.logs');
$router->get('/admin/backups', static function (): void {
    (new AdminController())->backups();
}, true, 'system.backup');
$router->post('/admin/backups', static function (): void {
    (new AdminController())->createBackup();
}, true, true, 'system.backup');
$router->get('/admin/backups/{id}/download', static function (string $id): void {
    (new AdminController())->downloadBackup($id);
}, true, 'system.backup');
$router->get('/admin/automations', static function (): void {
    (new AdminController())->automations();
}, true, 'automations.view');
$router->post('/admin/automations', static function (): void {
    (new AdminController())->saveAutomation();
}, true, true, 'automations.manage');
$router->get('/admin/roles', static function (): void {
    (new AdminController())->roles();
}, true, 'users.manage');
$router->get('/admin/targets', static function (): void {
    (new AdminController())->targets();
}, true, 'settings.manage');
$router->post('/admin/targets', static function (): void {
    (new AdminController())->saveTargets();
}, true, true, 'settings.manage');
$router->get('/admin/export', static function (): void {
    (new AdminController())->exportForm();
}, true, 'reports.export');
$router->get('/admin/export.csv', static function (): void {
    (new AdminController())->exportCsv();
}, true, 'reports.export');
$router->post('/quotes/{id}/follow-up', static function (string $id): void {
    (new QuoteController())->followUp($id);
}, true, true, 'quotes.manage');
$router->post('/customers/{id}/communications', static function (string $id): void {
    (new CustomerController())->storeCommunication($id);
}, true, true, 'activities.manage');

$router->get('/surveys', static function (): void {
    (new SiteSurveyController())->index();
}, true, 'site_surveys.view');
$router->get('/surveys/new', static function (): void {
    (new SiteSurveyController())->create();
}, true, 'site_surveys.create');
$router->post('/surveys', static function (): void {
    (new SiteSurveyController())->store();
}, true, true, 'site_surveys.create');
$router->get('/surveys/{id}', static function (string $id): void {
    (new SiteSurveyController())->show($id);
}, true, 'site_surveys.view');
$router->get('/surveys/{id}/edit', static function (string $id): void {
    (new SiteSurveyController())->edit($id);
}, true, 'site_surveys.edit');
$router->post('/surveys/{id}', static function (string $id): void {
    (new SiteSurveyController())->update($id);
}, true, true, 'site_surveys.edit');
$router->post('/surveys/{id}/measurements', static function (string $id): void {
    (new SiteSurveyController())->measure($id);
}, true, true, 'site_surveys.edit');
$router->post('/surveys/{id}/photos', static function (string $id): void {
    (new SiteSurveyController())->photo($id);
}, true, true, 'site_surveys.edit');
$router->get('/surveys/{id}/pdf', static function (string $id): void {
    (new SiteSurveyController())->pdf($id);
}, true, 'site_surveys.view');

$router->get('/recipes', static function (): void {
    (new RecipeController())->index();
}, true, 'recipes.view');
$router->get('/recipes/new', static function (): void {
    (new RecipeController())->create();
}, true, 'recipes.create');
$router->post('/recipes', static function (): void {
    (new RecipeController())->store();
}, true, true, 'recipes.create');
$router->get('/recipes/test', static function (): void {
    (new RecipeController())->testForm();
}, true, 'recipes.test');
$router->post('/recipes/test', static function (): void {
    (new RecipeController())->test();
}, true, true, 'recipes.test');
$router->get('/recipes/{id}/edit', static function (string $id): void {
    (new RecipeController())->edit($id);
}, true, 'recipes.edit');
$router->post('/recipes/{id}', static function (string $id): void {
    (new RecipeController())->update($id);
}, true, true, 'recipes.edit');
$router->post('/recipes/{id}/duplicate', static function (string $id): void {
    (new RecipeController())->duplicate($id);
}, true, true, 'recipes.create');
$router->post('/recipes/{id}/deactivate', static function (string $id): void {
    (new RecipeController())->deactivate($id);
}, true, true, 'recipes.deactivate');

$router->get('/templates', static function (): void {
    (new TemplateController())->index();
}, true, 'templates.view');
$router->get('/templates/new', static function (): void {
    (new TemplateController())->create();
}, true, 'templates.manage');
$router->post('/templates', static function (): void {
    (new TemplateController())->store();
}, true, true, 'templates.manage');
$router->get('/templates/{id}/edit', static function (string $id): void {
    (new TemplateController())->edit($id);
}, true, 'templates.manage');
$router->post('/templates/{id}', static function (string $id): void {
    (new TemplateController())->update($id);
}, true, true, 'templates.manage');

$router->get('/quotes/{id}/configure', static function (string $id): void {
    (new RecipeController())->configure($id);
}, true, 'quotes.manage');
$router->post('/quotes/{id}/configure', static function (string $id): void {
    (new RecipeController())->addToQuote($id);
}, true, true, 'quotes.manage');
$router->post('/quotes/{id}/configure/preview', static function (string $id): void {
    (new RecipeController())->previewConfigure($id);
}, true, true, 'quotes.manage');

$router->get('/admin/portal', static function (): void {
    (new PortalAdminController())->index();
}, true, 'portal.access_manage');
$router->post('/admin/portal', static function (): void {
    (new PortalAdminController())->store();
}, true, true, 'portal.access_manage');
$router->post('/admin/portal/link', static function (): void {
    (new PortalAdminController())->link();
}, true, true, 'portal.access_manage');
$router->post('/admin/portal/deactivate', static function (): void {
    (new PortalAdminController())->deactivate();
}, true, true, 'portal.access_manage');

$router->get('/portal/login', static function (): void {
    (new PortalController())->loginForm();
}, false);
$router->post('/portal/login', static function (): void {
    (new PortalController())->login();
}, false, true);
$router->get('/portal/access/{token}', static function (string $token): void {
    (new PortalController())->access($token);
}, false);
$router->post('/portal/logout', static function (): void {
    (new PortalController())->logout();
}, false, true);
$router->get('/portal', static function (): void {
    (new PortalController())->home();
}, false);
$router->get('/portal/quotes/{id}', static function (string $id): void {
    (new PortalController())->quote($id);
}, false);
$router->get('/portal/quotes/{id}/pdf', static function (string $id): void {
    (new PortalController())->quotePdf($id);
}, false);
$router->post('/portal/quotes/{id}/accept', static function (string $id): void {
    (new PortalController())->acceptQuote($id);
}, false, true);
$router->post('/portal/quotes/{id}/decline', static function (string $id): void {
    (new PortalController())->declineQuote($id);
}, false, true);
$router->post('/portal/quotes/{id}/changes', static function (string $id): void {
    (new PortalController())->quoteChange($id);
}, false, true);
$router->get('/portal/quotes/{id}/whatsapp', static function (string $id): void {
    (new PortalController())->whatsapp($id);
}, false);
$router->get('/portal/artwork/{id}', static function (string $id): void {
    (new PortalController())->artwork($id);
}, false);
$router->get('/portal/artwork/{id}/file', static function (string $id): void {
    (new PortalController())->artworkFile($id);
}, false);
$router->post('/portal/artwork/{id}/approve', static function (string $id): void {
    (new PortalController())->approveArtwork($id);
}, false, true);
$router->post('/portal/artwork/{id}/changes', static function (string $id): void {
    (new PortalController())->artworkChange($id);
}, false, true);
$router->get('/portal/jobs/{id}', static function (string $id): void {
    (new PortalController())->job($id);
}, false);
$router->get('/portal/invoices/{id}', static function (string $id): void {
    (new PortalController())->invoice($id);
}, false);
$router->get('/portal/invoices/{id}/pdf', static function (string $id): void {
    (new PortalController())->invoicePdf($id);
}, false);
$router->get('/portal/statement', static function (): void {
    (new PortalController())->statement();
}, false);
$router->post('/portal/files', static function (): void {
    (new PortalController())->upload();
}, false, true);
$router->get('/portal/files/{id}', static function (string $id): void {
    (new PortalController())->file($id);
}, false);
$router->post('/portal/messages', static function (): void {
    (new PortalController())->message();
}, false, true);

$router->get('/work', static function (): void {
    (new PlanningController())->myWork();
}, true, 'schedule.view');
$router->post('/work/start', static function (): void {
    (new PlanningController())->startTask();
}, true, true, 'schedule.view');
$router->post('/work/complete', static function (): void {
    (new PlanningController())->completeTask();
}, true, true, 'schedule.view');
$router->post('/work/block', static function (): void {
    (new PlanningController())->blockTask();
}, true, true, 'schedule.view');
$router->get('/today', static function (): void {
    (new PlanningController())->today();
}, true, 'schedule.view');
$router->get('/schedule', static function (): void {
    (new PlanningController())->schedule();
}, true, 'schedule.view');
$router->post('/schedule/entries', static function (): void {
    (new PlanningController())->storeEntry();
}, true, true, 'schedule.manage');
$router->post('/schedule/move', static function (): void {
    (new PlanningController())->moveEntry();
}, true, true, 'schedule.manage');
$router->get('/capacity', static function (): void {
    (new PlanningController())->capacity();
}, true, 'capacity.view');
$router->get('/resources', static function (): void {
    (new PlanningController())->resources();
}, true, 'resources.view');
$router->get('/resources/new', static function (): void {
    (new PlanningController())->resourceForm();
}, true, 'resources.manage');
$router->post('/resources', static function (): void {
    (new PlanningController())->resourceSave();
}, true, true, 'resources.manage');
$router->post('/resources/unavailability', static function (): void {
    (new PlanningController())->unavailableSave();
}, true, true, 'staff_availability.manage');
$router->get('/resources/calendar', static function (): void {
    (new PlanningController())->exceptions();
}, true, 'resources.view');
$router->post('/resources/calendar', static function (): void {
    (new PlanningController())->exceptionSave();
}, true, true, 'resources.manage');
$router->get('/maintenance', static function (): void {
    (new PlanningController())->maintenance();
}, true, 'maintenance.view');
$router->post('/maintenance', static function (): void {
    (new PlanningController())->maintenanceSave();
}, true, true, 'maintenance.manage');
$router->get('/vehicles', static function (): void {
    (new PlanningController())->vehicles();
}, true, 'vehicles.view');
$router->post('/vehicles/mileage', static function (): void {
    (new PlanningController())->mileageSave();
}, true, true, 'vehicles.view');
$router->get('/installations/planner', static function (): void {
    (new PlanningController())->installations();
}, true, 'installations.view');
$router->get('/field/{id}', static function (string $id): void {
    (new PlanningController())->field($id);
}, true, 'installations.view');
$router->post('/field/{id}', static function (string $id): void {
    (new PlanningController())->fieldAct($id);
}, true, true, 'installations.view');
$router->get('/recurring', static function (): void {
    (new PlanningController())->recurring();
}, true, 'recurring_jobs.view');
$router->post('/recurring', static function (): void {
    (new PlanningController())->recurringSave();
}, true, true, 'recurring_jobs.manage');
$router->post('/recurring/run', static function (): void {
    (new PlanningController())->recurringRun();
}, true, true, 'recurring_jobs.manage');
$router->get('/subcontracts', static function (): void {
    (new PlanningController())->subcontracts();
}, true, 'subcontractors.view');
$router->post('/subcontracts', static function (): void {
    (new PlanningController())->subcontractSave();
}, true, true, 'subcontractors.manage');
$router->post('/subcontracts/complete', static function (): void {
    (new PlanningController())->subcontractComplete();
}, true, true, 'subcontractors.manage');
$router->get('/reports/utilisation', static function (): void {
    (new PlanningController())->utilisation();
}, true, 'capacity.view');
$router->get('/reports/downtime', static function (): void {
    (new PlanningController())->downtime();
}, true, 'maintenance.view');
$router->get('/reports/capacity-demand', static function (): void {
    (new PlanningController())->demand();
}, true, 'capacity.view');
$router->get('/reports/late-jobs', static function (): void {
    (new PlanningController())->lateJobs();
}, true, 'reports.operations');

$soon = static function (string $slug): void {
    (new PageController())->upcoming($slug);
};
