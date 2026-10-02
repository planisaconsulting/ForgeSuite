<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ProcurementRepository;
use App\Services\SupplierRfqService;
use App\Services\WarehouseService;

final class ProcurementController
{
    public function workbench(): void
    {
        $repo = new ProcurementRepository();
        View::render('purchasing/workbench', [
            'title' => 'Purchasing workbench',
            'activeNav' => 'procurement-workbench',
            'cards' => $repo->workbench(),
            'onTime' => $repo->onTimeDeliveries(),
            'showCost' => can('procurement.quotes.view') || can('purchasing.view'),
        ]);
    }

    public function rfq(string $id): void
    {
        $rfqId = (int) $id;
        $repo = new ProcurementRepository();
        $rfq = $repo->rfq($rfqId);
        if ($rfq === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Not found']);

            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && can('procurement.rfq.award')) {
            $lines = [[
                'quotation_item_id' => (int) ($_POST['quotation_item_id'] ?? 0),
                'quantity' => (string) ($_POST['quantity'] ?? '0'),
                'reason' => (string) ($_POST['reason'] ?? 'OTHER'),
            ]];
            $result = (new SupplierRfqService())->award($rfqId, $lines, (int) auth_user()['id']);
            flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Draft purchase orders created. Nothing was sent.' : (string) ($result['errors']['_form'] ?? 'The award was not saved.'));
            redirect('/purchasing/rfqs/' . $rfqId);
        }
        View::render('purchasing/rfq', [
            'title' => (string) $rfq['rfq_number'],
            'activeNav' => 'procurement-workbench',
            'rfq' => $rfq,
            'items' => $repo->rfqItems($rfqId),
            'comparison' => (new SupplierRfqService())->compare($rfqId),
            'awards' => $repo->awards($rfqId),
        ]);
    }

    public function supplier(string $token): void
    {
        $opened = (new SupplierRfqService())->openToken($token);
        if (!$opened['found']) {
            http_response_code(403);
            View::render('supplier/denied', ['title' => 'Supplier link', 'message' => (string) $opened['error']], 'layouts/supplier');

            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $lines = [[
                'rfq_item_id' => (int) ($_POST['rfq_item_id'] ?? 0),
                'unit_price' => (string) ($_POST['unit_price'] ?? '0'),
                'available_quantity' => (string) ($_POST['available_quantity'] ?? '0'),
                'offered_description' => (string) ($_POST['offered_description'] ?? 'Offered item'),
                'lead_time_days' => (string) ($_POST['lead_time_days'] ?? ''),
                'alternative' => !empty($_POST['alternative']),
                'product_id' => (int) ($_POST['product_id'] ?? 0),
            ]];
            $saved = (new SupplierRfqService())->respond($token, [
                'delivery_amount' => (string) ($_POST['delivery_amount'] ?? '0'),
                'notes' => (string) ($_POST['notes'] ?? ''),
            ], $lines);
            flash($saved['errors'] === [] ? 'success' : 'error', $saved['errors'] === [] ? 'Quotation submitted.' : (string) ($saved['errors']['_form'] ?? 'The quotation was not saved.'));
            redirect('/supplier/' . $token);
        }
        View::render('supplier/rfq', [
            'title' => 'Request for quotation',
            'opened' => $opened,
            'token' => $token,
        ], 'layouts/supplier');
    }

    public function locations(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $made = (new WarehouseService())->createLocation($_POST, (int) auth_user()['id']);
            flash($made['errors'] === [] ? 'success' : 'error', $made['errors'] === [] ? 'Location saved.' : (string) ($made['errors']['code'] ?? $made['errors']['_form'] ?? 'Not saved.'));
            redirect('/inventory/locations');
        }
        View::render('inventory/locations', [
            'title' => 'Warehouse locations',
            'activeNav' => 'inventory',
        ]);
    }

    public function mobileReceive(): void
    {
        View::render('mobile/receiving', [
            'title' => 'Receive goods',
            'activeNav' => 'purchasing',
        ], 'layouts/app');
    }
}
