<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CategoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\PricingLevelRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Services\PricingService;
use App\Services\QuoteService;

/**
 * The calculator shows a live price, but every figure comes back from
 * PricingService. The browser does not own the cost, the markup, or the waste.
 */
final class CalculatorController
{
    public function index(): void
    {
        View::render('calculator/index', [
            'title' => 'Pricing calculator',
            'activeNav' => 'calculator',
            'categories' => (new CategoryRepository())->allWithParent(),
            'products' => (new ProductRepository())->calculatorCatalogue(),
            'customers' => can('quotes.manage') ? (new CustomerRepository())->search('', 'active', 300) : [],
            'drafts' => can('quotes.manage') ? (new QuoteRepository())->recentDrafts() : [],
            'canQuote' => can('quotes.manage'),
            'scripts' => ['assets/js/calculator.js'],
        ]);
    }

    public function addToQuote(): void
    {
        $userId = (int) auth_user()['id'];
        $service = new QuoteService();
        $quotes = new QuoteRepository();
        $quoteId = (int) ($_POST['quote_id'] ?? 0);
        if ($quoteId > 0) {
            $existing = $quotes->find($quoteId);
            if ($existing === null || (string) $existing['status'] !== 'DRAFT') {
                flash('error', 'Choose a draft quotation.');
                redirect('/calculator');
            }
            $version = (int) $existing['version_number'];
        } else {
            $created = $service->create($_POST, $userId);
            if ($created['id'] === null) {
                flash('error', (string) reset($created['errors']));
                redirect('/calculator');
            }
            $quoteId = (int) $created['id'];
            $version = 1;
        }
        $result = $service->addProductLine($quoteId, $_POST, $version, $userId);
        if ($result['errors'] !== []) {
            flash('error', implode(' ', $result['errors']));
        } else {
            flash('success', 'The line was priced on the server and added to the quotation.');
        }
        redirect('/quotes/' . $quoteId . '/edit');
    }

    public function price(): void
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $product = (new ProductRepository())->find($productId);
        if ($product === null || (int) $product['active'] !== 1) {
            json_response(['ok' => false, 'errors' => ['Choose an active product.']], 422);
        }

        $result = (new PricingService())->price(
            $product,
            $_POST,
            (new PricingLevelRepository())->active()
        );
        $result['product'] = [
            'id' => (int) $product['id'],
            'sku' => (string) $product['sku'],
            'name' => (string) $product['name'],
            'pricing_method' => (string) $product['pricing_method'],
        ];
        $result['total_cost_display'] = money((string) $result['total_cost']);
        $result['raw_cost_display'] = money((string) $result['raw_cost']);
        $result['unit_cost_display'] = money((string) $result['unit_cost']);
        foreach ($result['levels'] as $index => $level) {
            $result['levels'][$index]['selling_price_display'] = money((string) $level['selling_price']);
            $result['levels'][$index]['gross_profit_display'] = money((string) $level['gross_profit']);
        }

        json_response($result, $result['ok'] ? 200 : 422);
    }
}
