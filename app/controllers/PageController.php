<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;

/**
 * Later modules stay on the menu as disabled items. Opening the address
 * directly shows this note instead of an error. No data is invented.
 */
final class PageController
{
    /** @var array<string, array{title: string, nav: string, icon: string, summary: string}> */
    private const MODULES = [
        'quotes' => [
            'title' => 'Quotes',
            'nav' => 'quotes',
            'icon' => 'fa-file-invoice',
            'summary' => 'Quotations are the next phase. Nothing is stored for them yet, so this screen does not show a count or a list.',
        ],
        'jobs' => [
            'title' => 'Jobs',
            'nav' => 'jobs',
            'icon' => 'fa-clipboard-list',
            'summary' => 'Jobs will start from an accepted quote. They are not part of this phase.',
        ],
        'production' => [
            'title' => 'Production',
            'nav' => 'production',
            'icon' => 'fa-industry',
            'summary' => 'Production tracking is not part of this phase.',
        ],
        'stock' => [
            'title' => 'Stock',
            'nav' => 'stock',
            'icon' => 'fa-warehouse',
            'summary' => 'Stock will be a movement ledger later. Products can be flagged for tracking, but there is no quantity on hand yet.',
        ],
        'invoices' => [
            'title' => 'Invoices',
            'nav' => 'invoices',
            'icon' => 'fa-receipt',
            'summary' => 'Invoices and payments are not part of this phase.',
        ],
    ];

    public function upcoming(string $slug): void
    {
        $module = self::MODULES[$slug] ?? null;
        if ($module === null) {
            abort_not_found('That page is not part of Sign-Forge.');
        }

        View::render('pages/upcoming', [
            'title' => $module['title'],
            'activeNav' => $module['nav'],
            'module' => $module,
        ]);
    }
}
