<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\AuditRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SupplierRepository;
use App\Services\ProductService;

final class ProductController
{
    public function index(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $status = list_status();
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        View::render('products/index', [
            'title' => 'Products',
            'activeNav' => 'products',
            'rows' => (new ProductRepository())->search($term, $status, $categoryId > 0 ? $categoryId : null),
            'categories' => (new CategoryRepository())->allWithParent(),
            'term' => $term,
            'status' => $status,
            'categoryId' => $categoryId,
            'canManage' => can('products.manage'),
        ]);
    }

    public function create(): void
    {
        $this->form(null, [], ['active' => '1', 'product_type' => 'MATERIAL', 'pricing_method' => 'AREA', 'default_waste_policy' => 'ACTUAL', 'standard_waste_percent' => '0']);
    }

    public function store(): void
    {
        $result = (new ProductService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->form(null, $result['errors'], $_POST);

            return;
        }
        flash('success', 'Product created.');
        redirect('/products/' . $result['id']);
    }

    public function show(string $id): void
    {
        $productId = route_id($id);
        $product = (new ProductRepository())->find($productId);
        if ($product === null) {
            abort_not_found('That product was not found.');
        }
        View::render('products/show', [
            'title' => (string) $product['name'],
            'activeNav' => 'products',
            'product' => $product,
            'history' => (new ProductRepository())->priceHistory($productId),
            'audit' => (new AuditRepository())->forEntity('product', $productId),
            'canManage' => can('products.manage'),
        ]);
    }

    public function edit(string $id): void
    {
        $product = $this->requireProduct($id);
        $this->form($product, [], $product);
    }

    public function update(string $id): void
    {
        $productId = route_id($id);
        $errors = (new ProductService())->update($productId, $_POST, (int) auth_user()['id']);
        if ($errors !== []) {
            $this->form((new ProductRepository())->find($productId), $errors, $_POST);

            return;
        }
        flash('success', 'Product updated.');
        redirect('/products/' . $productId);
    }

    public function deactivate(string $id): void
    {
        $productId = route_id($id);
        $active = posted_flag($_POST, 'active', 0) === 1;
        if (!(new ProductService())->setActive($productId, $active)) {
            abort_not_found('That product was not found.');
        }
        flash('success', $active ? 'Product activated.' : 'Product deactivated. The record is kept.');
        redirect('/products/' . $productId);
    }

    /**
     * @param array<string, mixed>|null $product
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function form(?array $product, array $errors, array $old): void
    {
        View::render('products/form', [
            'title' => $product === null || !isset($product['id']) ? 'New product' : 'Edit product',
            'activeNav' => 'products',
            'product' => isset($product['id']) ? $product : null,
            'errors' => $errors,
            'old' => $old,
            'categories' => (new CategoryRepository())->allWithParent(),
            'suppliers' => (new SupplierRepository())->options(),
            'scripts' => ['assets/js/product-form.js'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireProduct(string $id): array
    {
        $product = (new ProductRepository())->find(route_id($id));
        if ($product === null) {
            abort_not_found('That product was not found.');
        }

        return $product;
    }
}
