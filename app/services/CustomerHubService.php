<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CustomerHubRepository;
use App\Repositories\PortalRepository;

/**
 * Customer hub orders are intake. They do not release production, consume stock, or issue invoices.
 * The customer id comes from the portal user, never from the browser.
 */
final class CustomerHubService
{
    /** @var array<string, list<string>> */
    public const ROLES = [
        'ADMIN' => [
            'portal.quotes.request', 'portal.quotes.view', 'portal.quotes.approve',
            'portal.orders.create', 'portal.orders.view', 'portal.artwork.approve',
            'portal.projects.view', 'portal.jobs.view', 'portal.assets.view',
            'portal.service.create', 'portal.service.view', 'portal.finance.view',
            'portal.finance.pay', 'portal.documents.view', 'portal.users.manage',
        ],
        'BUYER' => [
            'portal.quotes.request', 'portal.quotes.view', 'portal.orders.create', 'portal.orders.view',
            'portal.projects.view', 'portal.jobs.view', 'portal.documents.view', 'portal.assets.view',
        ],
        'MARKETING' => ['portal.artwork.approve', 'portal.quotes.view', 'portal.documents.view', 'portal.orders.view'],
        'PROJECT_MANAGER' => ['portal.projects.view', 'portal.jobs.view', 'portal.orders.view', 'portal.orders.create', 'portal.documents.view'],
        'SITE_MANAGER' => ['portal.projects.view', 'portal.jobs.view', 'portal.assets.view', 'portal.service.create', 'portal.service.view'],
        'ACCOUNTS' => ['portal.finance.view', 'portal.finance.pay', 'portal.documents.view', 'portal.quotes.view'],
        'VIEW_ONLY' => ['portal.quotes.view', 'portal.orders.view', 'portal.projects.view', 'portal.jobs.view', 'portal.assets.view', 'portal.documents.view'],
    ];

    /** @var list<string> */
    public const REQUEST_TYPES = [
        'GENERAL_SIGNAGE', 'VEHICLE_BRANDING', 'WINDOW_BRANDING', 'BUILDING_SIGNAGE',
        'LIGHTBOX', 'CHANNEL_LETTERS', 'PYLON', 'PRINTING', 'PROJECT_ROLLOUT', 'SERVICE', 'OTHER',
    ];

