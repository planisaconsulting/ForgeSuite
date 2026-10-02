<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CustomerHubRepository;
use App\Services\CustomerHubService;
use App\Services\PortalAuthService;

/**
 * Customer hub pages. The portal user comes from the session.
 */
final class CustomerHubController
{
    public function orderForm(): void
    {
        $user = $this->user();
        if ($user === null || !(new CustomerHubService())->allows($user, 'portal.orders.create')) {
            $this->denied();
        }
        View::render('portal/order', [
            'title' => 'Order signage',
            'user' => $user,
            'errors' => [],
        ], 'layouts/portal');
    }

    public function submitOrder(): void
    {
        $user = $this->user();
        if ($user === null) {
            $this->denied();
        }
        $sites = [];
        $rawSites = $_POST['site_id'] ?? [];
        $rawQty = $_POST['site_qty'] ?? [];
        if (is_array($rawSites)) {
            foreach ($rawSites as $index => $siteId) {
                $qty = is_array($rawQty) ? (string) ($rawQty[$index] ?? '') : '';
                if ((int) $siteId > 0 && $qty !== '') {
                    $sites[(int) $siteId] = $qty;
                }
            }
        }
        $result = (new CustomerHubService())->submitOrder($user, [
            'catalogue_item_id' => (int) ($_POST['catalogue_item_id'] ?? 0),
            'quantity' => (string) ($_POST['quantity'] ?? ''),
            'customer_po' => (string) ($_POST['customer_po'] ?? ''),
            'cost_centre' => (string) ($_POST['cost_centre'] ?? ''),
            'notes' => (string) ($_POST['notes'] ?? ''),
            'requested_date' => (string) ($_POST['requested_date'] ?? ''),
            'idempotency_key' => (string) ($_POST['idempotency_key'] ?? ''),
            'sites' => $sites,
            'customer_id' => (int) ($_POST['customer_id'] ?? 0),
        ]);
        if ($result['errors'] !== []) {
            View::render('portal/order', ['title' => 'Order signage', 'user' => $user, 'errors' => $result['errors']], 'layouts/portal');

            return;
        }
        flash('success', 'Order submitted. It is waiting for Sign-Forge to review. Production has not started.');
        redirect('/portal');
    }

    public function quoteRequest(): void
    {
        $user = $this->user();
        if ($user === null || !(new CustomerHubService())->allows($user, 'portal.quotes.request')) {
            $this->denied();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = (new CustomerHubService())->requestQuote($user, (string) ($_POST['request_type'] ?? 'OTHER'), [
                'notes' => (string) ($_POST['notes'] ?? ''),
                'site' => (string) ($_POST['site'] ?? ''),
            ]);
            flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Quote request sent.' : (string) reset($result['errors']));
            redirect('/portal');
        }
        View::render('portal/quote_request', [
            'title' => 'Request a quote',
            'types' => CustomerHubService::REQUEST_TYPES,
        ], 'layouts/portal');
    }

    public function project(string $id): void
    {
        $user = $this->user();
        if ($user === null) {
            $this->denied();
        }
        $view = (new CustomerHubService())->projectView($user, (int) $id);
        if ($view === null) {
            $this->denied();
        }
        View::render('portal/project', ['title' => 'Project', 'project' => $view], 'layouts/portal');
    }

    public function inbox(): void
    {
        if (!can('customer_hub.view')) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Not allowed']);

            return;
        }
        View::render('sales/portal_inbox', [
            'title' => 'Portal requests',
            'activeNav' => 'portal-inbox',
            'cards' => (new CustomerHubRepository())->inbox(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function user(): ?array
    {
        return (new PortalAuthService())->user();
    }

    private function denied(): never
    {
        http_response_code(403);
        View::render('portal/denied', ['title' => 'Not available'], 'layouts/portal');
        exit;
    }
}
