<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ProductionControlRepository;

/**
 * Stage actions apply only to a current release.
 * A repeated idempotency key does not start or complete twice.
 */
final class ProductionStageControlService
{
    /** @var list<string> */
    public const PAUSE = ['BREAK', 'WAITING_MATERIAL', 'WAITING_ARTWORK', 'MACHINE', 'CUSTOMER', 'QUALITY', 'OTHER'];

    /** @var list<string> */
    public const BLOCKS = ['MATERIAL_SHORTAGE', 'ARTWORK_QUERY', 'MACHINE_BREAKDOWN', 'QUALITY_ISSUE', 'CUSTOMER_CHANGE', 'TECHNICAL_QUERY', 'RESOURCE_SHORTAGE', 'OTHER'];

    /** @var list<string> */
    public const REWORK = ['ARTWORK_ERROR', 'PRINT_DEFECT', 'MATERIAL_DEFECT', 'MACHINE_ERROR', 'PRODUCTION_ERROR', 'CUSTOMER_CHANGE', 'INSTALLATION_DAMAGE', 'OTHER'];

    public function __construct(
        private readonly ProductionControlRepository $repo = new ProductionControlRepository(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function start(int $stageId, int $userId, ?string $key = null): array
    {
        if (!can('production.stage.start') && !can('production.supervise')) {
            return ['_form' => 'You cannot start a production stage.'];
        }

        return $this->act($stageId, $userId, 'START', $key, function (array $stage, array $release) use ($userId): array {
            if ((string) $stage['status'] === 'IN_PROGRESS') {
                return [];
            }
            $previous = $this->previousOpen($stage);
            if ($previous !== null) {
                return ['_form' => 'The previous stage is not complete.'];
            }
            $this->repo->updateStage((int) $stage['id'], 'IN_PROGRESS', date('Y-m-d H:i:s'), null, $userId, null, null, (int) $release['id']);
            $this->repo->preparation((int) $stage['job_id'], 'IN_PRODUCTION');
            BusinessEventDispatcher::emit('PRODUCTION_STAGE_STARTED', 'PRODUCTION_STAGE', (int) $stage['id'], $userId, []);

            return [];
        });
    }

    /**
     * @return array<string, string>
     */
    public function pause(int $stageId, string $reason, int $userId, ?string $key = null): array
    {
        if (!can('production.stage.block') && !can('production.supervise')) {
            return ['_form' => 'You cannot pause a production stage.'];
        }
        $reason = strtoupper(trim($reason));
        if (!in_array($reason, self::PAUSE, true)) {
            return ['reason' => 'Choose a pause reason.'];
        }

        return $this->act($stageId, $userId, 'PAUSE', $key, function (array $stage, array $release) use ($reason, $userId): array {
            $this->repo->updateStage((int) $stage['id'], 'PAUSED', null, null, $userId, $reason, null, (int) $release['id']);
            BusinessEventDispatcher::emit('PRODUCTION_STAGE_PAUSED', 'PRODUCTION_STAGE', (int) $stage['id'], $userId, ['reason' => strtolower($reason)]);

            return [];
        });
    }

    /**
     * @return array<string, string>
     */
    public function block(int $stageId, string $reason, int $userId, ?string $key = null): array
    {
        if (!can('production.stage.block') && !can('production.supervise')) {
            return ['_form' => 'You cannot block a production stage.'];
        }
        $reason = strtoupper(trim($reason));
        if (!in_array($reason, self::BLOCKS, true)) {
            return ['reason' => 'Choose a blocking reason.'];
        }

        return $this->act($stageId, $userId, 'BLOCK', $key, function (array $stage, array $release) use ($reason, $userId): array {
            $this->repo->updateStage((int) $stage['id'], 'BLOCKED', null, null, $userId, null, $reason, (int) $release['id']);
            $role = \App\Helpers\Database::connection()->query("SELECT id FROM roles WHERE code = 'MANAGEMENT' LIMIT 1")->fetchColumn();
            if ($role) {
                $this->notifications->send(null, (int) $role, 'SYSTEM', 'Production blocked', (string) $stage['stage_name'] . ' is blocked: ' . $reason . '.', 'JOB', (int) $stage['job_id'], 'HIGH', 'stage-block-' . (int) $stage['id'] . '-' . $reason);
            }
            BusinessEventDispatcher::emit('PRODUCTION_STAGE_BLOCKED', 'PRODUCTION_STAGE', (int) $stage['id'], $userId, ['reason' => strtolower($reason)]);

            return [];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function complete(int $stageId, array $input, int $userId, ?string $key = null): array
    {
        if (!can('production.stage.complete') && !can('production.supervise')) {
            return ['_form' => 'You cannot complete a production stage.'];
        }
        $good = (string) ($input['good_quantity'] ?? '0');
        $waste = (string) ($input['waste_quantity'] ?? '0');
        $rework = (string) ($input['rework_quantity'] ?? '0');
        foreach (['good' => $good, 'waste' => $waste, 'rework' => $rework] as $name => $value) {
            if (!Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
                return [$name => 'Quantity cannot be negative.'];
            }
        }

        return $this->act($stageId, $userId, 'COMPLETE', $key, function (array $stage) use ($good, $waste, $rework, $userId): array {
            if ((string) $stage['status'] === 'COMPLETE') {
                return [];
            }
            $itemId = (int) ($stage['job_item_id'] ?? 0);
            if ($itemId > 0) {
                $item = $this->repo->item($itemId);
                $planned = (string) ($item['quantity'] ?? '0');
                $sum = Decimal::add($good, $waste, 4);
                if (Decimal::cmp($sum, $planned) > 0) {
                    return ['_form' => 'Good quantity plus waste is above the planned quantity.'];
                }
                if (Decimal::cmp($rework, $good) > 0) {
                    return ['_form' => 'Rework cannot be greater than the good quantity.'];
                }
                $this->repo->addGood($itemId, Decimal::round($good, 4), Decimal::round($waste, 4), Decimal::round($rework, 4));
            }
            $this->repo->updateStage((int) $stage['id'], 'COMPLETE', null, date('Y-m-d H:i:s'), $userId, null, null, null);
            BusinessEventDispatcher::emit('PRODUCTION_STAGE_COMPLETED', 'PRODUCTION_STAGE', (int) $stage['id'], $userId, ['good' => $good]);

            return [];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function rework(int $jobId, array $input, int $userId): array
    {
        if (!can('production.rework.manage') && !can('production.qc.disposition')) {
            return ['errors' => ['_form' => 'You cannot record rework.'], 'id' => null];
        }
        $reason = strtoupper(trim((string) ($input['reason_code'] ?? '')));
        if (!in_array($reason, self::REWORK, true)) {
            return ['errors' => ['reason_code' => 'Choose a rework reason. This is a cause category, not a named person.'], 'id' => null];
        }
        $material = $this->money($input['material_cost'] ?? '0');
        $labour = $this->money($input['labour_cost'] ?? '0');
        $machine = $this->money($input['machine_cost'] ?? '0');
        $other = $this->money($input['other_cost'] ?? '0');
        $id = $this->repo->insertRework([
            'job_id' => $jobId,
            'job_item_id' => (int) ($input['job_item_id'] ?? 0) > 0 ? (int) $input['job_item_id'] : null,
            'stage_id' => (int) ($input['stage_id'] ?? 0) > 0 ? (int) $input['stage_id'] : null,
            'reason_code' => $reason,
            'material_cost' => $material,
            'labour_cost' => $labour,
            'machine_cost' => $machine,
            'other_cost' => $other,
            'notes' => blank_to_null($input['notes'] ?? null),
            'created_by' => $userId,
        ]);
        $combinedOther = Decimal::add($machine, $other, 2);
        $this->repo->addReworkCost($jobId, $material, $labour, $combinedOther);
        BusinessEventDispatcher::emit('PRODUCTION_REWORK_CREATED', 'JOB', $jobId, $userId, ['rework_id' => $id]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function qcFail(int $jobId, string $reason, int $userId): array
    {
        if (!can('production.qc.disposition') && !can('qc.perform') && !can('production.supervise')) {
            return ['_form' => 'You cannot record a QC result.'];
        }
        \App\Helpers\Database::connection()->prepare(
            'INSERT INTO quality_checks (job_id, check_type, status, fail_reason, checked_by) VALUES (?, ?, ?, ?, ?)'
        )->execute([$jobId, 'RELEASE_GATE', 'FAIL', mb_substr($reason, 0, 255), $userId]);
        BusinessEventDispatcher::emit('PRODUCTION_QC_FAILED', 'JOB', $jobId, $userId, []);

        return [];
    }

    /**
     * @param callable(array<string, mixed>, array<string, mixed>): array<string, string> $body
     * @return array<string, string>
     */
    private function act(int $stageId, int $userId, string $action, ?string $key, callable $body): array
    {
        if ($key !== null && $key !== '' && $this->repo->stageActionExists($key)) {
            return [];
        }
        $stage = $this->repo->stage($stageId);
        if ($stage === null) {
            return ['_form' => 'That stage was not found.'];
        }
        $release = $this->repo->currentRelease((int) $stage['job_id']);
        if ($release === null) {
            return ['_form' => 'This job has not been released to production.'];
        }
        $errors = $body($stage, $release);
        if ($errors !== []) {
            return $errors;
        }
        if ($key !== null && $key !== '') {
            $this->repo->insertStageAction($stageId, $action, $key, $userId);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $stage
     * @return array<string, mixed>|null
     */
    private function previousOpen(array $stage): ?array
    {
        foreach ($this->repo->stages((int) $stage['job_id']) as $row) {
            if ((int) $row['sort_order'] < (int) $stage['sort_order'] && (string) $row['status'] !== 'COMPLETE') {
                if ((int) ($row['job_item_id'] ?? 0) === (int) ($stage['job_item_id'] ?? 0)) {
                    return $row;
                }
            }
        }

        return null;
    }

    private function money(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || !Decimal::isNumeric($text) || Decimal::cmp($text, '0') < 0) {
            return '0.00';
        }

        return Decimal::round($text, 2);
    }
}