    public function __construct(
        private readonly CustomerHubRepository $hub = new CustomerHubRepository(),
        private readonly PortalRepository $portal = new PortalRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function allows(array $user, string $permission): bool
    {
        $role = strtoupper((string) ($user['hub_role'] ?? 'ADMIN'));
        if ($role === 'CUSTOM') {
            $list = array_map('trim', explode(',', (string) ($user['custom_permissions'] ?? '')));

            return in_array($permission, $list, true);
        }
        if (!isset(self::ROLES[$role])) {
            $role = 'VIEW_ONLY';
        }

        return in_array($permission, self::ROLES[$role], true);
    }

    public function canUseSite(array $user, int $siteId): bool
    {
        $site = $this->hub->siteForCustomer((int) $user['customer_id'], $siteId);
        if ($site === null) {
            return false;
        }
        if (strtoupper((string) ($user['site_scope'] ?? 'ALL')) !== 'SELECTED') {
            return true;
        }

        return $this->hub->siteGranted((int) $user['id'], $siteId);
    }

    public function catalogue(array $user, int $id): ?array
    {
        $row = $this->hub->catalogue((int) $user['customer_id'], $id);
        if ($row === null) {
            return null;
        }
        unset($row['created_by']);

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function saveCatalogue(array $input, int $userId): array
    {
        if (!can('customer_catalogues.manage')) {
            return ['errors' => ['_form' => 'You cannot change a customer catalogue.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($customerId < 1 || $name === '') {
            return ['errors' => ['_form' => 'A catalogue needs a customer and a name.'], 'id' => null];
        }
        $id = $this->hub->insertCatalogue([
            'customer_id' => $customerId,
            'name' => mb_substr($name, 0, 180),
            'description' => blank_to_null($input['description'] ?? null),
            'status' => 'DRAFT',
            'valid_from' => $this->date($input['valid_from'] ?? null),
            'valid_until' => $this->date($input['valid_until'] ?? null),
            'pricing_mode' => $this->pricingMode((string) ($input['pricing_mode'] ?? 'FIXED_AGREED_PRICE')),
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    public function activateCatalogue(int $customerId, int $catalogueId, int $userId): array
    {
        if (!can('customer_catalogues.manage')) {
            return ['_form' => 'You cannot approve a catalogue.'];
        }
        if ($this->hub->catalogue($customerId, $catalogueId) === null) {
            return ['_form' => 'That catalogue was not found.'];
        }
        $this->hub->setCatalogueStatus($catalogueId, 'ACTIVE', $userId);
        $this->audit->record('customer_catalogue', $catalogueId, 'CATALOGUE_ACTIVATED', null, [], $userId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function addItem(int $customerId, int $catalogueId, array $input, int $userId): array
    {
        if (!can('customer_catalogues.manage') || $this->hub->catalogue($customerId, $catalogueId) === null) {
            return ['errors' => ['_form' => 'That catalogue was not found.'], 'id' => null];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['name' => 'Name the catalogue item.'], 'id' => null];
        }
        $id = $this->hub->insertItem([
            'catalogue_id' => $catalogueId,
            'product_id' => ((int) ($input['product_id'] ?? 0)) > 0 ? (int) $input['product_id'] : null,
            'specification_id' => ((int) ($input['specification_id'] ?? 0)) > 0 ? (int) $input['specification_id'] : null,
            'customer_code' => blank_to_null($input['customer_code'] ?? null),
            'internal_code' => blank_to_null($input['internal_code'] ?? null),
            'name' => mb_substr($name, 0, 180),
            'description' => blank_to_null($input['description'] ?? null),
            'locked_config_json' => json_encode($input['locked'] ?? []),
            'allowed_variables_json' => json_encode($input['variables'] ?? []),
            'price_visibility' => strtoupper((string) ($input['price_visibility'] ?? 'SHOW_PRICE')),
            'availability_label' => strtoupper((string) ($input['availability_label'] ?? 'MADE_TO_ORDER')),
            'status' => 'ACTIVE',
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * A new price is a new row. The previous amount is not overwritten.
     *
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function savePrice(int $customerId, int $itemId, string $price, string $from, ?string $until, int $userId): array
    {
        if (!can('customer_catalogues.manage')) {
            return ['errors' => ['_form' => 'You cannot change an agreed price.'], 'id' => null];
        }
        if ($this->hub->itemForCustomer($customerId, $itemId) === null) {
            return ['errors' => ['_form' => 'That catalogue item was not found.'], 'id' => null];
        }
        $from = $this->date($from) ?? date('Y-m-d');
        $current = $this->hub->latestPrice($itemId);
        $id = Database::transaction(function () use ($itemId, $price, $from, $until, $userId, $current): int {
            if ($current !== null && (string) $current['effective_from'] < $from) {
                $close = date('Y-m-d', strtotime($from . ' -1 day'));
                $this->hub->closePrice((int) $current['id'], $close);
            }
            return $this->hub->insertPrice([
                'catalogue_item_id' => $itemId,
                'price_ex_vat' => Decimal::round($price, 4),
                'unit' => 'each',
                'currency_code' => 'ZAR',
                'effective_from' => $from,
                'effective_to' => $this->date($until),
                'min_quantity' => null,
                'max_quantity' => null,
                'status' => 'ACTIVE',
                'created_by' => $userId,
            ]);
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{ex_vat: string, vat: string, incl_vat: string, source: string}|null
     */
    public function priceOn(int $customerId, int $itemId, string $date): ?array
    {
        $item = $this->hub->itemForCustomer($customerId, $itemId);
        if ($item === null) {
            return null;
        }
        $row = $this->hub->openPrice($itemId, $date);
        if ($row === null) {
            return null;
        }
        $ex = Decimal::money((string) $row['price_ex_vat']);
        $rate = Decimal::div((string) SettingsService::get('default_vat_percent', '15'), '100', 6);
        $vat = Decimal::money(Decimal::mul($ex, $rate));

        return [
            'ex_vat' => $ex,
            'vat' => $vat,
            'incl_vat' => Decimal::money(Decimal::add($ex, $vat)),
            'source' => 'FIXED_AGREED_PRICE',
        ];
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{status: string, artwork_reused: bool, artwork_id: int|null, reasons: list<string>}
     */
    public function assessReorder(array $facts): array
    {
        $reasons = [];
        if (empty($facts['product_active']) || empty($facts['customer_active'])) {
            return ['status' => 'UNAVAILABLE', 'artwork_reused' => false, 'artwork_id' => null, 'reasons' => ['The product or customer is not active.']];
        }
        if (empty($facts['material_current']) || empty($facts['specification_current'])) {
            $reasons[] = 'The previous material or specification needs review.';
        }
        $artwork = strtoupper((string) ($facts['artwork_status'] ?? ''));
        if ($artwork === 'SUPERSEDED' || $artwork === 'EXPIRED') {
            $reasons[] = 'The previous artwork is not the current approved revision.';
        }
        $change = !empty($facts['artwork_change']);
        $reused = !$change && $artwork === 'APPROVED' && $reasons === [];
        if ($reasons !== []) {
            $status = empty($facts['price_valid']) ? 'QUOTE_REQUIRED' : 'REVIEW_REQUIRED';

            return ['status' => $status, 'artwork_reused' => false, 'artwork_id' => null, 'reasons' => $reasons];
        }
        if (empty($facts['price_valid'])) {
            return ['status' => 'QUOTE_REQUIRED', 'artwork_reused' => false, 'artwork_id' => null, 'reasons' => ['The agreed price is no longer valid.']];
        }

        return [
            'status' => 'READY_TO_ORDER',
            'artwork_reused' => $reused,
            'artwork_id' => $reused ? (int) ($facts['artwork_id'] ?? 0) : null,
            'reasons' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function submitOrder(array $user, array $input): array
    {
        if (!$this->allows($user, 'portal.orders.create')) {
            return ['errors' => ['_form' => 'You cannot place an order.'], 'id' => null];
        }
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($key !== '') {
            $existing = $this->hub->orderByKey($key);
            if ($existing !== null) {
                if ((int) $existing['customer_id'] !== (int) $user['customer_id']) {
                    return ['errors' => ['_form' => 'That order is not for your account.'], 'id' => null];
                }

                return ['errors' => [], 'id' => (int) $existing['id']];
            }
        }
        $item = $this->hub->itemForCustomer((int) $user['customer_id'], (int) ($input['catalogue_item_id'] ?? 0));
        if ($item === null || (string) $item['status'] !== 'ACTIVE' || (string) $item['catalogue_status'] !== 'ACTIVE') {
            return ['errors' => ['_form' => 'That catalogue item is not available.'], 'id' => null];
        }
        $rules = is_array($input['rules'] ?? null) ? $input['rules'] : [];
        $options = is_array($input['options'] ?? null) ? $input['options'] : [];
        if ($rules !== []) {
            $checked = (new CompatibilityRuleService())->evaluate($rules, $options);
            if ($checked['blocked']) {
                return ['errors' => ['_form' => $checked['messages'][0] ?? 'That combination is not allowed.'], 'id' => null];
            }
        }
        $sites = is_array($input['sites'] ?? null) ? $input['sites'] : [];
        $qty = '0';
        foreach ($sites as $siteId => $siteQty) {
            if (!$this->canUseSite($user, (int) $siteId)) {
                return ['errors' => ['_form' => 'One of those sites is not on your account.'], 'id' => null];
            }
            if (!Decimal::isNumeric((string) $siteQty) || Decimal::cmp((string) $siteQty, '0') <= 0) {
                return ['errors' => ['_form' => 'Enter a quantity for each site.'], 'id' => null];
            }
            $qty = Decimal::add($qty, (string) $siteQty, 4);
        }
        if (Decimal::cmp($qty, '0') <= 0) {
            $qty = Decimal::round((string) ($input['quantity'] ?? '0'), 4);
        }
        if (Decimal::cmp($qty, '0') <= 0) {
            return ['errors' => ['quantity' => 'Enter a quantity.'], 'id' => null];
        }
        if (!empty($input['require_po']) && trim((string) ($input['customer_po'] ?? '')) === '') {
            return ['errors' => ['customer_po' => 'A customer purchase order is required before this can be processed.'], 'id' => null];
        }
        $date = $this->date($input['order_date'] ?? null) ?? date('Y-m-d');
        $price = $this->priceOn((int) $user['customer_id'], (int) $item['id'], $date);
        if ($price === null) {
            return ['errors' => ['_form' => 'The agreed price is not valid on that date. This needs a quote. The expired price was not used.'], 'id' => null];
        }
        $ex = Decimal::money(Decimal::mul($price['ex_vat'], $qty));
        $rate = Decimal::div((string) SettingsService::get('default_vat_percent', '15'), '100', 6);
        $vat = Decimal::money(Decimal::mul($ex, $rate));
        $total = Decimal::money(Decimal::add($ex, $vat));
        $id = Database::transaction(function () use ($user, $input, $key, $item, $qty, $price, $ex, $vat, $total, $sites, $options): int {
            if ($key !== '') {
                $again = $this->hub->orderByKey($key);
                if ($again !== null) {
                    return (int) $again['id'];
                }
            }
            $orderId = $this->hub->insertOrder([
                'order_number' => $this->numbers->customerOrder(),
                'customer_id' => (int) $user['customer_id'],
                'portal_user_id' => (int) $user['id'],
                'order_kind' => 'CATALOGUE',
                'status' => 'SUBMITTED',
                'customer_po' => blank_to_null($input['customer_po'] ?? null),
                'cost_centre' => blank_to_null($input['cost_centre'] ?? null),
                'department' => blank_to_null($input['department'] ?? null),
                'branch_reference' => blank_to_null($input['branch_reference'] ?? null),
                'campaign_code' => blank_to_null($input['campaign_code'] ?? null),
                'idempotency_key' => $key !== '' ? $key : null,
                'requested_date' => $this->date($input['requested_date'] ?? null),
                'notes' => blank_to_null($input['notes'] ?? null),
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);
            $itemId = $this->hub->insertOrderItem([
                'order_id' => $orderId,
                'catalogue_item_id' => (int) $item['id'],
                'product_id' => $item['product_id'],
                'artwork_id' => ((int) ($input['artwork_id'] ?? 0)) > 0 ? (int) $input['artwork_id'] : null,
                'description' => (string) $item['name'],
                'quantity' => Decimal::round($qty, 4),
                'unit_price_ex_vat' => $price['ex_vat'],
                'vat_amount' => $vat,
                'line_total' => $total,
                'pricing_source' => 'FIXED_AGREED_PRICE',
                'configuration_json' => json_encode(['locked' => json_decode((string) ($item['locked_config_json'] ?? '{}'), true), 'options' => $options]),
                'customer_reference' => (string) ($item['customer_code'] ?? ''),
                'notes' => null,
            ]);
            foreach ($sites as $siteId => $siteQty) {
                $this->hub->insertOrderSite($itemId, (int) $siteId, Decimal::round((string) $siteQty, 4), $this->date($input['requested_date'] ?? null), null);
            }
            $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'ORDER_SUBMITTED', 'customer_order', $orderId, $this->ip());

            return $orderId;
        });
        BusinessEventDispatcher::emit('CUSTOMER_ORDER_SUBMITTED', 'CUSTOMER_ORDER', $id, null, ['customer_id' => (int) $user['customer_id']]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function review(int $orderId, string $action, int $userId): array
    {
        if (!can('customer_orders.review') && !can('customer_hub.view')) {
            return ['_form' => 'You cannot review a portal order.'];
        }
        $action = strtoupper($action);
        $status = match ($action) {
            'ACCEPT' => 'APPROVED',
            'QUOTE' => 'QUOTED',
            'INFO' => 'INFO_REQUIRED',
            'REJECT' => 'CANCELLED',
            default => '',
        };
        if ($status === '') {
            return ['_form' => 'Choose how to review the order.'];
        }
        $this->hub->setOrderStatus($orderId, $status, $userId);
        $this->audit->record('customer_order', $orderId, 'CUSTOMER_ORDER_REVIEWED', null, ['status' => $status], $userId);
        BusinessEventDispatcher::emit('CUSTOMER_ORDER_REVIEWED', 'CUSTOMER_ORDER', $orderId, $userId, ['status' => strtolower($status)]);

        return [];
    }

    /**
     * A browser success page is not a payment.
     *
     * @return array{paid: false, message: string}
     */
    public function claimBrowserSuccess(): array
    {
        return ['paid' => false, 'message' => 'A browser return does not record a payment.'];
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $options
     * @return array{blocked: bool, messages: list<string>}
     */
    public function checkOptions(array $rules, array $options): array
    {
        $checked = (new CompatibilityRuleService())->evaluate($rules, $options);

        return ['blocked' => $checked['blocked'], 'messages' => $checked['messages']];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function requestQuote(array $user, string $type, array $payload): array
    {
        if (!$this->allows($user, 'portal.quotes.request')) {
            return ['errors' => ['_form' => 'You cannot request a quote.'], 'id' => null];
        }
        $type = strtoupper($type);
        if (!in_array($type, self::REQUEST_TYPES, true)) {
            $type = 'OTHER';
        }
        $id = $this->hub->insertQuoteRequest([
            'customer_id' => (int) $user['customer_id'],
            'portal_user_id' => (int) $user['id'],
            'request_type' => $type,
            'payload_json' => json_encode($payload),
        ]);
        BusinessEventDispatcher::emit('CUSTOMER_QUOTE_REQUESTED', 'CUSTOMER_QUOTE_REQUEST', $id, null, []);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null, impact: string}
     */
    public function requestCancellation(array $user, int $jobId, string $reason, int $staffUserId = 0): array
    {
        $job = $this->portal->job((int) $user['customer_id'], $jobId);
        if ($job === null) {
            return ['errors' => ['_form' => 'That job is not on your account.'], 'id' => null, 'impact' => ''];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['errors' => ['reason' => 'Say why you want to cancel.'], 'id' => null, 'impact' => ''];
        }
        $impact = 'REVIEW_REQUIRED';
        if ($staffUserId > 0 && (can('production.change.request') || can('production.release.approve'))) {
            $recorded = (new ProductionChangeImpactService())->record($jobId, 'CANCELLATION', 'CUSTOMER', $reason, 'Customer requested cancellation', $staffUserId);
            if (($recorded['impact'] ?? '') !== '') {
                $impact = (string) $recorded['impact'];
            }
        }
        $id = $this->hub->insertCancellation([
            'customer_id' => (int) $user['customer_id'],
            'portal_user_id' => (int) $user['id'],
            'job_id' => $jobId,
            'reason' => mb_substr($reason, 0, 255),
            'impact' => $impact,
        ]);
        BusinessEventDispatcher::emit('CUSTOMER_CANCELLATION_REQUESTED', 'JOB', $jobId, $staffUserId > 0 ? $staffUserId : null, []);

        return ['errors' => [], 'id' => $id, 'impact' => $impact];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function projectView(array $user, int $projectId): ?array
    {
        if (!$this->allows($user, 'portal.projects.view')) {
            return null;
        }
        $project = $this->hub->projectForCustomer((int) $user['customer_id'], $projectId);
        if ($project === null) {
            return null;
        }
        $allowed = [];
        if (strtoupper((string) ($user['site_scope'] ?? 'ALL')) === 'SELECTED') {
            $allowed = $this->hub->grantedSiteIds((int) $user['id']);
        }
        $counts = ['total' => 0, 'installed' => 0, 'in_production' => 0, 'awaiting_approval' => 0, 'scheduled' => 0];
        if ($allowed !== [] || strtoupper((string) ($user['site_scope'] ?? 'ALL')) !== 'SELECTED') {
            foreach ($this->hub->siteStatusCounts($projectId, $allowed) as $row) {
                $n = (int) $row['n'];
                $counts['total'] += $n;
                $status = strtoupper((string) $row['status']);
                if ($status === 'INSTALLED' || $status === 'COMPLETE') {
                    $counts['installed'] += $n;
                } elseif ($status === 'IN_PRODUCTION') {
                    $counts['in_production'] += $n;
                } elseif ($status === 'AWAITING_APPROVAL' || $status === 'AWAITING_CUSTOMER') {
                    $counts['awaiting_approval'] += $n;
                } elseif ($status === 'SCHEDULED') {
                    $counts['scheduled'] += $n;
                }
            }
        }

        return [
            'project_number' => (string) $project['project_number'],
            'name' => (string) $project['name'],
            'status' => (string) $project['status'],
            'sites' => $counts,
        ];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function reportAsset(array $user, int $assetId, string $description): array
    {
        if (!$this->allows($user, 'portal.service.create')) {
            return ['errors' => ['_form' => 'You cannot report a problem.'], 'id' => null];
        }

        return (new ServiceRequestService())->create([
            'customer_id' => (int) $user['customer_id'],
            'asset_id' => $assetId,
            'description' => $description,
            'source' => 'CUSTOMER_PORTAL',
            'priority' => 'NORMAL',
            'problem_category' => 'OTHER',
        ], 0, true);
    }

    /**
     * @return array{errors: array<string, string>, id: int|null, warning: string|null}
     */
    public function requestSite(array $user, string $name, string $address, string $code): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['errors' => ['site_name' => 'Name the site.'], 'id' => null, 'warning' => null];
        }
        $match = $this->hub->matchingSite((int) $user['customer_id'], $code, $address);
        $warning = $match === null ? null : 'A site with this branch code or address already exists. It was not created automatically.';
        $id = $this->hub->insertSiteRequest([
            'customer_id' => (int) $user['customer_id'],
            'portal_user_id' => (int) $user['id'],
            'site_name' => mb_substr($name, 0, 180),
            'address' => $address,
            'branch_code' => $code,
            'duplicate_warning' => $warning,
        ]);
        BusinessEventDispatcher::emit('CUSTOMER_SITE_REQUESTED', 'PORTAL_SITE_REQUEST', $id, null, []);

        return ['errors' => [], 'id' => $id, 'warning' => $warning];
    }

    public function portalMessages(array $user, string $type, int $entityId): array
    {
        return $this->hub->portalMessages((int) $user['customer_id'], $type, $entityId);
    }

    public function addInternalNote(int $customerId, string $type, int $entityId, string $body, int $userId): void
    {
        $this->hub->insertNote($customerId, null, $type, $entityId, $body, 'INTERNAL');
        $this->audit->record('customer_order', $entityId, 'INTERNAL_NOTE', null, [], $userId);
    }

    /**
     * @return array{warning: bool, margin_percent: string, sell: string, cost: string}|null
     */
    public function marginAlert(string $sell, string $cost): ?array
    {
        if (!Decimal::isNumeric($sell) || Decimal::cmp($sell, '0') <= 0) {
            return null;
        }
        $margin = Decimal::round(Decimal::mul(Decimal::div(Decimal::sub($sell, $cost, 4), $sell, 6), '100'), 2);
        $threshold = (string) SettingsService::get('catalogue_margin_alert_percent', '15');

        return [
            'warning' => Decimal::cmp($margin, $threshold) < 0,
            'margin_percent' => $margin,
            'sell' => Decimal::money($sell),
            'cost' => Decimal::money($cost),
        ];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function rejectFile(string $name, string $bytes): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'phtml', 'exe', 'sh', 'js', 'html', 'htm'], true) || str_contains(strtolower($name), '.php')) {
            return ['_form' => 'That file type is not accepted.'];
        }
        if ($ext === 'pdf' && !str_starts_with($bytes, '%PDF')) {
            return ['_form' => 'That file is not a PDF.'];
        }
        if (strlen($bytes) > 5_000_000) {
            return ['_form' => 'That file is larger than 5 MB.'];
        }

        return [];
    }

    public function search(array $user, string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        return $this->hub->searchOrders((int) $user['customer_id'], $q, 20);
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function saveTemplate(array $user, string $name, array $items): array
    {
        if (!$this->allows($user, 'portal.orders.create')) {
            return ['errors' => ['_form' => 'You cannot save an order template.'], 'id' => null];
        }
        $id = $this->hub->insertTemplate((int) $user['customer_id'], (int) $user['id'], mb_substr(trim($name), 0, 180), json_encode($items) ?: '[]');

        return ['errors' => [], 'id' => $id];
    }

    public function saveColour(int $customerId, array $input, int $userId): int
    {
        $id = $this->hub->insertColour([
            'customer_id' => $customerId,
            'name' => mb_substr(trim((string) ($input['name'] ?? 'Colour')), 0, 120),
            'pantone' => blank_to_null($input['pantone'] ?? null),
            'cmyk' => blank_to_null($input['cmyk'] ?? null),
            'rgb' => blank_to_null($input['rgb'] ?? null),
            'ral' => blank_to_null($input['ral'] ?? null),
            'vinyl_code' => blank_to_null($input['vinyl_code'] ?? null),
            'notes' => 'Stored as supplied. These codes are not converted into each other.',
        ]);
        $this->audit->record('customer', $customerId, 'BRAND_COLOUR', null, ['id' => $id], $userId);

        return $id;
    }

    private function pricingMode(string $mode): string
    {
        $mode = strtoupper($mode);
        $allowed = ['STANDARD_CURRENT', 'CUSTOMER_PRICE_LEVEL', 'FIXED_AGREED_PRICE', 'FORMULA_BASED', 'QUOTE_REQUIRED'];

        return in_array($mode, $allowed, true) ? $mode : 'QUOTE_REQUIRED';
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}
