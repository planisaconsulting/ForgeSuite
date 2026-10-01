<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\WorkshopCodes;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\JobRepository;
use App\Repositories\WorkshopRepository;

/**
 * Packs and dispatches existing production items. A second scan does not
 * add the quantity again, and an item from another job is refused.
 */
final class DispatchService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly QualityCheckService $quality = new QualityCheckService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(int $jobId, array $input, int $userId): array
    {
        if (!can('dispatch.create')) {
            return ['errors' => ['_form' => 'You cannot create a dispatch.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['dispatch_type'] ?? '')));
        if (!in_array($type, WorkshopCodes::dispatchTypes(), true)) {
            return ['errors' => ['dispatch_type' => 'Choose how this job is leaving.'], 'id' => null];
        }
        $job = (new JobRepository())->find($jobId);
        if ($job === null) {
            return ['errors' => ['_form' => 'That job was not found.'], 'id' => null];
        }
        $id = Database::transaction(function () use ($job, $type, $input, $userId): int {
            $number = $this->numbers->dispatch();
            $id = $this->workshop->insertDispatch([
                'dispatch_number' => $number,
                'job_id' => (int) $job['id'],
                'dispatch_type' => $type,
                'customer_id' => (int) $job['customer_id'],
                'scheduled_at' => blank_to_null($input['scheduled_at'] ?? null),
                'vehicle_resource_id' => ((int) ($input['vehicle_resource_id'] ?? 0)) > 0 ? (int) $input['vehicle_resource_id'] : null,
                'driver_user_id' => ((int) ($input['driver_user_id'] ?? 0)) > 0 ? (int) $input['driver_user_id'] : null,
                'status' => WorkshopCodes::DISPATCH_PACKING,
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
            foreach ($this->workshop->productionItems((int) $job['id']) as $piece) {
                if (!in_array((string) $piece['status'], [WorkshopCodes::ITEM_READY, WorkshopCodes::ITEM_COMPLETE, WorkshopCodes::ITEM_QC], true)) {
                    continue;
                }
                $this->workshop->insertDispatchItem([
                    'dispatch_id' => $id,
                    'job_item_id' => (int) $piece['job_item_id'],
                    'production_item_id' => (int) $piece['id'],
                    'description' => (string) $piece['description'],
                    'quantity' => (string) $piece['quantity'],
                    'status' => 'EXPECTED',
                ]);
            }
            $this->tracking->ensure('DISPATCH', $id, $number, $userId);
            $this->audit->record('dispatch', $id, 'DISPATCH_CREATED', null, ['job_id' => (int) $job['id'], 'number' => $number], $userId);

            return $id;
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, duplicate: bool}
     */
    public function scan(int $dispatchId, string $codeOrToken, int $userId): array
    {
        if (!can('dispatch.create') && !can('workshop.scan')) {
            return ['errors' => ['_form' => 'You cannot scan a dispatch.'], 'duplicate' => false];
        }
        $package = $this->workshop->packageByCode(strtoupper(trim($codeOrToken)));
        if ($package !== null) {
            return $this->scanPackage($dispatchId, (int) $package['id'], $userId);
        }
        $piece = $this->piece($codeOrToken);
        if ($piece === null) {
            return ['errors' => ['code' => 'That production item was not found.'], 'duplicate' => false];
        }
        try {
            $duplicate = false;
            Database::transaction(function () use ($dispatchId, $piece, $userId, &$duplicate): void {
                $dispatch = $this->workshop->lockDispatch($dispatchId);
                if ($dispatch === null) {
                    throw new StockRejected(['_form' => 'That dispatch was not found.']);
                }
                if ((int) $piece['job_id'] !== (int) $dispatch['job_id']) {
                    throw new StockRejected(['_form' => 'WRONG JOB. That item does not belong on this dispatch.']);
                }
                if ($this->quality->blocksDispatch((int) $dispatch['job_id'])) {
                    throw new StockRejected(['_form' => 'Quality control failed. Dispatch needs a rework, reprint, or an authorised override.']);
                }
                $existing = $this->workshop->dispatchItemForProduction($dispatchId, (int) $piece['id']);
                if ($existing !== null && (string) $existing['status'] === 'LOADED') {
                    $duplicate = true;

                    return;
                }
                if ($existing === null) {
                    $this->workshop->insertDispatchItem([
                        'dispatch_id' => $dispatchId,
                        'job_item_id' => (int) $piece['job_item_id'],
                        'production_item_id' => (int) $piece['id'],
                        'description' => (string) $piece['description'],
                        'quantity' => (string) $piece['quantity'],
                        'status' => 'LOADED',
                    ]);
                } else {
                    $this->workshop->markDispatchItem((int) $existing['id'], 'LOADED');
                }
                $this->refreshStatus($dispatchId);
                $this->tracking->recordScan([
                    'entity_type' => 'PRODUCTION_ITEM',
                    'entity_id' => (int) $piece['id'],
                    'tracking_code' => (string) $piece['tracking_code'],
                    'token_id' => null,
                ], 'DISPATCHED', $userId, null, ['dispatch_id' => $dispatchId]);
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'duplicate' => false];
        }

        return ['errors' => [], 'duplicate' => $duplicate];
    }

    /**
     * @return array{expected: int, scanned: int, missing: int, complete: bool}
     */
    public function progress(int $dispatchId): array
    {
        $items = $this->workshop->dispatchItems($dispatchId);
        $expected = count($items);
        $scanned = 0;
        foreach ($items as $item) {
            if ((string) $item['status'] === 'LOADED') {
                $scanned++;
            }
        }

        return [
            'expected' => $expected,
            'scanned' => $scanned,
            'missing' => max(0, $expected - $scanned),
            'complete' => $expected > 0 && $scanned === $expected,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function markDispatched(int $dispatchId, int $userId, string $override = ''): array
    {
        if (!can('dispatch.complete')) {
            return ['_form' => 'You cannot complete a dispatch.'];
        }
        $progress = $this->progress($dispatchId);
        if (!$progress['complete'] && trim($override) === '') {
            return ['_form' => 'INCOMPLETE DISPATCH. ' . $progress['missing'] . ' item(s) are not scanned.'];
        }
        $row = $this->workshop->dispatch($dispatchId);
        if ($row === null) {
            return ['_form' => 'That dispatch was not found.'];
        }
        if ($this->quality->blocksDispatch((int) $row['job_id'])) {
            return ['_form' => 'Quality control failed. Dispatch needs a rework, reprint, or an authorised override.'];
        }
        $this->workshop->updateDispatch($dispatchId, [
            'status' => WorkshopCodes::DISPATCH_DISPATCHED,
            'dispatched_at' => date('Y-m-d H:i:s'),
            'notes' => $row['notes'],
        ]);
        $this->audit->record('dispatch', $dispatchId, 'DISPATCHED', null, ['override' => $override], $userId);
        (new AutomationService())->fire('DISPATCHED', 'dispatch', $dispatchId, $userId);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, duplicate: bool}
     */
    private function scanPackage(int $dispatchId, int $packageId, int $userId): array
    {
        $items = $this->workshop->packageItems($packageId);
        if ($items === []) {
            return ['errors' => ['_form' => 'That package is empty.'], 'duplicate' => false];
        }
        $loaded = 0;
        $duplicates = 0;
        foreach ($items as $item) {
            $piece = $this->workshop->productionItem((int) $item['production_item_id']);
            if ($piece === null) {
                continue;
            }
            $result = $this->scan($dispatchId, (string) $piece['tracking_code'], $userId);
            if ($result['errors'] !== []) {
                return $result;
            }
            if ($result['duplicate']) {
                $duplicates++;
            } else {
                $loaded++;
            }
        }
        if ($loaded === 0 && $duplicates > 0) {
            return ['errors' => [], 'duplicate' => true];
        }

        return ['errors' => [], 'duplicate' => false];
    }

    private function refreshStatus(int $dispatchId): void
    {
        $progress = $this->progress($dispatchId);
        $row = $this->workshop->dispatch($dispatchId);
        if ($row === null) {
            return;
        }
        $status = $progress['complete'] ? WorkshopCodes::DISPATCH_READY : WorkshopCodes::DISPATCH_INCOMPLETE;
        $this->workshop->updateDispatch($dispatchId, [
            'status' => $status,
            'dispatched_at' => $row['dispatched_at'],
            'notes' => $row['notes'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function piece(string $codeOrToken): ?array
    {
        $resolved = $this->tracking->resolve(trim($codeOrToken));
        if ($resolved !== null && $resolved['entity_type'] === 'PRODUCTION_ITEM') {
            return $this->workshop->productionItem((int) $resolved['entity_id']);
        }
        if (preg_match('/^[a-f0-9]{64}$/i', trim($codeOrToken))) {
            return null;
        }
        $code = $this->tracking->findByCode($codeOrToken);
        if ($code !== null && (string) $code['entity_type'] === 'PRODUCTION_ITEM') {
            return $this->workshop->productionItem((int) $code['entity_id']);
        }

        return $this->workshop->productionItemByCode(trim($codeOrToken));
    }
}
