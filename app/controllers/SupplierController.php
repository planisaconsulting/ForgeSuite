<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\AuditRepository;
use App\Repositories\SupplierRepository;
use App\Services\SupplierService;

final class SupplierController
{
    public function index(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $status = list_status();
        View::render('suppliers/index', [
            'title' => 'Suppliers',
            'activeNav' => 'suppliers',
            'rows' => (new SupplierRepository())->search($term, $status),
            'term' => $term,
            'status' => $status,
            'canManage' => can('suppliers.manage'),
        ]);
    }

    public function create(): void
    {
        $this->form(null, [], ['active' => '1']);
    }

    public function store(): void
    {
        $result = (new SupplierService())->save(null, $_POST);
        if ($result['errors'] !== []) {
            $this->form(null, $result['errors'], $_POST);

            return;
        }
        flash('success', 'Supplier created.');
        redirect('/suppliers/' . $result['id']);
    }

    public function show(string $id): void
    {
        $supplierId = route_id($id);
        $supplier = (new SupplierRepository())->find($supplierId);
        if ($supplier === null) {
            abort_not_found('That supplier was not found.');
        }
        View::render('suppliers/show', [
            'title' => (string) $supplier['name'],
            'activeNav' => 'suppliers',
            'supplier' => $supplier,
            'audit' => (new AuditRepository())->forEntity('supplier', $supplierId),
            'canManage' => can('suppliers.manage'),
            'orders' => can('purchasing.view') ? (new \App\Repositories\PurchasingRepository())->ordersForSupplier($supplierId) : [],
            'prices' => can('supplier_prices.view') ? (new \App\Repositories\InventoryRepository())->supplierProductsForSupplier($supplierId) : [],
        ]);
    }

    public function edit(string $id): void
    {
        $supplier = $this->requireSupplier($id);
        $this->form($supplier, [], $supplier);
    }

    public function update(string $id): void
    {
        $supplierId = route_id($id);
        $result = (new SupplierService())->save($supplierId, $_POST);
        if ($result['errors'] !== []) {
            $this->form((new SupplierRepository())->find($supplierId), $result['errors'], $_POST);

            return;
        }
        flash('success', 'Supplier updated.');
        redirect('/suppliers/' . $supplierId);
    }

    public function deactivate(string $id): void
    {
        $supplierId = route_id($id);
        $active = posted_flag($_POST, 'active', 0) === 1;
        if (!(new SupplierService())->setActive($supplierId, $active)) {
            abort_not_found('That supplier was not found.');
        }
        flash('success', $active ? 'Supplier activated.' : 'Supplier deactivated. The record is kept.');
        redirect('/suppliers/' . $supplierId);
    }

    /**
     * @param array<string, mixed>|null $supplier
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function form(?array $supplier, array $errors, array $old): void
    {
        View::render('suppliers/form', [
            'title' => $supplier === null || !isset($supplier['id']) ? 'New supplier' : 'Edit supplier',
            'activeNav' => 'suppliers',
            'supplier' => isset($supplier['id']) ? $supplier : null,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireSupplier(string $id): array
    {
        $supplier = (new SupplierRepository())->find(route_id($id));
        if ($supplier === null) {
            abort_not_found('That supplier was not found.');
        }

        return $supplier;
    }
}
