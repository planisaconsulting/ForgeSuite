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
use App\Controllers\ProjectController;
use App\Controllers\PurchasingController;
use App\Controllers\OpportunityController;
use App\Controllers\QuoteController;
use App\Controllers\CategoryController;
use App\Controllers\CustomerController;
use App\Controllers\CommunicationCentreController;
use App\Controllers\DashboardController;
use App\Controllers\IntegrationController;
use App\Controllers\LeadController;
use App\Controllers\MarketingController;
use App\Controllers\PublicLeadController;
use App\Controllers\EstimatingController;
use App\Controllers\SignageController;
use App\Controllers\PageController;
use App\Controllers\PricingLevelController;
use App\Controllers\ProductController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\SupplierController;
use App\Controllers\UserController;
use App\Controllers\PortalAdminController;
use App\Controllers\PlanningController;
use App\Controllers\BusinessPlanningController;
use App\Controllers\ApiV1Controller;
use App\Controllers\AssetController;
use App\Controllers\PlatformController;
use App\Controllers\PortalController;
use App\Controllers\RecipeController;
use App\Controllers\SiteSurveyController;
use App\Controllers\TemplateController;
use App\Controllers\DispatchController;
use App\Controllers\WorkshopController;
use App\Controllers\MobileController;

$router->get('/health', static function (): void {
    json_response(\App\Services\RuntimeService::publicHealth());
}, false);

$router->get('/help', static function (): void {
    $role = (string) (auth_user()['role_code'] ?? '');
    \App\Helpers\View::render('help/index', [
        'title' => 'Help',
        'activeNav' => 'help',
        'release' => \App\Services\RuntimeService::releaseLabel(),
        'sections' => [
            ['title' => 'Customer', 'body' => 'Open Customers, add the company or person, then a contact and a site address.', 'href' => '/customers', 'link' => 'Customers'],
            ['title' => 'Quote', 'body' => 'Start from an opportunity or a customer. Send only after the lines and VAT look right. A revision keeps the old total.', 'href' => '/quotes', 'link' => 'Quotes'],
            ['title' => 'Job', 'body' => 'Accept the quote, then convert it. The job keeps the accepted prices.', 'href' => '/jobs', 'link' => 'Jobs'],
            ['title' => 'Material', 'body' => 'Issue material from the workshop scan while online. Stock moves only when the server accepts the issue.', 'href' => '/workshop/scan', 'link' => 'Scan'],
            ['title' => 'Installation', 'body' => 'Download the field pack before you leave. A signature waits until sync confirms it.', 'href' => '/m', 'link' => 'My work'],
            ['title' => 'Payment', 'body' => 'Record the payment, then allocate it. An overpayment stays as customer credit. It does not make an invoice negative.', 'href' => '/payments', 'link' => 'Payments'],
            ['title' => 'Your role', 'body' => 'Signed in as ' . $role . '. The desk on the home screen lists the next places for this role.', 'href' => '/', 'link' => 'Dashboard'],
        ],
    ]);
});

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

