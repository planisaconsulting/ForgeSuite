<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\LogisticsRepository;
use App\Repositories\OperationsRepository;
use PDOException;

/**
 * Shipments sit on top of fulfilment and workshop packages.
 * Dispatched, collected, delivered, and installed stay separate events.
 */
final class LogisticsService
{
    /** @var list<string> */
    public const TYPES = ['COURIER', 'OWN_DELIVERY', 'CUSTOMER_COLLECTION', 'INSTALLATION', 'THIRD_PARTY_DELIVERY', 'OTHER'];

    /** @var list<string> */
    public const STATUSES = [
        'DRAFT', 'READY', 'BOOKED', 'AWAITING_COLLECTION', 'COLLECTED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY',
        'DELIVERED', 'DELIVERY_FAILED', 'RETURNING', 'RETURNED', 'CANCELLED',
        'CUSTOMER_NOTIFIED',
    ];

    /** @var list<string> */
    public const SOURCES = ['MANUAL', 'COURIER_API', 'INTERNAL', 'CUSTOMER_CONFIRMATION'];

    /** @var list<string> */
    public const FAIL_REASONS = ['NO_ONE_AVAILABLE', 'WRONG_ADDRESS', 'ACCESS_DENIED', 'DAMAGED', 'CUSTOMER_REFUSED', 'OTHER'];

    /** @var list<string> */
    public const EXCEPTION_TYPES = [
        'DAMAGED', 'MISSING_ITEM', 'WRONG_ITEM', 'DELIVERY_FAILED', 'LOST_PACKAGE', 'ADDRESS_PROBLEM',
        'CUSTOMER_REFUSED', 'COURIER_DELAY', 'INSTALLATION_ACCESS', 'OTHER',
    ];

    /** @var list<string> */
    public const DISPOSITIONS = ['REWORK', 'REMAKE', 'RETURN', 'ACCEPTED', 'OTHER'];

    /** @var list<string> */
    public const LEFT = ['AWAITING_COLLECTION', 'COLLECTED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED', 'RETURNING', 'RETURNED'];

