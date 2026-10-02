<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ProductionControlRepository;

/**
 * Fulfilment is per job item. Produced quantity cannot be exceeded.
 * A job is not complete while a required fulfilment is still open.
 */
final class FulfilmentService
{
    /** @var list<string> */
    public const TYPES = ['INSTALLATION', 'DELIVERY', 'COLLECTION', 'COURIER', 'DIGITAL', 'NO_FULFILMENT', 'OTHER'];

    public function __construct(private readonly ProductionControlRepository $repo = new ProductionControlRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(int $jobId, array $input, int $userId): array
    {
        if (!can('fulfilment.manage')) {
            return ['errors' => ['_form' => 'You cannot manage fulfilment.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['fulfilment_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            return ['errors' => ['fulfilment_type' => 'That fulfilment type is not valid.'], 'id' => null];
        }
        $qty = (string) ($input['quantity'] ?? '0');
        if (!Decimal::isNumeric($qty) || Decimal::cmp($qty, '0') <= 0) {
            return ['errors' => ['quantity' => 'Quantity must be greater than zero.'], 'id' => null];
        }
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0) {
            $item = $this->repo->item($itemId);
            if ($item === null || (int) $item['job_id'] !== $jobId) {
                return ['errors' => ['job_item_id' => 'That item is not on this job.'], 'id' => null];
            }
            if (Decimal::cmp($qty, (string) $item['quantity']) > 0) {
                return ['errors' => ['quantity' => 'Fulfilment quantity is above the job item.'], 'id' => null];
            }
        }
        $id = $this->repo->insertFulfilment([
            'job_id' => $jobId,
            'job_item_id' => $itemId > 0 ? $itemId : null,
            'fulfilment_type' => $type,
            'quantity' => Decimal::round($qty, 4),
            'project_site_id' => (int) ($input['project_site_id'] ?? 0) > 0 ? (int) $input['project_site_id'] : null,
            'address' => blank_to_null($input['address'] ?? null),
            'contact_name' => blank_to_null($input['contact_name'] ?? null),
            'required_date' => blank_to_null($input['required_date'] ?? null),
            'status' => 'NOT_READY',
            'packing_status' => strtoupper((string) ($input['packing_status'] ?? 'NOT_REQUIRED')),
            'instructions' => blank_to_null($input['instructions'] ?? null),
        ]);
        $this->audit($id, 'FULFILMENT_CREATED', $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function fulfil(int $id, string $quantity, int $userId, ?string $collectedBy = null): array
    {
        if (!can('fulfilment.manage')) {
            return ['_form' => 'You cannot manage fulfilment.'];
        }
        $row = $this->repo->fulfilment($id);
        if ($row === null) {
            return ['_form' => 'That fulfilment was not found.'];
        }
        if ($this->repo->openQcFail((int) $row['job_id']) > 0) {
            return ['_form' => 'QC has failed. Fulfilment waits for rework or an authorised disposition.'];
        }
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            return ['quantity' => 'Quantity must be greater than zero.'];
        }
        $next = Decimal::add((string) $row['fulfilled_quantity'], $quantity, 4);
        if (Decimal::cmp($next, (string) $row['quantity']) > 0) {
            return ['_form' => 'That is more than this fulfilment line.'];
        }
        $itemId = (int) ($row['job_item_id'] ?? 0);
        if ($itemId > 0) {
            $item = $this->repo->item($itemId);
            $good = (string) ($item['good_quantity'] ?? '0');
            $already = '0';
            foreach ($this->repo->fulfilments((int) $row['job_id']) as $other) {
                if ((int) ($other['job_item_id'] ?? 0) === $itemId) {
                    $already = Decimal::add($already, (string) $other['fulfilled_quantity'], 4);
                }
            }
            $allocated = Decimal::add($already, $quantity, 4);
            if (Decimal::cmp($allocated, $good) > 0) {
                return ['_form' => 'Fulfilled quantity cannot exceed the good completed quantity.'];
            }
        }
        $status = Decimal::cmp($next, (string) $row['quantity']) >= 0 ? 'FULFILLED' : 'PARTIALLY_FULFILLED';
        $collectedAt = $collectedBy !== null && $collectedBy !== '' ? date('Y-m-d H:i:s') : null;
        $this->repo->updateFulfilment($id, Decimal::round($next, 4), $status, $collectedBy, $collectedAt);
        BusinessEventDispatcher::emit(
            $status === 'FULFILLED' ? 'FULFILMENT_COMPLETED' : 'FULFILMENT_READY',
            'FULFILMENT',
            $id,
            $userId,
            ['status' => strtolower($status)]
        );

        return [];
    }

    public function jobOpen(int $jobId): bool
    {
        return $this->repo->openFulfilmentForJob($jobId) > 0;
    }

    private function audit(int $id, string $action, int $userId): void
    {
        (new AuditService())->record('fulfilment', $id, $action, null, [], $userId);
    }
}
