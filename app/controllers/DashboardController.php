<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ActivityRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;
use App\Services\SettingsService;
use App\Services\StockValuation;

/**
 * Phase 1 home screen. Counts come from tables that exist.
 * Open quotes, jobs, invoices, and low stock are named as later work
 * and are not given invented numbers.
 */
final class DashboardController
{
    public function index(): void
    {
        $counts = new DashboardRepository();
        $month = date('Y-m-01');
        $sales = can('quotes.view') ? (new QuoteRepository())->desk($month) : null;
        $pipeline = can('opportunities.view') ? (new OpportunityRepository())->openSummary() : null;
        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
            'counts' => [
                'customers' => $counts->count('customers'),
                'products' => $counts->count('products'),
                'suppliers' => $counts->count('suppliers'),
                'levels' => $counts->count('pricing_levels'),
            ],
            'recentCustomers' => can('customers.view') ? (new CustomerRepository())->recent(5) : [],
            'recentActivities' => can('activities.view') ? (new ActivityRepository())->recent(5) : [],
            'priceChanges' => can('products.view') ? (new ProductRepository())->recentPriceChanges(5) : [],
            'sales' => $sales,
            'pipeline' => $pipeline,
            'operations' => can('jobs.view') ? (new JobRepository())->operationsDesk() : null,
            'productionActivity' => can('jobs.view') ? (new OperationsRepository())->recentProduction() : [],
            'lowStock' => can('inventory.view') ? $this->lowStockCount() : null,
            'financeDesk' => can('invoices.view') ? (new \App\Repositories\FinanceRepository())->desk() : null,
            'desk' => [
                'vat' => SettingsService::get('default_vat_percent', '15'),
                'currency' => SettingsService::get('currency_code', 'ZAR'),
                'symbol' => SettingsService::get('currency_symbol', 'R'),
                'timezone' => SettingsService::get('timezone', 'Africa/Johannesburg'),
            ],
        ]);
    }

    private function lowStockCount(): int
    {
        $inventory = new InventoryRepository();
        $onHand = [];
        foreach ($inventory->balances(500) as $row) {
            $id = (int) $row['product_id'];
            $onHand[$id] = Decimal::add($onHand[$id] ?? '0', (string) $row['on_hand'], 4);
        }
        $count = 0;
        foreach ($inventory->trackedProducts() as $product) {
            if ($product['minimum_stock_level'] === null) {
                continue;
            }
            $available = StockValuation::available($onHand[(int) $product['id']] ?? '0', $inventory->reserved((int) $product['id']));
            if (Decimal::cmp($available, (string) $product['minimum_stock_level']) <= 0) {
                $count++;
            }
        }

        return $count;
    }
}
