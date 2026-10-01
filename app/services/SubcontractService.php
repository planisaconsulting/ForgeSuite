<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\OperationsRepository;
use App\Repositories\PlanningRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UserRepository;

/**
 * A completed subcontract posts its actual cost to the job once.
 *
 * The order stores the other-cost id. Completing it again does not add a
 * second cost, even if someone also looks at job other costs.
 */
final class SubcontractService
{
    /** @var list<string> */
    public const STATUSES = ['DRAFT', 'REQUESTED', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly JobCostingService $costing = new JobCostingService(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        if (!AuthorizationService::allows((new UserRepository())->find($userId), 'subcontractors.manage')) {
            return ['errors' => ['_form' => 'You cannot create a subcontract order.'], 'id' => null];
        }
        $jobId = (int) ($input['job_id'] ?? 0);
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        $description = trim((string) ($input['description'] ?? ''));
        if ($this->planning->job($jobId) === null) {
            return ['errors' => ['job_id' => 'Choose a job.'], 'id' => null];
        }
        $supplier = (new SupplierRepository())->find($supplierId);
        if ($supplier === null) {
            return ['errors' => ['supplier_id' => 'Choose a supplier.'], 'id' => null];
        }
        if ($description === '') {
            return ['errors' => ['description' => 'Describe the subcontract work.'], 'id' => null];
        }
        $estimated = trim((string) ($input['estimated_cost'] ?? '0'));
        if (!Decimal::isNumeric($estimated) || Decimal::cmp($estimated, '0') < 0) {
            return ['errors' => ['estimated_cost' => 'Enter an estimated cost.'], 'id' => null];
        }
        $id = $this->planning->insertSubcontract([
            'order_number' => $this->numbers->subcontract(),
            'job_id' => $jobId,
            'supplier_id' => $supplierId,
            'description' => mb_substr($description, 0, 255),
            'required_date' => $this->date($input['required_date'] ?? null),
            'status' => 'DRAFT',
            'estimated_cost' => Decimal::money($estimated),
            'notes' => $this->blank($input['notes'] ?? null),
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function complete(int $id, string $actualCost, int $userId): array
    {
        if (!AuthorizationService::allows((new UserRepository())->find($userId), 'subcontractors.manage')) {
            return ['_form' => 'You cannot complete a subcontract order.'];
        }
        if (!Decimal::isNumeric($actualCost) || Decimal::cmp($actualCost, '0') < 0) {
            return ['actual_cost' => 'Enter the actual cost.'];
        }
        $actual = Decimal::money($actualCost);

        return Database::transaction(function () use ($id, $actual, $userId): array {
            $order = $this->planning->subcontract($id);
            if ($order === null) {
                return ['_form' => 'That subcontract order was not found.'];
            }
            if ($order['other_cost_id'] !== null || (string) $order['status'] === 'COMPLETED') {
                return [];
            }
            $reference = 'SUBCONTRACT-' . $order['order_number'];
            $existing = $this->ops->otherCostByReference((int) $order['job_id'], $reference);
            if ($existing !== null) {
                $this->planning->completeSubcontract($id, $actual, (int) $existing['id']);

                return [];
            }
            $otherId = $this->ops->insertOther([
                'job_id' => (int) $order['job_id'],
                'cost_type' => 'SUBCONTRACTOR',
                'description' => (string) $order['description'],
                'supplier_id' => (int) $order['supplier_id'],
                'quantity' => '1.0000',
                'unit_cost' => $actual,
                'total_cost' => $actual,
                'reference' => $reference,
                'created_by' => $userId,
            ]);
            $this->planning->completeSubcontract($id, $actual, $otherId);
            $this->costing->refresh((int) $order['job_id']);
            $this->audit->record('subcontract_order', $id, 'SUBCONTRACT_COMPLETED', null, [
                'actual_cost' => $actual,
                'other_cost_id' => $otherId,
            ], $userId);

            return [];
        });
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

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
