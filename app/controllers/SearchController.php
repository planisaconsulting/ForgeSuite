<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SupplierRepository;

/**
 * Phase 1 search covers customers, products, and suppliers.
 * Quotes, jobs, and invoices will be added here when those modules exist.
 * Each block is omitted when the signed-in role cannot view it.
 */
final class SearchController
{
    public function index(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        View::render('search/index', [
            'title' => 'Search',
            'activeNav' => '',
            'term' => $term,
            'customers' => $term !== '' && can('customers.view')
                ? (new CustomerRepository())->search($term, 'all', 20)
                : [],
            'products' => $term !== '' && can('products.view')
                ? (new ProductRepository())->search($term, 'all', null, 20)
                : [],
            'suppliers' => $term !== '' && can('suppliers.view')
                ? (new SupplierRepository())->search($term, 'all', 20)
                : [],
            'showCustomers' => can('customers.view'),
            'showProducts' => can('products.view'),
            'showSuppliers' => can('suppliers.view'),
        ]);
    }
}
