<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AssetRepository;

/**
 * Asset maintenance dates move forward from the last completed visit.
 * A due plan can open a draft service request. It never creates an invoice.
 * Equipment downtime stays on MaintenanceService.
 */
final class AssetMaintenanceService
{
    public function __construct(
        private readonly AssetRepository $assets = new AssetRepository(),
        private readonly NumberingService $numbers = new NumberingService()
    ) {
    }

    public static function nextDate(string $from, int $months, int $days): string
    {
        $date = new \DateTimeImmutable($from);
        if ($months > 0) {
            $date = $date->modify('+' . $months . ' months');
        }
        if ($days > 0) {
            $date = $date->modify('+' . $days . ' days');
        }

        return $date->format('Y-m-d');
    }

    public static function dueState(string $next, string $today): string
    {
        if ($next < $today) {
            return 'OVERDUE';
        }
        $soon = (new \DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
        if ($next <= $soon) {
            return 'DUE';
        }

        return 'UPCOMING';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createPlan(array $input, int $userId): array
    {
        unset($userId);
        if (!can('maintenance.manage')) {
            return ['errors' => ['_form' => 'You cannot manage maintenance plans.'], 'id' => null];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['name' => 'Name is required.'], 'id' => null];
        }
        $months = max(0, (int) ($input['interval_months'] ?? 0));
        $days = max(0, (int) ($input['interval_days'] ?? 0));
        if ($months === 0 && $days === 0) {
            return ['errors' => ['interval_months' => 'Set an interval in months or days.'], 'id' => null];
        }
        $id = $this->assets->insertPlan([
            'name' => mb_substr($name, 0, 160),
            'asset_type_id' => (int) ($input['asset_type_id'] ?? 0) > 0 ? (int) $input['asset_type_id'] : null,
            'interval_days' => $days,
            'interval_months' => $months,
            'checklist_name' => blank_to_null($input['checklist_name'] ?? null),
            'recipe_id' => (int) ($input['recipe_id'] ?? 0) > 0 ? (int) $input['recipe_id'] : null,
            'auto_request' => !empty($input['auto_request']) ? 1 : 0,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function assign(int $assetId, int $planId, ?string $from, int $userId): array
    {
        unset($userId);
        if (!can('maintenance.manage') && !can('assets.edit')) {
            return ['_form' => 'You cannot assign a maintenance plan.'];
        }
        $plan = $this->assets->plan($planId);
        $asset = $this->assets->find($assetId);
        if ($plan === null || $asset === null) {
            return ['_form' => 'Choose an asset and a plan.'];
        }
        $start = $from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d');
        $next = self::nextDate($start, (int) $plan['interval_months'], (int) $plan['interval_days']);
        $this->assets->assignPlan([
            'asset_id' => $assetId,
            'plan_id' => $planId,
            'last_completed_on' => null,
            'next_due_on' => $next,
        ]);
        $this->rememberNext($assetId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function complete(int $assignmentId, string $completedOn, int $userId): array
    {
        if (!can('inspections.perform') && !can('maintenance.manage')) {
            return ['_form' => 'You cannot complete maintenance.'];
        }
        $row = $this->assets->assignment($assignmentId);
        if ($row === null) {
            return ['_form' => 'That maintenance assignment was not found.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedOn)) {
            return ['completed_on' => 'Completion date is required.'];
        }
        $next = self::nextDate($completedOn, (int) $row['interval_months'], (int) $row['interval_days']);
        $this->assets->completeAssignment($assignmentId, $completedOn, $next);
        $this->assets->setServiceDates((int) $row['asset_id'], $completedOn, $next);
        $this->assets->insertEvent([
            'asset_id' => (int) $row['asset_id'],
            'event_type' => 'MAINTENANCE',
            'summary' => (string) $row['name'] . ' completed. Next ' . $next,
            'related_type' => 'maintenance_plan',
            'related_id' => (int) $row['plan_id'],
            'happened_at' => $completedOn . ' 00:00:00',
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function override(int $assignmentId, string $next, int $userId): array
    {
        unset($userId);
        if (!can('maintenance.manage')) {
            return ['_form' => 'You cannot override a service date.'];
        }
        if ($this->assets->assignment($assignmentId) === null) {
            return ['_form' => 'That assignment was not found.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $next)) {
            return ['next_due_on' => 'Enter the next service date.'];
        }
        $this->assets->overrideDue($assignmentId, $next);

        return [];
    }

    /**
     * @return array{notified: int, drafts: int}
     */
    public function notifyDue(): array
    {
        $today = date('Y-m-d');
        $notes = new NotificationService();
        $role = $this->assets->rowsForReport("SELECT id FROM roles WHERE code = 'MANAGEMENT' LIMIT 1");
        $roleId = (int) ($role[0]['id'] ?? 0);
        $notified = 0;
        $drafts = 0;
        foreach ($this->assets->dueAssignments($today) as $row) {
            $sent = $notes->send(
                null,
                $roleId > 0 ? $roleId : null,
                'MAINTENANCE_DUE',
                'Maintenance due',
                (string) $row['asset_number'] . ' / ' . (string) $row['plan_name'] . ' was due ' . (string) $row['next_due_on'] . '.',
                'customer_asset',
                (int) $row['asset_id'],
                'NORMAL',
                'maintenance-due:' . $row['id'] . ':' . $row['next_due_on']
            );
            if ($sent) {
                $notified++;
                BusinessEventDispatcher::emit('MAINTENANCE_DUE', 'ASSET', (int) $row['asset_id'], null, [
                    'plan' => (string) $row['plan_name'],
                ]);
                BusinessEventDispatcher::emit('ASSET_SERVICE_DUE', 'ASSET', (int) $row['asset_id'], null, []);
            }
            if ((int) $row['auto_request'] === 1 && $this->assets->openRequestForAsset((int) $row['asset_id']) === null) {
                $this->assets->insertRequest([
                    'request_number' => $this->numbers->serviceRequest(),
                    'customer_id' => (int) $row['customer_id'],
                    'project_site_id' => null,
                    'asset_id' => (int) $row['asset_id'],
                    'asset_component_id' => null,
                    'reported_by' => 'Maintenance plan',
                    'contact_detail' => null,
                    'source' => 'INSPECTION',
                    'problem_category' => 'OTHER',
                    'description' => 'Draft from maintenance plan ' . $row['plan_name'] . '. Not invoiced.',
                    'priority' => 'NORMAL',
                    'status' => 'NEW',
                    'classification' => 'MAINTENANCE_CONTRACT',
                    'warranty_candidate' => 0,
                    'assigned_user_id' => null,
                    'reported_at' => date('Y-m-d H:i:s'),
                    'created_by' => null,
                ]);
                $drafts++;
            }
        }

        return ['notified' => $notified, 'drafts' => $drafts];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forecast(int $days): array
    {
        $until = (new \DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');

        return $this->assets->dueAssignments($until);
    }

    private function rememberNext(int $assetId): void
    {
        $next = null;
        foreach ($this->assets->assignments($assetId) as $row) {
            if ((int) $row['active'] !== 1 || $row['next_due_on'] === null) {
                continue;
            }
            if ($next === null || (string) $row['next_due_on'] < $next) {
                $next = (string) $row['next_due_on'];
            }
        }
        if ($next !== null) {
            $this->assets->setServiceDates($assetId, null, $next);
        }
    }
}