$router->get('/estimates', static function (): void {
    (new EstimatingController())->index();
}, true, 'estimates.view');
$router->get('/estimates/new', static function (): void {
    (new EstimatingController())->create();
}, true, 'estimates.create');
$router->post('/estimates', static function (): void {
    (new EstimatingController())->store();
}, true, true, 'estimates.create');
$router->get('/estimates/{id}', static function (string $id): void {
    (new EstimatingController())->show($id);
}, true, 'estimates.view');
$router->post('/estimates/{id}/approve', static function (string $id): void {
    (new EstimatingController())->approve($id);
}, true, true, 'estimates.approve');
$router->get('/estimating/sheets', static function (): void {
    (new EstimatingController())->sheets();
}, true, 'yield.view');
$router->post('/estimating/sheets', static function (): void {
    (new EstimatingController())->sheets();
}, true, true, 'yield.view');
$router->get('/estimating/rolls', static function (): void {
    (new EstimatingController())->rolls();
}, true, 'yield.view');
$router->post('/estimating/rolls', static function (): void {
    (new EstimatingController())->rolls();
}, true, true, 'yield.view');
$router->get('/estimating/installation', static function (): void {
    (new EstimatingController())->installation();
}, true, 'estimates.view');
$router->post('/estimating/installation', static function (): void {
    (new EstimatingController())->installation();
}, true, true, 'estimates.create');
$router->get('/estimating/vehicles', static function (): void {
    (new EstimatingController())->vehicles();
}, true, 'estimates.view');
$router->post('/estimating/vehicles', static function (): void {
    (new EstimatingController())->vehicles();
}, true, true, 'estimates.create');
$router->get('/estimating/signage', static function (): void {
    (new SignageController())->index();
}, true, 'estimators.use');
$router->get('/estimating/signage/preview', static function (): void {
    (new SignageController())->preview();
}, true, 'estimators.use');
$router->get('/estimating/signage/reports/{slug}', static function (string $slug): void {
    (new SignageController())->report($slug);
}, true, 'estimators.view_costs');
$router->get('/estimating/signage/calculations/{id}', static function (string $id): void {
    (new SignageController())->show($id);
}, true, 'estimators.use');
$router->get('/estimating/signage/{type}', static function (string $type): void {
    (new SignageController())->form($type);
}, true, 'estimators.use');
$router->post('/estimating/signage/{type}', static function (string $type): void {
    (new SignageController())->calculate($type);
}, true, true, 'estimators.use');
$router->get('/specifications', static function (): void {
    (new SignageController())->specifications();
}, true, 'specifications.view');
$router->post('/specifications', static function (): void {
    (new SignageController())->saveSpecification();
}, true, true, 'specifications.create');
$router->get('/specifications/{id}', static function (string $id): void {
    (new SignageController())->specification($id);
}, true, 'specifications.view');
$router->post('/specifications/{id}/approve', static function (string $id): void {
    (new SignageController())->approveSpecification($id);
}, true, true, 'specifications.approve');
$router->post('/specifications/{id}/revise', static function (string $id): void {
    (new SignageController())->reviseSpecification($id);
}, true, true, 'specifications.edit');
$router->get('/estimating/simulator', static function (): void {
    (new EstimatingController())->simulator();
}, true, 'estimates.view');
$router->post('/estimating/simulator', static function (): void {
    (new EstimatingController())->simulator();
}, true, true, 'estimates.view');
$router->get('/estimating/intelligence', static function (): void {
    (new EstimatingController())->intelligence();
}, true, 'pricing_intelligence.view');
$router->get('/estimating/recommendations', static function (): void {
    (new EstimatingController())->recommendations();
}, true, 'pricing_intelligence.view');
$router->post('/estimating/recommendations/{id}', static function (string $id): void {
    (new EstimatingController())->recommendationAct($id);
}, true, true, 'pricing_recommendations.review');
$router->get('/reports/estimate-actual', static function (): void {
    (new EstimatingController())->variance();
}, true, 'historical_costing.view');
$router->get('/reports/recipe-accuracy', static function (): void {
    (new EstimatingController())->intelligence();
}, true, 'pricing_intelligence.view');
$router->get('/reports/material-yield', static function (): void {
    (new EstimatingController())->sheets();
}, true, 'yield.view');
$router->get('/reports/pricing-health', static function (): void {
    (new EstimatingController())->intelligence();
}, true, 'pricing_intelligence.view');

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
$router->get('/portal/assets', static function (): void {
    (new PortalController())->assets();
}, false);
$router->get('/portal/assets/{id}', static function (string $id): void {
    (new PortalController())->asset($id);
}, false);
$router->post('/portal/assets/{id}/report', static function (string $id): void {
    (new PortalController())->reportAsset($id);
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
$router->get('/portal/documents/{id}', static function (string $id): void {
    (new PortalController())->signedDocument($id);
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

$router->post('/api/public/leads', static function (): void {
    (new PublicLeadController())->store();
}, false, false);
$router->post('/api/integrations/{provider}/events', static function (string $provider): void {
    (new IntegrationController())->webhook($provider);
}, false, false);

$router->get('/leads', static function (): void {
    (new LeadController())->index();
}, true, 'leads.view');
$router->get('/leads/new', static function (): void {
    (new LeadController())->create();
}, true, 'leads.create');
$router->post('/leads', static function (): void {
    (new LeadController())->store();
}, true, true, 'leads.create');
$router->get('/leads/{id}', static function (string $id): void {
    (new LeadController())->show($id);
}, true, 'leads.view');
$router->post('/leads/{id}/assign', static function (string $id): void {
    (new LeadController())->assign($id);
}, true, true, 'leads.assign');
$router->post('/leads/{id}/call', static function (string $id): void {
    (new LeadController())->call($id);
}, true, true, 'communications.create');
$router->post('/leads/{id}/whatsapp', static function (string $id): void {
    (new LeadController())->whatsapp($id);
}, true, true, 'communications.whatsapp');
$router->post('/leads/{id}/note', static function (string $id): void {
    (new LeadController())->note($id);
}, true, true, 'communications.create');
$router->post('/leads/{id}/follow-up', static function (string $id): void {
    (new LeadController())->followUp($id);
}, true, true, 'leads.view');
$router->post('/leads/{id}/lost', static function (string $id): void {
    (new LeadController())->lost($id);
}, true, true, 'leads.mark_lost');
$router->post('/leads/{id}/spam', static function (string $id): void {
    (new LeadController())->spam($id);
}, true, true, 'leads.mark_lost');
$router->post('/leads/{id}/convert', static function (string $id): void {
    (new LeadController())->convert($id);
}, true, true, 'leads.convert');
$router->get('/follow-ups', static function (): void {
    (new LeadController())->followUps();
}, true, 'leads.view');
$router->get('/sales/desk', static function (): void {
    (new LeadController())->desk();
}, true, 'leads.view');

$router->get('/communications', static function (): void {
    (new CommunicationCentreController())->index();
}, true, 'communications.view');
$router->get('/communications/templates', static function (): void {
    (new CommunicationCentreController())->templates();
}, true, 'communication_templates.view');
$router->post('/communications/templates', static function (): void {
    (new CommunicationCentreController())->saveTemplate();
}, true, true, 'communication_templates.manage');
$router->post('/quotes/{id}/email-preview', static function (string $id): void {
    (new CommunicationCentreController())->quotePreview($id);
}, true, true, 'communications.send_email');
$router->post('/quotes/{id}/email', static function (string $id): void {
    (new CommunicationCentreController())->quoteSend($id);
}, true, true, 'communications.send_email');
$router->get('/customers/{id}/contacts/{contactId}/preferences', static function (string $id, string $contactId): void {
    (new CommunicationCentreController())->preferences($id, $contactId);
}, true, 'customers.view');
$router->post('/customers/{id}/contacts/{contactId}/preferences', static function (string $id, string $contactId): void {
    (new CommunicationCentreController())->savePreferences($id, $contactId);
}, true, true, 'customers.manage');
$router->post('/customers/{id}/contacts/{contactId}/unsubscribe', static function (string $id, string $contactId): void {
    (new CommunicationCentreController())->unsubscribe($id, $contactId);
}, true, true, 'customers.manage');

$router->get('/marketing/campaigns', static function (): void {
    (new MarketingController())->campaigns();
}, true, 'campaigns.view');
$router->post('/marketing/campaigns', static function (): void {
    (new MarketingController())->storeCampaign();
}, true, true, 'campaigns.manage');
$router->get('/marketing/campaigns/{id}', static function (string $id): void {
    (new MarketingController())->showCampaign($id);
}, true, 'campaigns.view');
$router->get('/marketing/sources', static function (): void {
    (new MarketingController())->sources();
}, true, 'marketing_reports.view');
$router->get('/marketing/retention', static function (): void {
    (new MarketingController())->retention();
}, true, 'customer_retention.view');
$router->get('/marketing/lists', static function (): void {
    (new MarketingController())->bulkPreview();
}, true, 'bulk_communications.send');
$router->get('/reports/communication-activity', static function (): void {
    (new MarketingController())->activity();
}, true, 'marketing_reports.view');
$router->get('/feedback', static function (): void {
    (new MarketingController())->feedback();
}, true, 'feedback.view');
$router->post('/feedback', static function (): void {
    (new MarketingController())->storeFeedback();
}, true, true, 'feedback.manage');
$router->post('/feedback/review', static function (): void {
    (new MarketingController())->review();
}, true, true, 'feedback.manage');
$router->get('/workshop', static function (): void {
    (new WorkshopController())->floor();
}, true, 'workshop.view');
$router->get('/workshop/board.json', static function (): void {
    (new WorkshopController())->counts();
}, true, 'workshop.view');
$router->get('/workshop/queue', static function (): void {
    (new WorkshopController())->queue();
}, true, 'workshop.view');
$router->get('/workshop/qc', static function (): void {
    (new WorkshopController())->qc();
}, true, 'qc.perform');
$router->post('/workshop/qc', static function (): void {
    (new WorkshopController())->quality();
}, true, true, 'qc.perform');
$router->get('/workshop/reprints', static function (): void {
    (new WorkshopController())->reprints();
}, true, 'production.reprint');
$router->get('/workshop/scan', static function (): void {
    (new WorkshopController())->scanForm();
}, true, 'workshop.scan');
$router->post('/workshop/scan', static function (): void {
    (new WorkshopController())->scanSubmit();
}, true, true, 'workshop.scan');
$router->post('/workshop/actions', static function (): void {
    (new WorkshopController())->action();
}, true, true, 'workshop.scan');
$router->post('/workshop/material', static function (): void {
    (new WorkshopController())->material();
}, true, true, 'workshop.scan');
$router->get('/workshop/items/{id}', static function (string $id): void {
    (new WorkshopController())->item($id);
}, true, 'workshop.view');
$router->get('/workshop/kiosk', static function (): void {
    (new WorkshopController())->kiosk();
}, false);
$router->post('/workshop/kiosk', static function (): void {
    (new WorkshopController())->kioskPin();
}, false, true);
$router->post('/workshop/kiosk/badge', static function (): void {
    (new WorkshopController())->kioskBadge();
}, false, true);
$router->get('/scan/{token}', static function (string $token): void {
    (new WorkshopController())->openToken($token);
}, true, 'workshop.scan');
$router->get('/dispatch', static function (): void {
    (new DispatchController())->index();
}, true, 'dispatch.view');
$router->post('/dispatch', static function (): void {
    (new DispatchController())->store();
}, true, true, 'dispatch.create');
$router->get('/dispatch/{id}', static function (string $id): void {
    (new DispatchController())->show($id);
}, true, 'dispatch.view');
$router->post('/dispatch/{id}/pack', static function (string $id): void {
    (new DispatchController())->pack($id);
}, true, true, 'dispatch.create');
$router->post('/dispatch/{id}/scan', static function (string $id): void {
    (new DispatchController())->scan($id);
}, true, true, 'dispatch.create');
$router->post('/dispatch/{id}/complete', static function (string $id): void {
    (new DispatchController())->complete($id);
}, true, true, 'dispatch.complete');
$router->post('/dispatch/{id}/pod', static function (string $id): void {
    (new DispatchController())->pod($id);
}, true, true, 'delivery.signoff');
$router->get('/snags', static function (): void {
    (new DispatchController())->snags();
}, true, 'snags.view');
$router->post('/snags', static function (): void {
    (new DispatchController())->storeSnag();
}, true, true, 'snags.manage');
$router->post('/snags/{id}', static function (string $id): void {
    (new DispatchController())->updateSnag($id);
}, true, true, 'snags.manage');
$router->get('/jobs/{id}/trace', static function (string $id): void {
    (new WorkshopController())->trace($id);
}, true, 'jobs.view');
$router->post('/jobs/{id}/installation-signoff', static function (string $id): void {
    (new WorkshopController())->signoff($id);
}, true, true, 'installation.signoff');
$router->get('/labels/preview', static function (): void {
    (new WorkshopController())->labelPreview();
}, true, 'labels.print');
$router->get('/documents/job-cards', static function (): void {
    $_GET['type'] = 'JOB_CARD';
    (new WorkshopController())->documents();
}, true, 'documents.internal.view');
$router->get('/documents/delivery-notes', static function (): void {
    $_GET['type'] = 'DELIVERY_NOTE';
    (new WorkshopController())->documents();
}, true, 'dispatch.view');
$router->get('/documents/certificates', static function (): void {
    $_GET['type'] = 'COMPLETION_CERTIFICATE';
    (new WorkshopController())->documents();
}, true, 'jobs.view');
$router->get('/documents/generated/{id}', static function (string $id): void {
    (new WorkshopController())->download($id);
}, true, 'jobs.view');
$router->get('/reports/throughput', static function (): void {
    (new WorkshopController())->reports();
}, true, 'reports.operations');
$router->get('/reports/qc-rework', static function (): void {
    (new WorkshopController())->reports();
}, true, 'reports.operations');
$router->get('/reports/traceability', static function (): void {
    (new WorkshopController())->reports();
}, true, 'tracking.traceability.view');
$router->get('/admin/document-templates', static function (): void {
    (new WorkshopController())->templates();
}, true, 'documents.templates.manage');
$router->post('/admin/document-templates', static function (): void {
    (new WorkshopController())->saveTemplate();
}, true, true, 'documents.templates.manage');
$router->get('/admin/label-templates', static function (): void {
    (new WorkshopController())->templates();
}, true, 'documents.templates.manage');

$router->get('/planning', static function (): void {
    (new BusinessPlanningController())->overview();
}, true, 'planning.view');
$router->get('/planning/sales', static function (): void {
    (new BusinessPlanningController())->sales();
}, true, 'forecast.sales');
$router->get('/planning/backlog', static function (): void {
    (new BusinessPlanningController())->backlog();
}, true, 'planning.view');
$router->get('/planning/cash', static function (): void {
    (new BusinessPlanningController())->cash();
}, true, 'forecast.cash');
$router->get('/planning/materials', static function (): void {
    (new BusinessPlanningController())->materials();
}, true, 'forecast.materials');
$router->get('/planning/mrp', static function (): void {
    (new BusinessPlanningController())->mrp();
}, true, 'mrp.view');
$router->post('/planning/mrp/refresh', static function (): void {
    (new BusinessPlanningController())->refreshMrp();
}, true, true, 'mrp.manage');
$router->get('/planning/purchasing', static function (): void {
    (new BusinessPlanningController())->purchasing();
}, true, 'purchase_recommendations.review');
$router->post('/planning/recommendations/{id}', static function (string $id): void {
    (new BusinessPlanningController())->reviewRecommendation($id);
}, true, true, 'purchase_recommendations.review');
$router->post('/planning/recommendations/{id}/request', static function (string $id): void {
    (new BusinessPlanningController())->convertRecommendation($id);
}, true, true, 'purchase_recommendations.review');
$router->get('/planning/capacity', static function (): void {
    (new BusinessPlanningController())->capacity();
}, true, 'forecast.capacity');
$router->get('/planning/calendar', static function (): void {
    (new BusinessPlanningController())->calendar();
}, true, 'planning.view');
$router->get('/planning/scenarios', static function (): void {
    (new BusinessPlanningController())->scenarios();
}, true, 'scenarios.view');
$router->post('/planning/scenarios', static function (): void {
    (new BusinessPlanningController())->runScenario();
}, true, true, 'scenarios.manage');
$router->get('/planning/export.csv', static function (): void {
    (new BusinessPlanningController())->exportCsv();
}, true, 'exports.perform');
$router->get('/budgets', static function (): void {
    (new BusinessPlanningController())->budgets();
}, true, 'budgets.view');
$router->post('/budgets', static function (): void {
    (new BusinessPlanningController())->saveBudget();
}, true, true, 'budgets.manage');
$router->get('/budgets/targets', static function (): void {
    (new BusinessPlanningController())->targets();
}, true, 'targets.view');
$router->post('/budgets/targets', static function (): void {
    (new BusinessPlanningController())->saveTarget();
}, true, true, 'targets.manage');
$router->get('/budgets/{id}/actual', static function (string $id): void {
    (new BusinessPlanningController())->budgetActual($id);
}, true, 'budgets.view');
$router->get('/reports/forecast-accuracy', static function (): void {
    (new BusinessPlanningController())->accuracy();
}, true, 'planning.view');
$router->get('/reports/stock-coverage', static function (): void {
    (new BusinessPlanningController())->coverage();
}, true, 'forecast.materials');
$router->get('/reports/data-quality', static function (): void {
    (new BusinessPlanningController())->quality();
}, true, 'data_quality.view');
$router->get('/admin/imports', static function (): void {
    (new BusinessPlanningController())->importForm();
}, true, 'imports.perform');
$router->post('/admin/imports/preview', static function (): void {
    (new BusinessPlanningController())->importPreview();
}, true, true, 'imports.perform');
$router->post('/admin/imports/commit', static function (): void {
    (new BusinessPlanningController())->importCommit();
}, true, true, 'imports.perform');
$router->get('/admin/api-clients', static function (): void {
    (new BusinessPlanningController())->apiClients();
}, true, 'api.manage');
$router->post('/admin/api-clients', static function (): void {
    (new BusinessPlanningController())->saveApiClient();
}, true, true, 'api.manage');
$router->get('/admin/webhooks', static function (): void {
    (new BusinessPlanningController())->webhooks();
}, true, 'webhooks.manage');
$router->post('/admin/webhooks', static function (): void {
    (new BusinessPlanningController())->saveWebhook();
}, true, true, 'webhooks.manage');
$router->get('/admin/integration-logs', static function (): void {
    (new BusinessPlanningController())->integrationLogs();
}, true, 'integration_logs.view');
$router->get('/api/v1/customers/{id}', static function (string $id): void {
    (new ApiV1Controller())->customer($id);
}, false);
$router->get('/api/v1/quotes/{id}', static function (string $id): void {
    (new ApiV1Controller())->quote($id);
}, false);
$router->get('/api/v1/jobs/{id}', static function (string $id): void {
    (new ApiV1Controller())->job($id);
}, false);
$router->get('/api/v1/invoices/{id}', static function (string $id): void {
    (new ApiV1Controller())->invoice($id);
}, false);
$router->get('/api/v1/stock/{id}', static function (string $id): void {
    (new ApiV1Controller())->stock($id);
}, false);
$router->post('/api/v1/leads', static function (): void {
    (new ApiV1Controller())->createLead();
}, false, false);

$router->get('/admin/integrations', static function (): void {
    (new IntegrationController())->index();
}, true, 'integrations.view');
$router->post('/admin/integrations', static function (): void {
    (new IntegrationController())->save();
}, true, true, 'integrations.manage');

$router->get('/workflows', static function (): void {
    (new PlatformController())->workflows();
}, true, 'workflows.view');
$router->get('/workflows/new', static function (): void {
    (new PlatformController())->workflowForm();
}, true, 'workflows.manage');
$router->post('/workflows', static function (): void {
    (new PlatformController())->saveWorkflow();
}, true, true, 'workflows.manage');
$router->get('/workflows/history', static function (): void {
    (new PlatformController())->history();
}, true, 'workflows.view');
$router->get('/workflows/{id}', static function (string $id): void {
    (new PlatformController())->workflowForm($id);
}, true, 'workflows.view');
$router->post('/workflows/{id}', static function (string $id): void {
    (new PlatformController())->saveWorkflow($id);
}, true, true, 'workflows.manage');
$router->post('/workflows/{id}/test', static function (string $id): void {
    (new PlatformController())->testWorkflow($id);
}, true, true, 'workflows.test');
$router->get('/approvals', static function (): void {
    (new PlatformController())->approvals();
}, true, 'approvals.view');
$router->get('/approvals/{id}', static function (string $id): void {
    (new PlatformController())->showApproval($id);
}, true, 'approvals.view');
$router->post('/approvals/{id}', static function (string $id): void {
    (new PlatformController())->decide($id);
}, true, true, 'approvals.decide');
$router->get('/reviews', static function (): void {
    (new PlatformController())->reviews();
}, true, 'review_queue.view');
$router->post('/reviews/{id}', static function (string $id): void {
    (new PlatformController())->resolveReview($id);
}, true, true, 'review_queue.resolve');
$router->get('/admin/configuration', static function (): void {
    (new PlatformController())->configuration();
}, true, 'configuration.manage');
$router->get('/admin/configuration/export', static function (): void {
    (new PlatformController())->exportConfig();
}, true, 'configuration.manage');
$router->get('/admin/approval-policies', static function (): void {
    (new PlatformController())->policies();
}, true, 'approvals.view');
$router->get('/admin/custom-fields', static function (): void {
    (new PlatformController())->fields();
}, true, 'custom_fields.manage');
$router->post('/admin/custom-fields', static function (): void {
    (new PlatformController())->saveField();
}, true, true, 'custom_fields.manage');
$router->get('/admin/custom-forms', static function (): void {
    (new PlatformController())->forms();
}, true, 'custom_forms.manage');
$router->get('/admin/feature-flags', static function (): void {
    (new PlatformController())->flags();
}, true, 'feature_flags.manage');
$router->post('/admin/feature-flags', static function (): void {
    (new PlatformController())->saveFlag();
}, true, true, 'feature_flags.manage');
$router->get('/admin/ai', static function (): void {
    (new PlatformController())->ai();
}, true, 'ai.admin');
$router->get('/admin/dictionary', static function (): void {
    (new PlatformController())->dictionary();
}, true, 'configuration.manage');
$router->get('/command', static function (): void {
    (new PlatformController())->command();
}, true, 'dashboard.view');
$router->get('/integrations', static function (): void {
    (new PlatformController())->integrations();
}, true, 'integrations.view');
$router->get('/integrations/issues', static function (): void {
    (new PlatformController())->issues();
}, true, 'integration_logs.view');
$router->post('/integrations/issues/{id}', static function (string $id): void {
    (new PlatformController())->retryIssue($id);
}, true, true, 'integrations.retry');
$router->get('/finance/payment-links', static function (): void {
    (new PlatformController())->payments();
}, true, 'payment_links.manage');
$router->get('/pay/return/{token}', static function (string $token): void {
    (new PlatformController())->payReturn($token);
}, false);
$router->post('/payments/webhook/{provider}', static function (string $provider): void {
    (new PlatformController())->webhook($provider);
}, false, false);
$router->get('/my/dashboard', static function (): void {
    (new PlatformController())->dashboard();
}, true, 'dashboard.view');
$router->get('/reports/workflow-performance', static function (): void {
    (new PlatformController())->performance();
}, true, 'workflows.view');

$router->get('/m', static function (): void {
    (new MobileController())->home();
}, true, 'mobile.use');
$router->get('/m/sync', static function (): void {
    (new MobileController())->syncCentre();
}, true, 'offline.use');
$router->get('/m/more', static function (): void {
    (new MobileController())->more();
}, true, 'mobile.use');
$router->get('/m/scan', static function (): void {
    (new MobileController())->scan();
}, true, 'mobile.use');
$router->get('/m/workshop', static function (): void {
    (new MobileController())->workshop();
}, true, 'workshop.tablet');
$router->get('/m/surveys/{id}', static function (string $id): void {
    (new MobileController())->survey($id);
}, true, 'site_survey.mobile');
$router->get('/m/installations/{id}', static function (string $id): void {
    (new MobileController())->installation($id);
}, true, 'installation.mobile');
$router->get('/m/deliveries/{id}', static function (string $id): void {
    (new MobileController())->delivery($id);
}, true, 'delivery.mobile');
$router->get('/m/jobs/{id}', static function (string $id): void {
    (new MobileController())->job($id);
}, true, 'jobs.view');
$router->get('/m/customers/{id}', static function (string $id): void {
    (new MobileController())->customer($id);
}, true, 'customers.view');
$router->get('/m/quotes/{id}', static function (string $id): void {
    (new MobileController())->quote($id);
}, true, 'quotes.view');
$router->get('/m/leads/{id}', static function (string $id): void {
    (new MobileController())->lead($id);
}, true, 'leads.view');
$router->get('/m/devices', static function (): void {
    (new MobileController())->devices();
}, true, 'device.view_own');
$router->post('/m/devices', static function (): void {
    (new MobileController())->saveDevice();
}, true, true, 'device.manage_own');
$router->post('/m/devices/{id}/revoke', static function (string $id): void {
    (new MobileController())->revokeDevice($id);
}, true, true, 'device.manage_own');
$router->get('/m/conflicts', static function (): void {
    (new MobileController())->conflicts();
}, true, 'sync_conflicts.view');
$router->post('/m/conflicts/{id}', static function (string $id): void {
    (new MobileController())->resolveConflict($id);
}, true, true, 'sync_conflicts.resolve');
$router->get('/m/photos/{id}', static function (string $id): void {
    (new MobileController())->photo($id);
}, true, 'mobile.use');
$router->get('/admin/devices', static function (): void {
    (new MobileController())->devices();
}, true, 'device.manage_all');
$router->get('/admin/sync-status', static function (): void {
    (new MobileController())->syncCentre();
}, true, 'device.manage_all');
$router->get('/admin/sync-conflicts', static function (): void {
    (new MobileController())->conflicts();
}, true, 'sync_conflicts.view');
$router->get('/admin/push', static function (): void {
    (new MobileController())->settings();
}, true, 'device.manage_all');
$router->get('/admin/offline-settings', static function (): void {
    (new MobileController())->settings();
}, true, 'configuration.manage');
$router->post('/admin/offline-settings', static function (): void {
    (new MobileController())->saveSettings();
}, true, true, 'configuration.manage');
$router->get('/admin/field-packs', static function (): void {
    (new MobileController())->syncCentre();
}, true, 'device.manage_all');
$router->get('/api/v1/mobile/config', static function (): void {
    (new MobileController())->config();
}, true, 'mobile.use');
$router->post('/api/v1/devices', static function (): void {
    (new MobileController())->registerDevice();
}, true, true, 'mobile.use');
$router->post('/api/v1/sync', static function (): void {
    (new MobileController())->sync();
}, true, true, 'offline.use');
$router->post('/api/v1/field-packs', static function (): void {
    (new MobileController())->downloadPack();
}, true, true, 'field_pack.download');
$router->post('/api/v1/push-subscriptions', static function (): void {
    (new MobileController())->subscribe();
}, true, true, 'push_notifications.use');
$router->post('/api/v1/mobile/report', static function (): void {
    (new MobileController())->report();
}, true, true, 'mobile.use');

$router->get('/projects', static function (): void {
    (new ProjectController())->index();
}, true, 'projects.view_assigned');
$router->get('/projects/new', static function (): void {
    (new ProjectController())->createForm();
}, true, 'projects.create');
$router->post('/projects', static function (): void {
    (new ProjectController())->store();
}, true, true, 'projects.create');
$router->get('/projects/reports/{slug}', static function (string $slug): void {
    (new ProjectController())->report($slug);
}, true, 'projects.view_assigned');
$router->get('/projects/{id}', static function (string $id): void {
    (new ProjectController())->show($id);
}, true, 'projects.view_assigned');
$router->post('/projects/{id}', static function (string $id): void {
    (new ProjectController())->update($id);
}, true, true, 'projects.edit');
$router->post('/projects/{id}/date', static function (string $id): void {
    (new ProjectController())->shiftDate($id);
}, true, true, 'projects.edit');
$router->post('/projects/{id}/sites', static function (string $id): void {
    (new ProjectController())->addSite($id);
}, true, true, 'projects.manage_sites');
$router->get('/projects/{id}/import', static function (string $id): void {
    (new ProjectController())->importForm($id);
}, true, 'projects.import_sites');
$router->post('/projects/{id}/import', static function (string $id): void {
    (new ProjectController())->import($id);
}, true, true, 'projects.import_sites');
$router->post('/projects/{id}/jobs', static function (string $id): void {
    (new ProjectController())->bulkJobs($id);
}, true, true, 'projects.bulk_create_jobs');
$router->post('/projects/{id}/waves', static function (string $id): void {
    (new ProjectController())->addWave($id);
}, true, true, 'projects.manage_sites');
$router->post('/projects/{id}/milestones', static function (string $id): void {
    (new ProjectController())->addMilestone($id);
}, true, true, 'projects.manage_milestones');
$router->post('/projects/{id}/milestones/{milestoneId}', static function (string $id, string $milestoneId): void {
    (new ProjectController())->milestoneStatus($id, $milestoneId);
}, true, true, 'projects.manage_milestones');
$router->post('/projects/{id}/risks', static function (string $id): void {
    (new ProjectController())->addRisk($id);
}, true, true, 'projects.manage_risks');
$router->post('/projects/{id}/issues', static function (string $id): void {
    (new ProjectController())->addIssue($id);
}, true, true, 'projects.manage_issues');
$router->post('/projects/{id}/issues/{issueId}', static function (string $id, string $issueId): void {
    (new ProjectController())->issueStatus($id, $issueId);
}, true, true, 'projects.manage_issues');
$router->post('/projects/{id}/notes', static function (string $id): void {
    (new ProjectController())->addNote($id);
}, true, true, 'projects.edit');
$router->post('/projects/{id}/changes', static function (string $id): void {
    (new ProjectController())->addChange($id);
}, true, true, 'projects.manage_changes');
$router->post('/projects/{id}/changes/{changeId}', static function (string $id, string $changeId): void {
    (new ProjectController())->decideChange($id, $changeId);
}, true, true, 'projects.manage_changes');
$router->post('/projects/{id}/budget', static function (string $id): void {
    (new ProjectController())->saveBudget($id);
}, true, true, 'projects.manage_budget');
$router->post('/projects/{id}/costs', static function (string $id): void {
    (new ProjectController())->addCost($id);
}, true, true, 'projects.manage_budget');
$router->post('/projects/{id}/complete', static function (string $id): void {
    (new ProjectController())->complete($id);
}, true, true, 'projects.complete');
$router->get('/projects/{id}/handover', static function (string $id): void {
    (new ProjectController())->handover($id);
}, true, 'projects.generate_handover');
$router->post('/projects/{id}/documents', static function (string $id): void {
    (new ProjectController())->upload($id);
}, true, true, 'projects.edit');
$router->get('/project-sites/{id}', static function (string $id): void {
    (new ProjectController())->site($id);
}, true, 'projects.view_assigned');
$router->get('/m/projects', static function (): void {
    (new ProjectController())->mobileIndex();
}, true, 'projects.view_assigned');
$router->get('/m/projects/{id}', static function (string $id): void {
    (new ProjectController())->mobileProject($id);
}, true, 'projects.view_assigned');
$router->get('/api/v1/projects/{id}', static function (string $id): void {
    (new ApiV1Controller())->project($id);
}, false);
$router->get('/api/v1/projects/{id}/sites', static function (string $id): void {
    (new ApiV1Controller())->projectSites($id);
}, false);
$router->get('/api/v1/projects/{id}/milestones', static function (string $id): void {
    (new ApiV1Controller())->projectMilestones($id);
}, false);
$router->get('/api/v1/projects/{id}/jobs', static function (string $id): void {
    (new ApiV1Controller())->projectJobs($id);
}, false);
$router->get('/api/v1/projects/{id}/summary', static function (string $id): void {
    (new ApiV1Controller())->projectSummary($id);
}, false);
$router->get('/api/v1/project-sites/{id}', static function (string $id): void {
    (new ApiV1Controller())->projectSite($id);
}, false);

$router->get('/assets', static function (): void {
    (new AssetController())->index();
}, true, 'assets.view');
$router->get('/assets/new', static function (): void {
    (new AssetController())->createForm();
}, true, 'assets.create');
$router->post('/assets', static function (): void {
    (new AssetController())->store();
}, true, true, 'assets.create');
$router->get('/assets/import', static function (): void {
    (new AssetController())->importForm();
}, true, 'assets.import');
$router->post('/assets/import', static function (): void {
    (new AssetController())->import();
}, true, true, 'assets.import');
$router->get('/assets/scan', static function (): void {
    (new AssetController())->scan();
}, true, 'assets.view');
$router->get('/assets/{id}', static function (string $id): void {
    (new AssetController())->show($id);
}, true, 'assets.view');
$router->post('/assets/{id}', static function (string $id): void {
    (new AssetController())->update($id);
}, true, true, 'assets.edit');
$router->post('/assets/{id}/components', static function (string $id): void {
    (new AssetController())->addComponent($id);
}, true, true, 'assets.manage_components');
$router->post('/assets/{id}/components/replace', static function (string $id): void {
    (new AssetController())->replaceComponent($id);
}, true, true, 'assets.manage_components');
$router->post('/assets/{id}/warranties', static function (string $id): void {
    (new AssetController())->addWarranty($id);
}, true, true, 'assets.manage_warranties');
$router->post('/assets/{id}/claims', static function (string $id): void {
    (new AssetController())->addClaim($id);
}, true, true, 'warranty_claims.manage');
$router->get('/assets/{id}/label', static function (string $id): void {
    (new AssetController())->label($id);
}, true, 'assets.generate_labels');
$router->post('/assets/{id}/transfer', static function (string $id): void {
    (new AssetController())->transfer($id);
}, true, true, 'assets.edit');
$router->post('/assets/{id}/remove', static function (string $id): void {
    (new AssetController())->remove($id);
}, true, true, 'assets.edit');
$router->get('/service', static function (): void {
    (new AssetController())->serviceIndex();
}, true, 'service_requests.view');
$router->post('/service', static function (): void {
    (new AssetController())->serviceCreate();
}, true, true, 'service_requests.create');
$router->get('/service/maintenance', static function (): void {
    (new AssetController())->maintenance();
}, true, 'service_requests.view');
$router->post('/service/maintenance', static function (): void {
    (new AssetController())->savePlan();
}, true, true, 'maintenance.manage');
$router->get('/service/warranties', static function (): void {
    (new AssetController())->warranties();
}, true, 'warranty_claims.view');
$router->get('/service/reports/{slug}', static function (string $slug): void {
    (new AssetController())->report($slug);
}, true, 'service_requests.view');
$router->get('/service/asset/{token}', static function (string $token): void {
    (new AssetController())->publicShow($token);
}, false);
$router->post('/service/asset/{token}', static function (string $token): void {
    (new AssetController())->publicReport($token);
}, false, true);
$router->get('/service/{id}', static function (string $id): void {
    (new AssetController())->serviceShow($id);
}, true, 'service_requests.view');
$router->post('/service/{id}', static function (string $id): void {
    (new AssetController())->serviceUpdate($id);
}, true, true, 'service_requests.manage');
$router->post('/service/{id}/job', static function (string $id): void {
    (new AssetController())->serviceJob($id);
}, true, true, 'service_jobs.manage');
$router->post('/service/{id}/sign', static function (string $id): void {
    (new AssetController())->serviceSign($id);
}, true, true, 'inspections.perform');
$router->get('/service/{id}/report', static function (string $id): void {
    (new AssetController())->serviceReport($id);
}, true, 'service_requests.view');
$router->get('/m/service', static function (): void {
    (new AssetController())->mobile();
}, true, 'service_requests.view');
$router->get('/m/service/{id}', static function (string $id): void {
    (new AssetController())->mobileAsset($id);
}, true, 'assets.view');
$router->get('/api/v1/assets', static function (): void {
    (new ApiV1Controller())->assets();
}, false);
$router->get('/api/v1/assets/{id}', static function (string $id): void {
    (new ApiV1Controller())->assetRecord($id);
}, false);
$router->get('/api/v1/assets/{id}/components', static function (string $id): void {
    (new ApiV1Controller())->assetComponents($id);
}, false);
$router->get('/api/v1/assets/{id}/warranties', static function (string $id): void {
    (new ApiV1Controller())->assetWarranties($id);
}, false);
$router->get('/api/v1/assets/{id}/service-history', static function (string $id): void {
    (new ApiV1Controller())->assetHistory($id);
}, false);
$router->get('/api/v1/service-requests', static function (): void {
    (new ApiV1Controller())->serviceRequests();
}, false);
$router->get('/api/v1/service-requests/{id}', static function (string $id): void {
    (new ApiV1Controller())->serviceRequest($id);
}, false);
$router->get('/api/v1/warranty-claims', static function (): void {
    (new ApiV1Controller())->warrantyClaims();
}, false);
$router->get('/api/v1/maintenance/due', static function (): void {
    (new ApiV1Controller())->maintenanceDue();
}, false);
$router->get('/api/v1/specifications', static function (): void {
    (new ApiV1Controller())->specifications();
}, false);
$router->get('/api/v1/vehicle-templates', static function (): void {
    (new ApiV1Controller())->vehicleTemplates();
}, false);
$router->post('/api/v1/estimators/vehicle-wrap', static function (): void {
    (new ApiV1Controller())->estimateSign('VEHICLE_WRAP');
}, false, false);
$router->post('/api/v1/estimators/channel-letter', static function (): void {
    (new ApiV1Controller())->estimateSign('CHANNEL_LETTER');
}, false, false);
$router->post('/api/v1/estimators/lightbox', static function (): void {
    (new ApiV1Controller())->estimateSign('LIGHTBOX');
}, false, false);
$router->post('/api/v1/estimators/pylon', static function (): void {
    (new ApiV1Controller())->estimateSign('PYLON');
}, false, false);
$router->post('/api/v1/estimators/panel-frame', static function (): void {
    (new ApiV1Controller())->estimateSign('PANEL_FRAME');
}, false, false);

$soon = static function (string $slug): void {
    (new PageController())->upcoming($slug);
};
