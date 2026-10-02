<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ContractorRepository;
use App\Repositories\LogisticsRepository;
use App\Services\ContractorWorkService;
use App\Services\LogisticsService;

/**
 * Staff logistics desk. Costs stay on the internal screens.
 */
final class LogisticsController
{
    public function index(): void
    {
        $service = new LogisticsService();
        View::render('logistics/dashboard', [
            'title' => 'Logistics',
            'activeNav' => 'logistics',
            'cards' => $service->dashboard(),
            'shipments' => $service->page(['status' => ''], 12, 0),
            'couriers' => (new LogisticsRepository())->couriers(false),
        ]);
    }

    public function show(string $id): void
    {
        $service = new LogisticsService();
        $shipment = (new LogisticsRepository())->shipment((int) $id);
        if ($shipment === null) {
            redirect('/logistics');
        }
        View::render('logistics/shipment', [
            'title' => (string) $shipment['shipment_number'],
            'activeNav' => 'logistics',
            'shipment' => $shipment,
            'items' => (new LogisticsRepository())->items((int) $id),
            'packages' => (new LogisticsRepository())->packages((int) $id),
            'events' => (new LogisticsRepository())->events((int) $id),
            'checks' => $service->dispatchChecks((int) $id),
            'link' => $service->trackingLink((int) $id),
            'profit' => can('contractor.cost.view') || can('logistics.shipment.manage') ? $service->profitability((int) $id) : [],
            'couriers' => (new LogisticsRepository())->couriers(),
            'pod' => (new LogisticsRepository())->pod((int) $id),
        ]);
    }

    public function create(): void
    {
        $made = (new LogisticsService())->createShipment($_POST, (int) auth_user()['id']);
        if ($made['errors'] !== []) {
            flash('error', (string) reset($made['errors']));
            redirect('/logistics');
        }
        redirect('/logistics/shipments/' . $made['id']);
    }

    public function addItem(string $id): void
    {
        $result = (new LogisticsService())->addItem((int) $id, $_POST, (int) auth_user()['id']);
        flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Item added.' : (string) reset($result['errors']));
        redirect('/logistics/shipments/' . $id);
    }

    public function book(string $id): void
    {
        $errors = (new LogisticsService())->bookCourier((int) $id, $_POST, (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Courier booked. This is not a collection or a delivery.' : (string) reset($errors));
        redirect('/logistics/shipments/' . $id);
    }

    public function dispatch(string $id): void
    {
        $errors = (new LogisticsService())->dispatch((int) $id, (int) auth_user()['id'], (string) ($_POST['override'] ?? ''));
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Dispatched. Delivery is still outstanding.' : (string) reset($errors));
        redirect('/logistics/shipments/' . $id);
    }

    public function event(string $id): void
    {
        $result = (new LogisticsService())->recordEvent((int) $id, $_POST, (int) auth_user()['id']);
        flash($result['errors'] === [] ? 'success' : 'error', $result['duplicate'] ? 'That courier event was already recorded.' : ($result['errors'] === [] ? 'Tracking recorded.' : (string) reset($result['errors'])));
        redirect('/logistics/shipments/' . $id);
    }

    public function label(string $id): void
    {
        $label = (new LogisticsService())->label((int) $id);
        if ($label === []) {
            redirect('/logistics');
        }
        View::render('logistics/label', [
            'title' => (string) $label['package_code'],
            'activeNav' => 'logistics',
            'label' => $label,
        ]);
    }

    public function contractors(): void
    {
        View::render('logistics/contractors', [
            'title' => 'Contractors',
            'activeNav' => 'contractors',
            'rows' => (new ContractorRepository())->list(50, 0),
        ]);
    }

    public function saveContractor(): void
    {
        $made = (new ContractorWorkService())->save($_POST, (int) auth_user()['id']);
        flash($made['errors'] === [] ? 'success' : 'error', $made['errors'] === [] ? 'Contractor saved.' : (string) reset($made['errors']));
        redirect('/logistics/contractors');
    }

    public function workOrders(): void
    {
        View::render('logistics/work_orders', [
            'title' => 'Contractor work',
            'activeNav' => 'contractor-work',
            'rows' => (new ContractorRepository())->list(20, 0),
        ]);
    }

    public function reports(): void
    {
        $service = new LogisticsService();
        View::render('logistics/reports', [
            'title' => 'Logistics reports',
            'activeNav' => 'logistics-reports',
            'cards' => $service->dashboard(),
            'couriers' => $service->courierPerformance(),
        ]);
    }

    public function track(string $token): void
    {
        $row = (new LogisticsService())->publicTrack($token);
        View::render('logistics/track', [
            'title' => 'Shipment tracking',
            'row' => $row,
        ], 'layouts/portal');
    }

    public function portalShipments(): void
    {
        $user = (new \App\Services\PortalAuthService())->user();
        if ($user === null) {
            redirect('/portal/login');
        }
        $rows = (new LogisticsService())->page(['customer_id' => (int) $user['customer_id']], 30, 0);
        $safe = [];
        foreach ($rows as $row) {
            $view = (new LogisticsService())->customerSafe((int) $row['id'], (int) $user['customer_id']);
            if ($view !== null) {
                $safe[] = $view;
            }
        }
        View::render('logistics/portal_shipments', [
            'title' => 'Deliveries',
            'rows' => $safe,
        ], 'layouts/portal');
    }
}
