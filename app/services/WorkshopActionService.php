<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\JobItemStatus;
use App\Domain\WorkshopCodes;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\OperationsRepository;
use App\Repositories\WorkshopRepository;

/**
 * Start, pause, block, complete, and reprint against existing production stages.
 */
final class WorkshopActionService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly ProductionItemService $pieces = new ProductionItemService()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function start(int $productionItemId, int $userId, string $key = ''): array
    {
        if (!can('production.start') && !can('production.update')) {
            return ['_form' => 'You cannot start production.'];
        }

        return $this->transition($productionItemId, 'STARTED', $userId, $key, function (array $item) use ($userId): array {
            $jobId = (int) $item['job_id'];
            if (!$this->ops->artworkApproved($jobId) && !can('artwork.override_approval')) {
                return ['_form' => 'Artwork has not been approved by the customer.'];
            }
            $stageId = (int) ($item['current_stage_id'] ?? 0);
            if ($stageId > 0) {
                $stage = $this->workshop->stage($stageId);
                if ($stage !== null && (string) $stage['status'] === 'COMPLETE') {
                    return ['_form' => 'That stage is already complete.'];
                }
                $stages = $this->workshop->stages($jobId, (int) $item['job_item_id']);
                $seen = false;
                foreach ($stages as $row) {
                    if ((int) $row['id'] === $stageId) {
                        $seen = true;
                        break;
                    }
                    if ((string) $row['status'] !== 'COMPLETE') {
                        return ['_form' => 'An earlier stage is still open.'];
                    }
                }
                if ($seen && $stage !== null) {
                    $this->workshop->updateStage($stageId, [
                        'status' => 'IN_PROGRESS',
                        'started_at' => $stage['started_at'] ?? date('Y-m-d H:i:s'),
                        'completed_at' => null,
                        'notes' => $stage['notes'],
                        'actual_minutes' => $stage['actual_minutes'],
                    ]);
                }
            }
            $this->workshop->updateProductionItem((int) $item['id'], [
                'status' => WorkshopCodes::ITEM_IN_PROGRESS,
                'quantity_completed' => (string) $item['quantity_completed'],
                'current_stage_id' => $item['current_stage_id'],
                'assigned_resource_id' => $item['assigned_resource_id'],
            ]);
            $this->workshop->setItemStatus((int) $item['job_item_id'], JobItemStatus::InProduction->value);
            $this->workshop->noteSchedule($jobId, 'Production started');
            $this->audit->record('job', $jobId, 'PRODUCTION_STARTED', null, ['production_item_id' => (int) $item['id']], $userId);
            (new AutomationService())->fire('PRODUCTION_STARTED', 'job', $jobId, $userId);
            (new WorkshopNotifier())->send('PRODUCTION_STARTED', 'Production started', 'A production item was started.', 'job', $jobId);

            return [];
        });
    }

    /**
     * @return array<string, string>
     */
    public function pause(int $productionItemId, string $reason, int $userId, string $key = ''): array
    {
        if (!can('production.pause') && !can('production.update')) {
            return ['_form' => 'You cannot pause production.'];
        }
        $reason = strtoupper(trim($reason));
        if (!in_array($reason, WorkshopCodes::pauseReasons(), true)) {
            return ['reason' => 'Choose a pause reason.'];
        }

        return $this->transition($productionItemId, 'PAUSED', $userId, $key, function (array $item) use ($reason): array {
            $this->workshop->updateProductionItem((int) $item['id'], [
                'status' => WorkshopCodes::ITEM_PAUSED,
                'quantity_completed' => (string) $item['quantity_completed'],
                'current_stage_id' => $item['current_stage_id'],
                'assigned_resource_id' => $item['assigned_resource_id'],
            ]);
            $this->closeOpenTime((int) $item['job_id'], (int) ($item['current_stage_id'] ?? 0), $reason);

            return [];
        }, $reason);
    }

    /**
     * @return array<string, string>
     */
    public function block(int $productionItemId, string $reason, int $userId, string $key = ''): array
    {
        if (!can('production.pause') && !can('production.update')) {
            return ['_form' => 'You cannot block production.'];
        }
        $reason = strtoupper(trim($reason));
        if (!in_array($reason, WorkshopCodes::blockReasons(), true)) {
            return ['reason' => 'A block needs a reason.'];
        }

        return $this->transition($productionItemId, 'BLOCKED', $userId, $key, function (array $item) use ($reason, $userId): array {
            $stageId = (int) ($item['current_stage_id'] ?? 0);
            if ($stageId > 0) {
                $stage = $this->workshop->stage($stageId);
                if ($stage !== null) {
                    $this->workshop->updateStage($stageId, [
                        'status' => 'BLOCKED',
                        'started_at' => $stage['started_at'],
                        'completed_at' => null,
                        'notes' => $reason,
                        'actual_minutes' => $stage['actual_minutes'],
                    ]);
                }
            }
            $this->workshop->updateProductionItem((int) $item['id'], [
                'status' => WorkshopCodes::ITEM_BLOCKED,
                'quantity_completed' => (string) $item['quantity_completed'],
                'current_stage_id' => $item['current_stage_id'],
                'assigned_resource_id' => $item['assigned_resource_id'],
            ]);
            $this->audit->record('job', (int) $item['job_id'], 'PRODUCTION_BLOCKED', null, [
                'production_item_id' => (int) $item['id'],
                'reason' => $reason,
            ], $userId);
            (new WorkshopNotifier())->send('PRODUCTION_BLOCKED', 'Production blocked', $reason, 'job', (int) $item['job_id']);

            return [];
        }, $reason);
    }

    /**
     * @return array<string, string>
     */
    public function complete(int $productionItemId, string $quantity, int $userId, string $key = '', string $notes = ''): array
    {
        if (!can('production.complete') && !can('production.update')) {
            return ['_form' => 'You cannot complete production.'];
        }
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') < 0) {
            return ['quantity' => 'Enter the quantity completed.'];
        }

        return $this->transition($productionItemId, 'COMPLETED', $userId, $key, function (array $item) use ($quantity, $userId, $notes): array {
            $done = Decimal::round($quantity, 4);
            if (Decimal::cmp($done, (string) $item['quantity']) > 0) {
                return ['quantity' => 'That is more than the quantity on this piece.'];
            }
            $status = Decimal::cmp($done, (string) $item['quantity']) < 0
                ? WorkshopCodes::ITEM_PARTIAL
                : WorkshopCodes::ITEM_READY;
            $stageId = (int) ($item['current_stage_id'] ?? 0);
            $next = $stageId;
            if ($status === WorkshopCodes::ITEM_READY && $stageId > 0) {
                $stage = $this->workshop->stage($stageId);
                if ($stage !== null) {
                    $this->workshop->updateStage($stageId, [
                        'status' => 'COMPLETE',
                        'started_at' => $stage['started_at'] ?? date('Y-m-d H:i:s'),
                        'completed_at' => date('Y-m-d H:i:s'),
                        'notes' => $notes !== '' ? $notes : $stage['notes'],
                        'actual_minutes' => $stage['actual_minutes'],
                    ]);
                }
                $stages = $this->workshop->stages((int) $item['job_id'], (int) $item['job_item_id']);
                $passed = false;
                foreach ($stages as $row) {
                    if ($passed && (string) $row['status'] !== 'COMPLETE') {
                        $next = (int) $row['id'];
                        $status = WorkshopCodes::ITEM_WAITING;
                        break;
                    }
                    if ((int) $row['id'] === $stageId) {
                        $passed = true;
                    }
                }
            }
            $this->workshop->updateProductionItem((int) $item['id'], [
                'status' => $status,
                'quantity_completed' => $done,
                'current_stage_id' => $next > 0 ? $next : null,
                'assigned_resource_id' => $item['assigned_resource_id'],
            ]);
            $this->pieces->syncJobItem((int) $item['job_item_id']);
            $this->audit->record('job', (int) $item['job_id'], 'PRODUCTION_COMPLETED', null, [
                'production_item_id' => (int) $item['id'],
                'quantity_completed' => $done,
                'status' => $status,
            ], $userId);
            if ($status === WorkshopCodes::ITEM_READY) {
                (new AutomationService())->fire('PRODUCTION_COMPLETED', 'job', (int) $item['job_id'], $userId);
                (new WorkshopNotifier())->send('DISPATCH_READY', 'Ready for dispatch', 'A production item is ready.', 'job', (int) $item['job_id']);
            }

            return [];
        }, null, $quantity, $notes);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function reprint(int $productionItemId, array $input, int $userId): array
    {
        if (!can('production.reprint')) {
            return ['_form' => 'You cannot record a reprint.'];
        }
        $reason = strtoupper(trim((string) ($input['reason_code'] ?? '')));
        $known = array_column($this->workshop->reprintReasons(), 'code');
        if (!in_array($reason, $known, true)) {
            return ['reason_code' => 'Choose a reprint reason.'];
        }
        $qty = trim((string) ($input['quantity'] ?? ''));
        if (!Decimal::isNumeric($qty) || Decimal::cmp($qty, '0') <= 0) {
            return ['quantity' => 'Enter the reprinted quantity.'];
        }
        $item = $this->workshop->productionItem($productionItemId);
        if ($item === null) {
            return ['_form' => 'That production item was not found.'];
        }
        Database::transaction(function () use ($item, $input, $userId, $reason, $qty): void {
            $material = null;
            $materialQty = trim((string) ($input['material_quantity'] ?? ''));
            if ($materialQty !== '' && Decimal::cmp($materialQty, '0') > 0 && (int) ($input['product_id'] ?? 0) > 0) {
                $recorded = (new MaterialUsageService(stock: new JobStockHook()))->record((int) $item['job_id'], [
                    'usage_type' => 'REWORK',
                    'product_id' => (int) $input['product_id'],
                    'quantity' => $materialQty,
                    'job_item_id' => (int) $item['job_item_id'],
                    'inventory_item_id' => (int) ($input['inventory_item_id'] ?? 0),
                    'reason' => 'REWORK',
                    'notes' => 'Reprint ' . $reason,
                ], $userId);
                if ($recorded['errors'] !== []) {
                    throw new StockRejected($recorded['errors']);
                }
                $material = $materialQty;
            }
            $this->workshop->insertReprint([
                'job_id' => (int) $item['job_id'],
                'job_item_id' => (int) $item['job_item_id'],
                'production_item_id' => (int) $item['id'],
                'stage_id' => $item['current_stage_id'],
                'quantity' => Decimal::round($qty, 4),
                'reason_code' => $reason,
                'material_quantity' => $material,
                'labour_minutes' => ((int) ($input['labour_minutes'] ?? 0)) > 0 ? (int) $input['labour_minutes'] : null,
                'chargeable' => !empty($input['chargeable']) ? 1 : 0,
                'variation_id' => ((int) ($input['variation_id'] ?? 0)) > 0 ? (int) $input['variation_id'] : null,
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
            $this->workshop->updateProductionItem((int) $item['id'], [
                'status' => WorkshopCodes::ITEM_WAITING,
                'quantity_completed' => '0.0000',
                'current_stage_id' => $item['current_stage_id'],
                'assigned_resource_id' => $item['assigned_resource_id'],
            ]);
            $this->audit->record('job', (int) $item['job_id'], 'REPRINT_RECORDED', null, [
                'production_item_id' => (int) $item['id'],
                'quantity' => Decimal::round($qty, 4),
                'reason_code' => $reason,
            ], $userId);
            (new WorkshopNotifier())->send('REPRINT_REQUIRED', 'Reprint recorded', $reason, 'job', (int) $item['job_id']);
        });

        return [];
    }

    /**
     * @param callable(array<string, mixed>): array<string, string> $apply
     * @return array<string, string>
     */
    private function transition(int $id, string $action, int $userId, string $key, callable $apply, ?string $reason = null, ?string $quantity = null, string $notes = ''): array
    {
        if ($key !== '') {
            $prior = $this->workshop->eventByKey($key);
            if ($prior !== null) {
                return [];
            }
        }
        try {
            return Database::transaction(function () use ($id, $action, $userId, $key, $apply, $reason, $quantity, $notes): array {
                if ($key !== '' && $this->workshop->eventByKey($key) !== null) {
                    return [];
                }
                $item = $this->workshop->lockProductionItem($id);
                if ($item === null) {
                    return ['_form' => 'That production item was not found.'];
                }
                if ((string) $item['status'] === WorkshopCodes::ITEM_MISSING) {
                    return ['_form' => 'This item is missing and needs an authorised resolution.'];
                }
                $errors = $apply($item);
                if ($errors !== []) {
                    throw new StockRejected($errors);
                }
                $this->workshop->insertEvent([
                    'production_item_id' => $id,
                    'job_production_stage_id' => $item['current_stage_id'],
                    'action' => $action,
                    'reason' => $reason,
                    'quantity' => $quantity,
                    'notes' => $notes !== '' ? $notes : null,
                    'idempotency_key' => $key !== '' ? $key : null,
                    'user_id' => $userId,
                    'client_at' => null,
                ]);
                $this->tracking->recordScan([
                    'entity_type' => 'PRODUCTION_ITEM',
                    'entity_id' => $id,
                    'tracking_code' => (string) $item['tracking_code'],
                    'token_id' => null,
                ], $action, $userId);

                return [];
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }
    }

    private function closeOpenTime(int $jobId, int $stageId, string $reason): void
    {
        if (SettingsService::get('workshop_time_tracking', '0') !== '1') {
            return;
        }
        $this->workshop->noteSchedule($jobId, 'Paused: ' . $reason);
        unset($stageId);
    }
}
