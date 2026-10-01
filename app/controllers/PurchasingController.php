<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\PurchaseOrderStatus;
use App\Helpers\View;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchasingRepository;
use App\Repositories\SupplierRepository;
use App\Services\PurchasingService;
use App\Services\QuotePdf;
use App\Services\SettingsService;

final class PurchasingController
{
    public function index(): void
    {
        $repo = new PurchasingRepository();
        View::render('purchasing/index', [
            'title' => 'Purchasing',
            'activeNav' => 'purchasing',
            'desk' => $repo->desk(),
            'suppliers' => can('purchasing.view') ? $repo->topSuppliers() : [],
            'prices' => can('supplier_prices.view') ? (new InventoryRepository())->recentPriceChanges() : [],
            'receipts' => $repo->recentReceipts(),
            'showCost' => can('inventory.view_cost') || can('purchasing.view'),
        ]);
    }

    public function orders(): void
    {
        View::render('purchasing/orders', [
            'title' => 'Purchase orders',
            'activeNav' => 'orders',
            'rows' => (new PurchasingRepository())->orders([
                'status' => trim((string) ($_GET['status'] ?? '')),
                'q' => trim((string) ($_GET['q'] ?? '')),
                'supplier_id' => (int) ($_GET['supplier_id'] ?? 0),
            ]),
            'suppliers' => (new SupplierRepository())->options(),
            'statuses' => PurchaseOrderStatus::cases(),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'term' => trim((string) ($_GET['q'] ?? '')),
        ]);
    }

    public function create(): void
    {
        $result = (new PurchasingService())->createOrder($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) ($result['errors']['_form'] ?? 'The purchase order was not created.'));
            redirect('/purchasing/orders');
        }
        flash('success', 'Draft purchase order created.');
        redirect('/purchasing/orders/' . $result['id']);
    }

    public function show(string $id): void
    {
        $order = (new PurchasingRepository())->order(route_id($id));
        if ($order === null) {
            abort_not_found('That purchase order was not found.');
        }
        $repo = new PurchasingRepository();
        View::render('purchasing/order', [
            'title' => (string) $order['po_number'],
            'activeNav' => 'orders',
            'order' => $order,
            'items' => $repo->items((int) $order['id']),
            'receipts' => $repo->receiptsForOrder((int) $order['id']),
            'products' => (new ProductRepository())->search('', 'active', null, 300),
            'locations' => (new InventoryRepository())->locations(true),
            'statuses' => PurchaseOrderStatus::cases(),
            'showCost' => can('inventory.view_cost') || can('supplier_prices.view') || can('purchasing.view'),
        ]);
    }

    public function addLine(string $id): void
    {
        $errors = (new PurchasingService())->addLine(route_id($id), $_POST, (int) auth_user()['id']);
        $this->back($errors, '/purchasing/orders/' . route_id($id), 'Line added. The total was recalculated.');
    }

    public function status(string $id): void
    {
        $errors = (new PurchasingService())->setStatus(route_id($id), (string) ($_POST['status'] ?? ''), (int) auth_user()['id']);
        $this->back($errors, '/purchasing/orders/' . route_id($id), 'Purchase order updated.');
    }

    public function receive(string $id): void
    {
        $result = (new PurchasingService())->receive(route_id($id), $_POST, (int) auth_user()['id']);
        $this->back($result['errors'], '/purchasing/orders/' . route_id($id), 'Goods received and stock movements posted.');
    }

    public function pdf(string $id): void
    {
        $repo = new PurchasingRepository();
        $order = $repo->order(route_id($id));
        if ($order === null) {
            abort_not_found('That purchase order was not found.');
        }
        $html = View::capture('purchasing/pdf', [
            'order' => $order,
            'items' => $repo->items((int) $order['id']),
            'company' => SettingsService::get('company_name', 'Sign-Forge Signs'),
            'address' => SettingsService::get('address', ''),
            'phone' => SettingsService::get('telephone', ''),
            'email' => SettingsService::get('email', ''),
        ]);
        $pdf = (new QuotePdf())->render($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $order['po_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    public function requests(): void
    {
        View::render('purchasing/requests', [
            'title' => 'Purchase requests',
            'activeNav' => 'requests',
            'rows' => (new PurchasingRepository())->requests([
                'status' => trim((string) ($_GET['status'] ?? '')),
            ]),
            'suppliers' => (new SupplierRepository())->options(),
            'products' => (new ProductRepository())->search('', 'active', null, 300),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ]);
    }

    public function storeRequest(): void
    {
        $result = (new PurchasingService())->request($_POST, (int) auth_user()['id']);
        $back = ((int) ($_POST['job_id'] ?? 0)) > 0 ? '/jobs/' . (int) $_POST['job_id'] . '?tab=materials' : '/purchasing/requests';
        $this->back($result['errors'], $back, 'Purchase request recorded.');
    }

    public function decide(string $id): void
    {
        $errors = (new PurchasingService())->decideRequest(route_id($id), (string) ($_POST['status'] ?? ''), (int) auth_user()['id']);
        $this->back($errors, '/purchasing/requests', 'Request updated.');
    }

    public function consolidate(): void
    {
        $ids = $_POST['request_ids'] ?? [];
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $result = (new PurchasingService())->consolidate($ids, (int) ($_POST['supplier_id'] ?? 0), (int) auth_user()['id']);
        if ($result['id'] !== null && $result['errors'] === []) {
            flash('success', 'Approved requests were combined onto one purchase order.');
            redirect('/purchasing/orders/' . $result['id']);
        }
        flash('error', (string) ($result['errors']['_form'] ?? 'The purchase order was not created.'));
        redirect('/purchasing/requests');
    }

    public function supplierPrice(): void
    {
        $errors = (new PurchasingService())->saveSupplierPrice($_POST, (int) auth_user()['id']);
        $back = ((int) ($_POST['product_id'] ?? 0)) > 0 ? '/products/' . (int) $_POST['product_id'] : '/suppliers/' . (int) ($_POST['supplier_id'] ?? 0);
        $this->back($errors, $back, 'Supplier price saved. Existing quotations were not changed.');
    }

    /**
     * @param array<string, string> $errors
     */
    private function back(array $errors, string $path, string $message): void
    {
        if ($errors !== []) {
            flash('error', (string) ($errors['_form'] ?? reset($errors)));
        } else {
            flash('success', $message);
        }
        redirect($path);
    }
}
