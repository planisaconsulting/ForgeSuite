<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Models\Dashboard;
use App\Models\Setting;

/**
 * Home screen after sign-in. Counts come from the database.
 * This controller does not calculate prices.
 */
final class DashboardController
{
    public function index(): void
    {
        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
            'summary' => Dashboard::summary(),
            'desk' => [
                'vat' => Setting::get('default_vat_percent', '15'),
                'prefix' => Setting::get('quote_prefix', 'SFQ'),
                'validity' => Setting::get('default_quote_validity_days', '14'),
                'currency' => Setting::get('currency_code', 'ZAR'),
                'year' => date('Y'),
            ],
        ]);
    }
}
