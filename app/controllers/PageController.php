<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;

/**
 * Placeholder screens for modules that are in the navigation but not built yet.
 *
 * The shell is complete. Catalogue, calculator, customers, and quotes are the
 * following build steps. Keeping a real page (instead of a dead link) means
 * the menu can be checked on a phone before those modules exist.
 */
final class PageController
{
    /** @var array<string, array{title: string, nav: string, icon: string, summary: string}> */
    private const MODULES = [
        'calculator' => [
            'title' => 'Calculator',
            'nav' => 'calculator',
            'icon' => 'fa-calculator',
            'summary' => 'Choose a category and a product, enter the size, and see actual area, consumed material, manufacturing waste, and the Q1 to Q4 selling prices. The server will recalculate every total when a line is saved.',
        ],
        'quotes' => [
            'title' => 'Quotes',
            'nav' => 'quotes',
            'icon' => 'fa-file-invoice',
            'summary' => 'Draft, send, and print quotations. Each line keeps the cost and markup from the day it was priced, so a later supplier increase does not rewrite an old quote.',
        ],
        'customers' => [
            'title' => 'Customers',
            'nav' => 'customers',
            'icon' => 'fa-users',
            'summary' => 'Keep the company, contact, VAT number, and address used on quotations, and open that customer\'s quote history from the same record.',
        ],
        'products' => [
            'title' => 'Products',
            'nav' => 'products',
            'icon' => 'fa-box',
            'summary' => 'Add and edit materials, set the pricing method, and deactivate anything that already appears on a quote. Products are not deleted once they have history.',
        ],
        'categories' => [
            'title' => 'Categories',
            'nav' => 'categories',
            'icon' => 'fa-tags',
            'summary' => 'Group the catalogue into families such as printable vinyl, boards, LED, and labour. The calculator will ask for a category before a product.',
        ],
        'pricing' => [
            'title' => 'Pricing levels',
            'nav' => 'pricing',
            'icon' => 'fa-layer-group',
            'summary' => 'Q1 to Q4 are starter names. Markup percentages live in the database and can later be renamed to Retail, Trade, or Wholesale without a code change.',
        ],
        'settings' => [
            'title' => 'Settings',
            'nav' => 'settings',
            'icon' => 'fa-gear',
            'summary' => 'Company name, VAT number, quote prefix, VAT rate, currency, and how long a quote stays valid. Those values are already in the database and will be editable here.',
        ],
    ];

    public function upcoming(string $slug): void
    {
        $module = self::MODULES[$slug] ?? null;
        if ($module === null) {
            http_response_code(404);
            View::render('errors/404', [
                'title' => 'Page not found',
                'activeNav' => '',
                'message' => 'That page is not part of Sign-Forge.',
            ]);

            return;
        }

        View::render('pages/upcoming', [
            'title' => $module['title'],
            'activeNav' => $module['nav'],
            'module' => $module,
        ]);
    }
}
