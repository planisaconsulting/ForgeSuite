<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\SystemRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SupplierRepository;

/**
 * Search covers customers, opportunities, quotations, products, and suppliers.
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
            'opportunities' => $term !== '' && can('opportunities.view')
                ? (new OpportunityRepository())->search($term, 'all')
                : [],
            'quotes' => $term !== '' && can('quotes.view')
                ? (new QuoteRepository())->search(['q' => $term])
                : [],
            'products' => $term !== '' && can('products.view')
                ? (new ProductRepository())->search($term, 'all', null, 20)
                : [],
            'suppliers' => $term !== '' && can('suppliers.view')
                ? (new SupplierRepository())->search($term, 'all', 20)
                : [],
            'showCustomers' => can('customers.view'),
            'showOpportunities' => can('opportunities.view'),
            'showQuotes' => can('quotes.view'),
            'showProducts' => can('products.view'),
            'showSuppliers' => can('suppliers.view'),
            'inventory' => $term !== '' && can('inventory.view')
                ? (new InventoryRepository())->searchItems(['q' => $term], 20)
                : [],
            'showInventory' => can('inventory.view'),
            'finance' => $term !== '' && (can('invoices.view') || can('payments.view') || can('credit_notes.view'))
                ? (new \App\Repositories\FinanceRepository())->searchDocuments($term)
                : [],
            'showFinance' => can('invoices.view') || can('payments.view') || can('credit_notes.view'),
            'jobs' => $term !== '' && can('jobs.view') ? (new SystemRepository())->searchJobs($term) : [],
            'contacts' => $term !== '' && can('customers.view') ? (new SystemRepository())->searchContacts($term) : [],
            'orders' => $term !== '' && can('purchasing.view') ? (new SystemRepository())->searchOrders($term) : [],
            'documents' => $term !== '' ? (new SystemRepository())->documents(['q' => $term, 'entity_type' => '', 'from' => '', 'to' => '']) : [],
            'showJobs' => can('jobs.view'),
            'showContacts' => can('customers.view'),
            'showOrders' => can('purchasing.view'),
            'showDocuments' => can('customers.view') || can('quotes.view') || can('jobs.view'),
        ]);
    }
}
