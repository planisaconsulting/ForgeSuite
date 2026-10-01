<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ActivityRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\ProductRepository;
use App\Services\SettingsService;

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
            'desk' => [
                'vat' => SettingsService::get('default_vat_percent', '15'),
                'currency' => SettingsService::get('currency_code', 'ZAR'),
                'symbol' => SettingsService::get('currency_symbol', 'R'),
                'timezone' => SettingsService::get('timezone', 'Africa/Johannesburg'),
            ],
        ]);
    }
}