    public function __construct(
        private readonly LogisticsRepository $repo = new LogisticsRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly ManualCourierProvider $manual = new ManualCourierProvider()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function saveCourier(array $input, int $userId): array
    {
        if (!can('logistics.shipment.manage')) {
            return ['errors' => ['_form' => 'You cannot manage couriers.'], 'id' => null];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['name' => 'Enter the courier name.'], 'id' => null];
        }
        $mode = strtoupper(trim((string) ($input['integration_type'] ?? 'MANUAL')));
        if (!in_array($mode, ['MANUAL', 'LINK_ONLY', 'API'], true)) {
            return ['errors' => ['integration_type' => 'Choose manual, link only, or API.'], 'id' => null];
        }
        $template = trim((string) ($input['tracking_url_template'] ?? ''));
        if ($template !== '' && TrackingUrl::build($template, 'SAMPLE', 'SAMPLE') === null) {
            return ['errors' => ['tracking_url_template' => 'The tracking template must be an https address with {tracking} or {waybill}.'], 'id' => null];
        }
        $id = $this->repo->insertCourier([
            'name' => mb_substr($name, 0, 180),
            'account_reference' => $this->blank($input['account_reference'] ?? null),
            'contact_name' => $this->blank($input['contact_name'] ?? null),
            'contact_email' => $this->blank($input['contact_email'] ?? null),
            'contact_phone' => $this->blank($input['contact_phone'] ?? null),
            'tracking_url_template' => $template === '' ? null : $template,
            'integration_type' => $mode,
            'active' => empty($input['active']) ? 0 : 1,
        ]);
        $this->audit->record('courier', $id, 'COURIER_SAVED', null, ['mode' => $mode], $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createShipment(array $input, int $userId): array
    {
        if (!can('logistics.shipment.create')) {
            return ['errors' => ['_form' => 'You cannot create a shipment.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['shipment_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            return ['errors' => ['shipment_type' => 'Choose how this shipment will travel.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        if ($customerId < 1) {
            return ['errors' => ['customer_id' => 'Choose a customer.'], 'id' => null];
        }
        $id = Database::transaction(function () use ($input, $userId, $type, $customerId): int {
            $id = $this->repo->insertShipment([
                'shipment_number' => $this->numbers->shipment(),
                'shipment_type' => $type,
                'status' => 'DRAFT',
                'customer_id' => $customerId,
                'job_id' => $this->positive($input['job_id'] ?? 0),
                'project_id' => $this->positive($input['project_id'] ?? 0),
                'project_site_id' => $this->positive($input['project_site_id'] ?? 0),
                'dispatch_id' => $this->positive($input['dispatch_id'] ?? 0),
                'destination_address' => $this->blank($input['destination_address'] ?? null),
                'contact_name' => $this->blank($input['contact_name'] ?? null),
                'contact_phone' => $this->blank($input['contact_phone'] ?? null),
                'required_date' => $this->date($input['required_date'] ?? null),
                'internal_notes' => $this->blank($input['internal_notes'] ?? null),
                'customer_note' => $this->blank($input['customer_note'] ?? null),
                'created_by' => $userId,
            ]);
            $this->emit('SHIPMENT_CREATED', $id, $userId, ['type' => strtolower($type)]);

            return $id;
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function addItem(int $shipmentId, array $input, int $userId): array
    {
        if (!can('logistics.shipment.create')) {
            return ['errors' => ['_form' => 'You cannot add to a shipment.'], 'id' => null];
        }
        $qty = (string) ($input['quantity'] ?? '0');
        if (!Decimal::isNumeric($qty) || Decimal::cmp($qty, '0') <= 0) {
            return ['errors' => ['quantity' => 'Quantity must be greater than zero.'], 'id' => null];
        }
        $jobId = (int) ($input['job_id'] ?? 0);
        $itemId = (int) ($input['job_item_id'] ?? 0);
        try {
            $id = Database::transaction(function () use ($shipmentId, $input, $userId, $qty, $jobId, $itemId): int {
                $shipment = $this->repo->lockShipment($shipmentId);
                if ($shipment === null || in_array((string) $shipment['status'], ['CANCELLED', 'DELIVERED', 'RETURNED'], true)) {
                    throw new StockRejected(['_form' => 'That shipment cannot take more items.']);
                }
                if ($itemId > 0) {
                    $item = $this->repo->lockItem($itemId);
                    if ($item === null || (int) $item['job_id'] !== $jobId) {
                        throw new StockRejected(['job_item_id' => 'That item is not on this job.']);
                    }
                    $good = (string) $item['good_quantity'];
                    $committed = Decimal::add($this->repo->committedQuantity($itemId), $qty, 4);
                    if (Decimal::cmp($committed, $good) > 0) {
                        throw new StockRejected(['_form' => 'Blocked. You cannot ship more than the QC-passed quantity of ' . $good . '.']);
                    }
                }
                $fulfilmentId = (int) ($input['fulfilment_requirement_id'] ?? 0);
                if ($fulfilmentId > 0) {
                    $line = $this->repo->fulfilment($fulfilmentId);
                    if ($line === null || (int) $line['job_id'] !== $jobId) {
                        throw new StockRejected(['fulfilment_requirement_id' => 'That fulfilment line is not on this job.']);
                    }
                    $room = Decimal::sub((string) $line['quantity'], (string) $line['dispatched_quantity'], 4);
                    if (Decimal::cmp($qty, $room) > 0) {
                        throw new StockRejected(['_form' => 'Blocked. That is more than the quantity still available to ship.']);
                    }
                }
                if ($this->repo->openQcFail($jobId) > 0) {
                    throw new StockRejected(['_form' => 'QC has failed. This shipment waits for a disposition.']);
                }

                return $this->repo->insertItem([
                    'shipment_id' => $shipmentId,
                    'job_id' => $jobId,
                    'job_item_id' => $itemId > 0 ? $itemId : null,
                    'fulfilment_requirement_id' => $fulfilmentId > 0 ? $fulfilmentId : null,
                    'package_id' => $this->positive($input['package_id'] ?? 0),
                    'quantity' => Decimal::round($qty, 4),
                    'description' => mb_substr(trim((string) ($input['description'] ?? 'Item')), 0, 255),
                    'tracking_code' => $this->blank($input['tracking_code'] ?? null),
                ]);
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }
        $this->audit->record('shipment', $shipmentId, 'SHIPMENT_ITEM_ADDED', null, ['item_id' => $id], $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>}
     */
    public function describePackage(int $packageId, int $shipmentId, array $input, int $userId): array
    {
        if (!can('logistics.shipment.create')) {
            return ['_form' => 'You cannot update a package.'];
        }
        $package = $this->repo->package($packageId);
        $shipment = $this->repo->shipment($shipmentId);
        if ($package === null || $shipment === null) {
            return ['_form' => 'That package or shipment was not found.'];
        }
        if ((int) $package['job_id'] > 0 && (int) ($shipment['job_id'] ?? 0) > 0 && (int) $package['job_id'] !== (int) $shipment['job_id']) {
            $jobs = array_map(static fn (array $row): int => (int) $row['job_id'], $this->repo->items($shipmentId));
            if (!in_array((int) $package['job_id'], $jobs, true)) {
                return ['_form' => 'WRONG JOB. That package does not belong on this shipment.'];
            }
        }
        $count = count($this->repo->packages($shipmentId)) + 1;
        $this->repo->updatePackage($packageId, [
            'shipment_id' => $shipmentId,
            'project_site_id' => $this->positive($input['project_site_id'] ?? $shipment['project_site_id'] ?? 0),
            'package_type' => mb_substr(strtoupper(trim((string) ($input['package_type'] ?? 'CARTON'))), 0, 30),
            'length_mm' => $this->blank($input['length_mm'] ?? null),
            'width_mm' => $this->blank($input['width_mm'] ?? null),
            'height_mm' => $this->blank($input['height_mm'] ?? null),
            'weight_kg' => $this->blank($input['weight_kg'] ?? null),
            'fragile' => empty($input['fragile']) ? 0 : 1,
            'special_handling' => $this->blank($input['special_handling'] ?? null),
            'sequence_no' => (int) ($input['sequence_no'] ?? $count),
            'sequence_total' => (int) ($input['sequence_total'] ?? $count),
        ]);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, package_id: int|null}
     */
    public function verifyScan(int $shipmentId, string $code, int $userId): array
    {
        if (!can('logistics.shipment.dispatch') && !can('workshop.scan')) {
            return ['errors' => ['_form' => 'You cannot verify a package.'], 'package_id' => null];
        }
        $package = $this->repo->packageByCode(strtoupper(trim($code)));
        $shipment = $this->repo->shipment($shipmentId);
        if ($package === null || $shipment === null) {
            return ['errors' => ['code' => 'That package was not found.'], 'package_id' => null];
        }
        $jobs = array_map(static fn (array $row): int => (int) $row['job_id'], $this->repo->items($shipmentId));
        if ((int) ($shipment['job_id'] ?? 0) > 0) {
            $jobs[] = (int) $shipment['job_id'];
        }
        if ($jobs !== [] && !in_array((int) $package['job_id'], $jobs, true)) {
            return ['errors' => ['_form' => 'WRONG JOB. That package does not belong on this shipment.'], 'package_id' => null];
        }
        if ($package['shipment_id'] !== null && (int) $package['shipment_id'] !== $shipmentId) {
            return ['errors' => ['_form' => 'WRONG SHIPMENT. That package is allocated to a different shipment.'], 'package_id' => null];
        }
        if ($package['project_site_id'] !== null && $shipment['project_site_id'] !== null && (int) $package['project_site_id'] !== (int) $shipment['project_site_id']) {
            return ['errors' => ['_form' => 'WRONG SITE. That package is for a different site.'], 'package_id' => null];
        }
        $this->repo->verifyPackage((int) $package['id'], $userId);

        return ['errors' => [], 'package_id' => (int) $package['id']];
    }

    /**
     * @return list<string>
     */
    public function dispatchChecks(int $shipmentId): array
    {
        $shipment = $this->repo->shipment($shipmentId);
        if ($shipment === null) {
            return ['That shipment was not found.'];
        }
        $issues = [];
        $items = $this->repo->items($shipmentId);
        if ($items === []) {
            $issues[] = 'The shipment has no items.';
        }
        foreach ($items as $item) {
            $jobId = (int) $item['job_id'];
            if ($this->repo->openQcFail($jobId) > 0) {
                $issues[] = 'QC has not passed.';
            }
        }
        $packages = $this->repo->packages($shipmentId);
        if ($packages === []) {
            $issues[] = 'Packing is not complete.';
        }
        foreach ($packages as $package) {
            if ((string) $package['status'] !== 'VERIFIED') {
                $issues[] = 'A package is not verified.';
                break;
            }
        }
        if (trim((string) ($shipment['destination_address'] ?? '')) === '' && (string) $shipment['shipment_type'] !== 'CUSTOMER_COLLECTION') {
            $issues[] = 'The destination is missing.';
        }
        if (trim((string) ($shipment['contact_name'] ?? '')) === '') {
            $issues[] = 'The contact is missing.';
        }

        return $issues;
    }

    /**
     * @return array<string, string>
     */
    public function markReady(int $shipmentId, int $userId): array
    {
        $issues = $this->dispatchChecks($shipmentId);
        if ($issues !== []) {
            return ['_form' => implode(' ', $issues)];
        }
        $this->repo->setShipmentStatus($shipmentId, 'READY');
        $this->audit->record('shipment', $shipmentId, 'SHIPMENT_READY', null, [], $userId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function dispatch(int $shipmentId, int $userId, string $override = ''): array
    {
        if (!can('logistics.shipment.dispatch')) {
            return ['_form' => 'You cannot dispatch a shipment.'];
        }
        $issues = $this->dispatchChecks($shipmentId);
        $policy = (string) SettingsService::get('logistics_incomplete_policy', 'OVERRIDE');
        if ($issues !== []) {
            if ($policy === 'BLOCK' || trim($override) === '') {
                return ['_form' => 'Dispatch is blocked. ' . implode(' ', $issues)];
            }
        }
        try {
            Database::transaction(function () use ($shipmentId, $userId, $override): void {
                $shipment = $this->repo->lockShipment($shipmentId);
                if ($shipment === null) {
                    throw new StockRejected(['_form' => 'That shipment was not found.']);
                }
                if (in_array((string) $shipment['status'], self::LEFT, true)) {
                    return;
                }
                foreach ($this->repo->items($shipmentId) as $item) {
                    $fulfilmentId = (int) ($item['fulfilment_requirement_id'] ?? 0);
                    if ($fulfilmentId > 0) {
                        $this->repo->addDispatched($fulfilmentId, (string) $item['quantity']);
                    }
                }
                $status = (string) $shipment['shipment_type'] === 'CUSTOMER_COLLECTION' ? 'AWAITING_COLLECTION' : 'AWAITING_COLLECTION';
                $this->repo->setShipmentStatus($shipmentId, $status, [
                    'override_reason' => $override === '' ? null : mb_substr($override, 0, 255),
                ]);
                $this->repo->insertEvent([
                    'shipment_id' => $shipmentId,
                    'status' => $status,
                    'event_time' => date('Y-m-d H:i:s'),
                    'location_text' => null,
                    'description' => 'Dispatched from the workshop. Not yet delivered.',
                    'source' => 'INTERNAL',
                    'external_event_id' => null,
                ]);
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }
        $this->notify($userId, 'Shipment dispatched', 'A shipment left the workshop and is not marked delivered.', 'SHIPMENT', $shipmentId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function bookCourier(int $shipmentId, array $input, int $userId): array
    {
        if (!can('logistics.shipment.manage') && !can('logistics.shipment.dispatch')) {
            return ['_form' => 'You cannot book a courier.'];
        }
        $courier = $this->repo->courier((int) ($input['courier_id'] ?? 0));
        if ($courier === null || (int) $courier['active'] !== 1) {
            return ['courier_id' => 'Choose an active courier.'];
        }
        $waybill = trim((string) ($input['waybill_number'] ?? ''));
        $tracking = trim((string) ($input['tracking_number'] ?? ''));
        if ($waybill === '' && $tracking === '') {
            return ['waybill_number' => 'Enter a waybill or a tracking number.'];
        }
        $estimated = $this->money($input['estimated_courier_cost'] ?? '0');
        $actual = $this->money($input['actual_courier_cost'] ?? '0');
        $charge = $this->money($input['customer_delivery_charge'] ?? '0');
        if ($estimated === null || $actual === null || $charge === null) {
            return ['estimated_courier_cost' => 'Enter the courier amounts as numbers.'];
        }
        $recorded = $this->manual->createShipment([
            'waybill_number' => $waybill,
            'tracking_number' => $tracking,
            'courier_id' => (int) $courier['id'],
        ]);
        if ((string) $courier['integration_type'] === 'API') {
            $recorded['message'] = 'API mode is not connected. The booking was stored manually.';
        }
        $this->repo->setShipmentStatus($shipmentId, 'BOOKED', [
            'courier_id' => (int) $courier['id'],
            'waybill_number' => $waybill === '' ? null : mb_substr($waybill, 0, 80),
            'tracking_number' => $tracking === '' ? null : mb_substr($tracking, 0, 80),
            'booked_at' => date('Y-m-d H:i:s'),
            'collection_date' => $this->date($input['collection_date'] ?? null),
            'expected_delivery' => $this->date($input['expected_delivery'] ?? null),
            'estimated_courier_cost' => $estimated,
            'actual_courier_cost' => $actual,
            'customer_delivery_charge' => $charge,
        ]);
        $this->emit('SHIPMENT_BOOKED', $shipmentId, $userId, ['courier' => (int) $courier['id']]);
        $this->audit->record('shipment', $shipmentId, 'COURIER_BOOKED', null, ['mode' => $recorded['mode']], $userId);

        return [];
    }

    public function trackingLink(int $shipmentId): ?string
    {
        $shipment = $this->repo->shipment($shipmentId);
        if ($shipment === null || $shipment['courier_id'] === null) {
            return null;
        }
        $courier = $this->repo->courier((int) $shipment['courier_id']);
        if ($courier === null) {
            return null;
        }

        return TrackingUrl::build(
            $courier['tracking_url_template'] !== null ? (string) $courier['tracking_url_template'] : null,
            (string) ($shipment['tracking_number'] ?? ''),
            (string) ($shipment['waybill_number'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, duplicate: bool, id: int|null}
     */
    public function recordEvent(int $shipmentId, array $input, int $userId): array
    {
        $status = strtoupper(trim((string) ($input['status'] ?? '')));
        if (!in_array($status, self::STATUSES, true)) {
            return ['errors' => ['status' => 'That tracking status is not valid.'], 'duplicate' => false, 'id' => null];
        }
        $source = strtoupper(trim((string) ($input['source'] ?? 'MANUAL')));
        if (!in_array($source, self::SOURCES, true)) {
            return ['errors' => ['source' => 'That tracking source is not valid.'], 'duplicate' => false, 'id' => null];
        }
        $external = trim((string) ($input['external_event_id'] ?? ''));
        if ($external !== '') {
            $existing = $this->repo->eventByExternal($shipmentId, $external);
            if ($existing !== null) {
                return ['errors' => [], 'duplicate' => true, 'id' => (int) $existing['id']];
            }
        }
        try {
            $id = Database::transaction(function () use ($shipmentId, $input, $status, $source, $external): int {
                $shipment = $this->repo->lockShipment($shipmentId);
                if ($shipment === null) {
                    throw new StockRejected(['_form' => 'That shipment was not found.']);
                }
                if ($external !== '' && $this->repo->eventByExternal($shipmentId, $external) !== null) {
                    throw new StockRejected(['duplicate' => '1']);
                }
                $id = $this->repo->insertEvent([
                    'shipment_id' => $shipmentId,
                    'status' => $status,
                    'event_time' => $this->when($input['event_time'] ?? null) ?? date('Y-m-d H:i:s'),
                    'location_text' => $this->blank($input['location_text'] ?? null),
                    'description' => mb_substr(trim((string) ($input['description'] ?? $status)), 0, 255),
                    'source' => $source,
                    'external_event_id' => $external === '' ? null : mb_substr($external, 0, 80),
                ]);
                $previous = (string) $shipment['status'];
                $collectionDone = $status === 'COLLECTED'
                    && (string) $shipment['shipment_type'] === 'CUSTOMER_COLLECTION'
                    && $previous !== 'COLLECTED';
                if ($status === 'DELIVERED' && $previous !== 'DELIVERED') {
                    foreach ($this->repo->items($shipmentId) as $item) {
                        $fulfilmentId = (int) ($item['fulfilment_requirement_id'] ?? 0);
                        if ($fulfilmentId > 0) {
                            $this->repo->addDelivered($fulfilmentId, (string) $item['quantity']);
                        }
                    }
                    $this->repo->setShipmentStatus($shipmentId, 'DELIVERED', ['actual_delivery' => date('Y-m-d H:i:s')]);
                } elseif ($collectionDone) {
                    foreach ($this->repo->items($shipmentId) as $item) {
                        $fulfilmentId = (int) ($item['fulfilment_requirement_id'] ?? 0);
                        if ($fulfilmentId > 0) {
                            $this->repo->addDelivered($fulfilmentId, (string) $item['quantity']);
                        }
                    }
                    $this->repo->setShipmentStatus($shipmentId, 'COLLECTED');
                } elseif ($status === 'DELIVERY_FAILED' && $previous !== 'DELIVERY_FAILED') {
                    $this->repo->setShipmentStatus($shipmentId, 'DELIVERY_FAILED');
                } elseif (!in_array($previous, ['DELIVERED', 'CANCELLED'], true)) {
                    $this->repo->setShipmentStatus($shipmentId, $status);
                }

                return $id;
            });
        } catch (StockRejected $e) {
            if (($e->errors['duplicate'] ?? '') === '1') {
                $existing = $external !== '' ? $this->repo->eventByExternal($shipmentId, $external) : null;

                return ['errors' => [], 'duplicate' => true, 'id' => $existing === null ? null : (int) $existing['id']];
            }

            return ['errors' => $e->errors, 'duplicate' => false, 'id' => null];
        } catch (PDOException $e) {
            if ($external !== '' && str_contains($e->getMessage(), 'uq_tracking_external')) {
                $existing = $this->repo->eventByExternal($shipmentId, $external);

                return ['errors' => [], 'duplicate' => true, 'id' => $existing === null ? null : (int) $existing['id']];
            }
            throw $e;
        }
        $eventName = match ($status) {
            'COLLECTED' => 'SHIPMENT_COLLECTED',
            'IN_TRANSIT', 'OUT_FOR_DELIVERY' => 'SHIPMENT_IN_TRANSIT',
            'DELIVERED' => 'SHIPMENT_DELIVERED',
            'DELIVERY_FAILED' => 'SHIPMENT_FAILED',
            default => null,
        };
        if ($eventName !== null) {
            $this->emit($eventName, $shipmentId, $userId, ['status' => strtolower($status)]);
        }

        return ['errors' => [], 'duplicate' => false, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{errors: array<string, string>, duplicate: bool}
     */
    public function webhook(string $providedSecret, array $payload, int $userId): array
    {
        $expected = (string) SettingsService::get('courier_webhook_secret', '');
        if ($expected === '' || !hash_equals($expected, $providedSecret)) {
            $this->audit->record('shipment', null, 'COURIER_WEBHOOK_REJECTED', null, [], $userId);

            return ['errors' => ['_form' => 'The courier webhook was not accepted.'], 'duplicate' => false];
        }
        $shipmentId = (int) ($payload['shipment_id'] ?? 0);
        $result = $this->recordEvent($shipmentId, [
            'status' => $payload['status'] ?? '',
            'source' => 'COURIER_API',
            'external_event_id' => $payload['external_event_id'] ?? '',
            'description' => $payload['description'] ?? 'Courier update',
            'event_time' => $payload['event_time'] ?? null,
            'location_text' => $payload['location_text'] ?? null,
        ], $userId);
        $this->audit->record('shipment', $shipmentId, 'COURIER_WEBHOOK', null, ['duplicate' => $result['duplicate'] ? 1 : 0], $userId);

        return ['errors' => $result['errors'], 'duplicate' => $result['duplicate']];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function failDelivery(int $shipmentId, array $input, int $userId): array
    {
        $reason = strtoupper(trim((string) ($input['reason'] ?? '')));
        if (!in_array($reason, self::FAIL_REASONS, true)) {
            return ['errors' => ['reason' => 'Choose why the delivery failed.'], 'id' => null];
        }
        $event = $this->recordEvent($shipmentId, [
            'status' => 'DELIVERY_FAILED',
            'source' => 'MANUAL',
            'description' => $reason,
            'external_event_id' => $input['external_event_id'] ?? null,
        ], $userId);
        if ($event['errors'] !== []) {
            return ['errors' => $event['errors'], 'id' => null];
        }
        $shipment = $this->repo->shipment($shipmentId);
        $id = $this->repo->insertException([
            'shipment_id' => $shipmentId,
            'job_id' => $shipment['job_id'] ?? null,
            'project_id' => $shipment['project_id'] ?? null,
            'project_site_id' => $shipment['project_site_id'] ?? null,
            'installation_id' => null,
            'contractor_work_order_id' => null,
            'exception_type' => 'DELIVERY_FAILED',
            'status' => 'OPEN',
            'description' => mb_substr($reason . ' ' . trim((string) ($input['description'] ?? '')), 0, 255),
            'photo_path' => null,
            'quantity' => null,
            'cause_text' => $reason,
            'disposition' => null,
            'created_by' => $userId,
        ]);
        $this->emit('LOGISTICS_EXCEPTION_CREATED', $id, $userId, ['type' => 'delivery_failed']);
        $this->notify($userId, 'Delivery failed', 'A delivery failed and fulfilment was not completed.', 'SHIPMENT', $shipmentId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function capturePod(int $shipmentId, array $input, int $userId): array
    {
        if (!can('logistics.delivery.manage') && !can('dispatch.complete')) {
            return ['errors' => ['_form' => 'You cannot capture proof of delivery.'], 'id' => null];
        }
        $name = trim((string) ($input['recipient_name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['recipient_name' => 'Enter the recipient name.'], 'id' => null];
        }
        if ($this->repo->pod($shipmentId) !== null) {
            return ['errors' => ['_form' => 'Proof of delivery is already stored.'], 'id' => null];
        }
        $id = $this->repo->insertPod([
            'shipment_id' => $shipmentId,
            'recipient_name' => mb_substr($name, 0, 120),
            'recipient_role' => $this->blank($input['recipient_role'] ?? null),
            'recipient_contact' => $this->blank($input['recipient_contact'] ?? null),
            'statement_version' => (string) SettingsService::get('pod_statement_version', 'POD-1'),
            'signature_id' => $this->positive($input['signature_id'] ?? 0),
            'photo_path' => $this->blank($input['photo_path'] ?? null),
            'notes' => $this->blank($input['notes'] ?? null),
            'latitude' => $this->coord($input['latitude'] ?? null),
            'longitude' => $this->coord($input['longitude'] ?? null),
            'device_signed_at' => $this->when($input['device_signed_at'] ?? null),
            'signed_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);
        $this->audit->record('shipment', $shipmentId, 'POD_CAPTURED', null, ['pod_id' => $id], $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * Customer-safe shipment. Costs, account numbers, and internal notes are omitted.
     *
     * @return array<string, mixed>|null
     */
    public function customerSafe(int $shipmentId, ?int $customerId = null): ?array
    {
        $shipment = $this->repo->shipment($shipmentId);
        if ($shipment === null) {
            return null;
        }
        if ($customerId !== null && (int) $shipment['customer_id'] !== $customerId) {
            return null;
        }
        $courier = $shipment['courier_id'] !== null ? $this->repo->courier((int) $shipment['courier_id']) : null;
        $pod = $this->repo->pod($shipmentId);

        return [
            'shipment_number' => (string) $shipment['shipment_number'],
            'status' => (string) $shipment['status'],
            'shipment_type' => (string) $shipment['shipment_type'],
            'courier_name' => $courier === null ? null : (string) $courier['name'],
            'tracking_number' => $shipment['tracking_number'],
            'tracking_url' => $this->trackingLink($shipmentId),
            'destination_address' => $shipment['destination_address'],
            'required_date' => $shipment['required_date'],
            'customer_note' => $shipment['customer_note'],
            'events' => $this->repo->events($shipmentId),
            'pod' => $pod === null ? null : [
                'recipient_name' => $pod['recipient_name'],
                'signed_at' => $pod['signed_at'],
                'statement_version' => $pod['statement_version'],
            ],
        ];
    }

    public function issueTrackingToken(int $shipmentId, int $userId): ?string
    {
        if (!can('logistics.shipment.manage') && !can('logistics.view')) {
            return null;
        }
        $raw = bin2hex(random_bytes(24));
        $days = max(1, (int) SettingsService::get('tracking_token_days', '30'));
        $this->repo->saveToken($shipmentId, hash('sha256', $raw), date('Y-m-d H:i:s', time() + ($days * 86400)), $userId);

        return $raw;
    }

    public function revokeTracking(int $shipmentId, int $userId): void
    {
        $this->repo->revokeTokens($shipmentId);
        $this->audit->record('shipment', $shipmentId, 'TRACKING_REVOKED', null, [], $userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publicTrack(string $token): ?array
    {
        $row = $this->repo->token(hash('sha256', $token));
        if ($row === null || $row['revoked_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $safe = $this->customerSafe((int) $row['shipment_id']);
        if ($safe === null) {
            return null;
        }
        unset($safe['destination_address']);

        return $safe;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createRun(array $input, int $userId): array
    {
        if (!can('logistics.delivery.manage')) {
            return ['errors' => ['_form' => 'You cannot plan a delivery.'], 'id' => null];
        }
        $date = $this->date($input['run_date'] ?? null);
        if ($date === null) {
            return ['errors' => ['run_date' => 'Choose the delivery date.'], 'id' => null];
        }
        $id = $this->repo->insertRun([
            'run_date' => $date,
            'vehicle_resource_id' => $this->positive($input['vehicle_resource_id'] ?? 0),
            'driver_user_id' => $this->positive($input['driver_user_id'] ?? 0),
            'team_id' => $this->positive($input['team_id'] ?? 0),
            'status' => 'PLANNED',
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function addStop(int $runId, int $shipmentId, int $sequence, int $userId): array
    {
        if (!can('logistics.delivery.manage')) {
            return ['_form' => 'You cannot plan a delivery.'];
        }
        if ($this->repo->deliveryRun($runId) === null || $this->repo->shipment($shipmentId) === null) {
            return ['_form' => 'That run or shipment was not found.'];
        }
        $this->repo->insertStop(['run_id' => $runId, 'shipment_id' => $shipmentId, 'sequence_no' => max(1, $sequence)]);
        $this->audit->record('delivery_run', $runId, 'STOP_ADDED', null, ['shipment_id' => $shipmentId], $userId);

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifest(int $runId): ?array
    {
        $run = $this->repo->deliveryRun($runId);
        if ($run === null) {
            return null;
        }
        $stops = [];
        foreach ($this->repo->stops($runId) as $stop) {
            $packages = $this->repo->packages((int) $stop['shipment_id']);
            $stops[] = [
                'sequence' => (int) $stop['sequence_no'],
                'status' => (string) $stop['status'],
                'shipment_number' => (string) $stop['shipment_number'],
                'customer' => $this->repo->customerName((int) $stop['customer_id']),
                'address' => (string) ($stop['destination_address'] ?? ''),
                'contact' => trim((string) ($stop['contact_name'] ?? '') . ' ' . (string) ($stop['contact_phone'] ?? '')),
                'instructions' => (string) ($stop['customer_note'] ?? ''),
                'packages' => array_map(static fn (array $package): string => (string) $package['package_code'], $packages),
            ];
        }

        return [
            'run_date' => (string) $run['run_date'],
            'vehicle_resource_id' => $run['vehicle_resource_id'],
            'driver_user_id' => $run['driver_user_id'],
            'team_id' => $run['team_id'],
            'stops' => $stops,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function updateStop(int $stopId, string $status, int $userId, string $reason = '', string $notes = ''): array
    {
        if (!can('logistics.delivery.manage')) {
            return ['_form' => 'You cannot update a delivery stop.'];
        }
        $status = strtoupper($status);
        if (!in_array($status, ['PLANNED', 'EN_ROUTE', 'ARRIVED', 'DELIVERED', 'FAILED', 'SKIPPED'], true)) {
            return ['status' => 'That stop status is not valid.'];
        }
        if ($status === 'FAILED' && !in_array(strtoupper($reason), self::FAIL_REASONS, true)) {
            return ['reason' => 'Choose why the stop failed.'];
        }
        $stop = $this->repo->stop($stopId);
        if ($stop === null) {
            return ['_form' => 'That stop was not found.'];
        }
        $this->repo->updateStop($stopId, $status, $reason === '' ? null : strtoupper($reason), $notes === '' ? null : mb_substr($notes, 0, 255));
        if ($status === 'EN_ROUTE') {
            $this->emit('DELIVERY_STARTED', (int) $stop['shipment_id'], $userId, []);
        }
        if ($status === 'DELIVERED') {
            $this->recordEvent((int) $stop['shipment_id'], [
                'status' => 'DELIVERED',
                'source' => 'INTERNAL',
                'description' => 'Own delivery completed',
            ], $userId);
            $this->emit('DELIVERY_COMPLETED', (int) $stop['shipment_id'], $userId, []);
        }
        if ($status === 'FAILED') {
            $this->failDelivery((int) $stop['shipment_id'], ['reason' => strtoupper($reason), 'description' => $notes], $userId);
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function notifyCollection(int $shipmentId, int $userId): array
    {
        if (!can('logistics.collection.manage')) {
            return ['_form' => 'You cannot update a collection.'];
        }
        $this->repo->setShipmentStatus($shipmentId, 'CUSTOMER_NOTIFIED');
        $this->emit('COLLECTION_READY', $shipmentId, $userId, []);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function handover(int $shipmentId, array $input, int $userId): array
    {
        if (!can('logistics.collection.manage')) {
            return ['errors' => ['_form' => 'You cannot complete a collection.'], 'id' => null];
        }
        $name = trim((string) ($input['collected_by'] ?? ''));
        if ($name === '') {
            return ['errors' => ['collected_by' => 'Enter who collected the goods.'], 'id' => null];
        }
        if ($this->repo->handover($shipmentId) !== null) {
            return ['errors' => ['_form' => 'This collection is already signed.'], 'id' => null];
        }
        $id = $this->repo->insertHandover([
            'shipment_id' => $shipmentId,
            'collected_by' => mb_substr($name, 0, 120),
            'contact_detail' => $this->blank($input['contact_detail'] ?? null),
            'vehicle_registration' => $this->blank($input['vehicle_registration'] ?? null),
            'signature_id' => $this->positive($input['signature_id'] ?? 0),
            'device_signed_at' => $this->when($input['device_signed_at'] ?? null),
            'collected_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);
        $this->recordEvent($shipmentId, ['status' => 'COLLECTED', 'source' => 'INTERNAL', 'description' => 'Collected by ' . $name], $userId);
        $this->emit('COLLECTION_COMPLETED', $shipmentId, $userId, []);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, package_id: int|null}
     */
    public function scanCollection(int $shipmentId, string $code, int $userId): array
    {
        return $this->verifyScan($shipmentId, $code, $userId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function openException(array $input, int $userId): array
    {
        if (!can('logistics.exceptions.manage')) {
            return ['errors' => ['_form' => 'You cannot record a logistics exception.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['exception_type'] ?? '')));
        if (!in_array($type, self::EXCEPTION_TYPES, true)) {
            return ['errors' => ['exception_type' => 'That exception type is not valid.'], 'id' => null];
        }
        $disposition = strtoupper(trim((string) ($input['disposition'] ?? '')));
        if ($disposition !== '' && !in_array($disposition, self::DISPOSITIONS, true)) {
            return ['errors' => ['disposition' => 'That disposition is not valid.'], 'id' => null];
        }
        $id = $this->repo->insertException([
            'shipment_id' => $this->positive($input['shipment_id'] ?? 0),
            'job_id' => $this->positive($input['job_id'] ?? 0),
            'project_id' => $this->positive($input['project_id'] ?? 0),
            'project_site_id' => $this->positive($input['project_site_id'] ?? 0),
            'installation_id' => $this->positive($input['installation_id'] ?? 0),
            'contractor_work_order_id' => $this->positive($input['contractor_work_order_id'] ?? 0),
            'exception_type' => $type,
            'status' => 'OPEN',
            'description' => mb_substr(trim((string) ($input['description'] ?? $type)), 0, 255),
            'photo_path' => $this->blank($input['photo_path'] ?? null),
            'quantity' => Decimal::isNumeric((string) ($input['quantity'] ?? '')) ? Decimal::round((string) $input['quantity'], 4) : null,
            'cause_text' => $this->blank($input['cause_text'] ?? null),
            'disposition' => $disposition === '' ? null : $disposition,
            'created_by' => $userId,
        ]);
        $this->emit('LOGISTICS_EXCEPTION_CREATED', $id, $userId, ['type' => strtolower($type)]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function postCourierCost(int $shipmentId, int $userId): array
    {
        if (!can('contractor.cost.approve') && !can('logistics.shipment.manage')) {
            return ['_form' => 'You cannot post a logistics cost.'];
        }
        $shipment = $this->repo->shipment($shipmentId);
        if ($shipment === null || $shipment['job_id'] === null) {
            return ['_form' => 'That shipment is not on a job.'];
        }
        if ($this->repo->costBySource('COURIER', $shipmentId) !== null) {
            return [];
        }
        $amount = Decimal::money((string) $shipment['actual_courier_cost']);
        $charge = Decimal::money((string) $shipment['customer_delivery_charge']);
        $reference = 'SHP-' . $shipment['shipment_number'] . '-COURIER';
        $ops = new OperationsRepository();
        $existing = $ops->otherCostByReference((int) $shipment['job_id'], $reference);
        $otherId = $existing['id'] ?? null;
        if ($existing === null && Decimal::cmp($amount, '0') > 0) {
            $otherId = $ops->insertOther([
                'job_id' => (int) $shipment['job_id'],
                'cost_type' => 'DELIVERY',
                'description' => 'Courier ' . $shipment['shipment_number'],
                'supplier_id' => null,
                'quantity' => '1.0000',
                'unit_cost' => $amount,
                'total_cost' => $amount,
                'reference' => $reference,
                'created_by' => $userId,
            ]);
            (new JobCostingService())->refresh((int) $shipment['job_id']);
        }
        $this->repo->insertCost([
            'job_id' => (int) $shipment['job_id'],
            'project_id' => $shipment['project_id'],
            'shipment_id' => $shipmentId,
            'source_type' => 'COURIER',
            'source_id' => $shipmentId,
            'amount' => $amount,
            'customer_charge' => $charge,
            'description' => 'Courier cost',
            'other_cost_id' => $otherId,
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function recordTravel(array $input, int $userId): array
    {
        $start = $input['start_odometer'] ?? null;
        $end = $input['end_odometer'] ?? null;
        $distance = $input['distance_km'] ?? null;
        if ($start !== null && $start !== '' && $end !== null && $end !== '') {
            if (!Decimal::isNumeric((string) $start) || !Decimal::isNumeric((string) $end) || Decimal::cmp((string) $end, (string) $start) < 0) {
                return ['end_odometer' => 'The end reading cannot be below the start reading.'];
            }
            $distance = Decimal::sub((string) $end, (string) $start, 2);
        }
        if ($distance === null || !Decimal::isNumeric((string) $distance) || Decimal::cmp((string) $distance, '0') < 0) {
            return ['distance_km' => 'Enter a distance or both odometer readings.'];
        }
        $rate = Decimal::money((string) SettingsService::get('travel_cost_per_km', '0'));
        $internal = Decimal::mul((string) $distance, $rate, 2);
        $charge = $this->money($input['customer_charge'] ?? '0') ?? '0.00';
        $this->repo->insertTravel([
            'entity_type' => mb_substr(strtoupper((string) ($input['entity_type'] ?? 'SHIPMENT')), 0, 40),
            'entity_id' => (int) ($input['entity_id'] ?? 0),
            'start_odometer' => $start === null || $start === '' ? null : (string) $start,
            'end_odometer' => $end === null || $end === '' ? null : (string) $end,
            'distance_km' => Decimal::round((string) $distance, 2),
            'cost_per_km' => $rate,
            'internal_cost' => $internal,
            'customer_charge' => $charge,
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function captureExpense(array $input, int $userId): array
    {
        $type = strtoupper(trim((string) ($input['expense_type'] ?? '')));
        if (!in_array($type, ['TOLL', 'PARKING', 'MATERIALS', 'OTHER'], true)) {
            return ['errors' => ['expense_type' => 'Choose the expense type.'], 'id' => null];
        }
        $amount = $this->money($input['amount'] ?? '');
        if ($amount === null) {
            return ['errors' => ['amount' => 'Enter the amount.'], 'id' => null];
        }
        $id = $this->repo->insertExpense([
            'entity_type' => mb_substr(strtoupper((string) ($input['entity_type'] ?? 'JOB')), 0, 40),
            'entity_id' => (int) ($input['entity_id'] ?? 0),
            'expense_type' => $type,
            'amount' => $amount,
            'receipt_path' => $this->blank($input['receipt_path'] ?? null),
            'note' => $this->blank($input['note'] ?? null),
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function label(int $packageId): array
    {
        $package = $this->repo->package($packageId);
        if ($package === null) {
            return [];
        }
        $shipment = $package['shipment_id'] !== null ? $this->repo->shipment((int) $package['shipment_id']) : null;
        $customer = $shipment === null ? '' : $this->repo->customerName((int) $shipment['customer_id']);

        return [
            'package_code' => (string) $package['package_code'],
            'shipment_number' => $shipment['shipment_number'] ?? '',
            'customer' => $customer,
            'destination' => (string) ($shipment['destination_address'] ?? ''),
            'sequence' => (int) $package['sequence_no'] . ' of ' . (int) $package['sequence_total'],
            'handling' => (string) ($package['special_handling'] ?? ''),
            'fragile' => (int) $package['fragile'] === 1,
            'barcode' => Code39::svg((string) $package['package_code']),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function profitability(int $shipmentId): array
    {
        $shipment = $this->repo->shipment($shipmentId);
        if ($shipment === null) {
            return [];
        }
        $charge = Decimal::money((string) $shipment['customer_delivery_charge']);
        $actual = Decimal::money((string) $shipment['actual_courier_cost']);

        return [
            'customer_delivery_charge' => $charge,
            'actual_courier_cost' => $actual,
            'estimated_courier_cost' => Decimal::money((string) $shipment['estimated_courier_cost']),
            'variance' => Decimal::sub($charge, $actual, 2),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function projectRollup(int $projectId): array
    {
        return $this->repo->projectRollup($projectId);
    }

    /**
     * @return array<string, int>
     */
    public function dashboard(): array
    {
        return $this->repo->dashboardCounts(date('Y-m-d'));
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function page(array $filters, int $limit, int $offset): array
    {
        return $this->repo->shipmentsPage($filters, max(1, min(100, $limit)), max(0, $offset));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function courierPerformance(): array
    {
        return $this->repo->courierPerformance();
    }

    /**
     * @param array<string, scalar|null> $summary
     */
    private function emit(string $event, int $id, int $userId, array $summary): void
    {
        BusinessEventDispatcher::emit($event, 'SHIPMENT', $id, $userId, $summary);
    }

    private function notify(int $userId, string $title, string $message, string $entity, int $entityId): void
    {
        (new NotificationService())->send($userId, null, 'SYSTEM', $title, $message, $entity, $entityId, 'NORMAL', $entity . ':' . $entityId . ':' . $title);
    }

    private function positive(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function date(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $parsed = strtotime($text);

        return $parsed === false ? null : date('Y-m-d', $parsed);
    }

    private function when(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $parsed = strtotime($text);

        return $parsed === false ? null : date('Y-m-d H:i:s', $parsed);
    }

    private function money(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            $text = '0';
        }
        if (!Decimal::isNumeric($text) || Decimal::cmp($text, '0') < 0) {
            return null;
        }

        return Decimal::money($text);
    }

    private function coord(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        return $text;
    }
}
