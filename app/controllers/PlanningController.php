<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\OperationsRepository;
use App\Repositories\PlanningRepository;
use App\Repositories\SupplierRepository;
use App\Services\CapacityService;
use App\Services\FieldWorkService;
use App\Services\JobReadinessService;
use App\Services\MaintenanceService;
use App\Services\RecurringJobService;
use App\Services\ResourceService;
use App\Services\ScheduleService;
use App\Services\SubcontractService;
use App\Services\VehicleService;
use App\Services\WorkExecutionService;

/**
 * Scheduling board, capacity, field work, and the resource screens.
 */
final class PlanningController
{
    public function schedule(): void
    {
        (new ResourceService())->ensurePeople();
        $range = (string) ($_GET['range'] ?? 'week');
        [$start, $end, $days] = $this->range($range);
        $planning = new PlanningRepository();
        View::render('planning/schedule', [
            'title' => 'Schedule',
            'activeNav' => 'schedule',
            'range' => $range,
            'days' => $days,
            'resources' => $planning->resources(null, true),
            'entries' => $planning->entriesBetween($start . ' 00:00:00', $end . ' 00:00:00'),
            'unscheduled' => $planning->unscheduledStages(),
            'canManage' => can('schedule.manage'),
        ]);
    }

    public function storeEntry(): void
    {
        $result = (new ScheduleService())->book($_POST, (int) auth_user()['id']);
        $this->finishSchedule($result, 'Work scheduled.');
    }

    public function moveEntry(): void
    {
        $result = (new ScheduleService())->move(
            (int) ($_POST['entry_id'] ?? 0),
            (string) ($_POST['start_datetime'] ?? ''),
            (string) ($_POST['end_datetime'] ?? ''),
            (int) auth_user()['id'],
            (string) ($_POST['reason'] ?? ''),
            array_map('intval', (array) ($_POST['resource_ids'] ?? []))
        );
        $this->finishSchedule($result, 'Schedule updated.');
    }

    public function capacity(): void
    {
        $days = (int) ($_GET['days'] ?? 7);
        View::render('planning/capacity', [
            'title' => 'Capacity',
            'activeNav' => 'capacity',
            'report' => (new ResourceService())->capacity($days),
        ]);
    }

    public function today(): void
    {
        $planning = new PlanningRepository();
        $date = date('Y-m-d');
        $entries = $planning->entriesBetween($date . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00');
        View::render('planning/today', [
            'title' => 'Today',
            'activeNav' => 'today',
            'date' => $date,
            'entries' => $entries,
            'installations' => $planning->installationsBetween($date, $date),
            'surveys' => $planning->surveysOn($date),
            'deliveries' => $planning->deliveriesOn($date),
            'maintenance' => $planning->maintenanceDue($date),
            'unavailable' => $this->unavailableToday($planning, $date),
        ]);
    }

    public function myWork(): void
    {
        $userId = (int) auth_user()['id'];
        $planning = new PlanningRepository();
        $tasks = $planning->tasksForUser($userId);
        $today = date('Y-m-d');
        $groups = ['Today' => [], 'Overdue' => [], 'Upcoming' => []];
        foreach ($tasks as $task) {
            $due = (string) ($task['due_date'] ?? '');
            if ($due !== '' && $due < $today) {
                $groups['Overdue'][] = $task;
            } elseif ($due === $today || $due === '') {
                $groups['Today'][] = $task;
            } else {
                $groups['Upcoming'][] = $task;
            }
        }
        View::render('planning/my_work', [
            'title' => 'My work',
            'activeNav' => 'my-work',
            'groups' => $groups,
            'reasons' => $planning->blockReasons(),
            'installations' => array_values(array_filter(
                $planning->installationsBetween($today, date('Y-m-d', strtotime('+14 days'))),
                static fn (array $row): bool => (int) ($row['assigned_user_id'] ?? 0) === $userId
            )),
        ]);
    }

    public function startTask(): void
    {
        $errors = (new WorkExecutionService())->startTask((int) ($_POST['task_id'] ?? 0), (int) auth_user()['id']);
        $this->flashResult($errors, 'Task started.', '/work');
    }

    public function completeTask(): void
    {
        $errors = (new WorkExecutionService())->completeTask((int) ($_POST['task_id'] ?? 0), (int) auth_user()['id']);
        $this->flashResult($errors, 'Task completed.', '/work');
    }

    public function blockTask(): void
    {
        $errors = (new WorkExecutionService())->blockTask(
            (int) ($_POST['task_id'] ?? 0),
            (int) ($_POST['reason_id'] ?? 0),
            (string) ($_POST['note'] ?? ''),
            (int) auth_user()['id']
        );
        $this->flashResult($errors, 'Task blocked.', '/work');
    }

    public function resources(): void
    {
        $type = strtoupper(trim((string) ($_GET['type'] ?? '')));
        View::render('planning/resources', [
            'title' => 'Resources',
            'activeNav' => 'resources',
            'type' => $type,
            'rows' => (new PlanningRepository())->resources($type !== '' ? $type : null),
            'canManage' => can('resources.manage') || can('machines.manage') || can('vehicles.manage'),
        ]);
    }

    public function resourceForm(): void
    {
        View::render('planning/resource_form', [
            'title' => 'Resource',
            'activeNav' => 'resources',
            'resource' => null,
            'errors' => [],
            'types' => ResourceService::TYPES,
        ]);
    }

    public function resourceSave(): void
    {
        $result = (new ResourceService())->save(null, $_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            View::render('planning/resource_form', [
                'title' => 'Resource',
                'activeNav' => 'resources',
                'resource' => $_POST,
                'errors' => $result['errors'],
                'types' => ResourceService::TYPES,
            ]);

            return;
        }
        flash('success', 'Resource saved.');
        redirect('/resources');
    }

    public function unavailableSave(): void
    {
        $errors = (new ResourceService())->addUnavailability($_POST, (int) auth_user()['id']);
        $this->flashResult($errors, 'Unavailability saved.', '/resources');
    }

    public function exceptions(): void
    {
        $planning = new PlanningRepository();
        View::render('planning/calendar', [
            'title' => 'Calendar',
            'activeNav' => 'resources',
            'rows' => $planning->exceptionsBetween(date('Y-01-01'), date('Y-12-31', strtotime('+1 year'))),
            'schedules' => $planning->schedules(),
            'canManage' => can('resources.manage'),
        ]);
    }

    public function exceptionSave(): void
    {
        if (!can('resources.manage')) {
            deny_access('You cannot change the calendar.');
        }
        (new PlanningRepository())->saveException([
            'exception_date' => (string) ($_POST['exception_date'] ?? ''),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'exception_type' => 'PUBLIC_HOLIDAY',
            'working_day_override' => (int) ($_POST['working_day_override'] ?? 0) === 1 ? 1 : 0,
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);
        flash('success', 'Calendar date saved.');
        redirect('/resources/calendar');
    }

    public function maintenance(): void
    {
        View::render('planning/maintenance', [
            'title' => 'Maintenance',
            'activeNav' => 'maintenance',
            'rows' => (new PlanningRepository())->maintenanceDue(date('Y-m-d', strtotime('+365 days'))),
            'resources' => (new PlanningRepository())->resources(null, true),
            'canManage' => can('maintenance.manage'),
            'errors' => [],
        ]);
    }

    public function maintenanceSave(): void
    {
        $result = (new MaintenanceService())->schedule($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
        } else {
            flash('success', 'Maintenance scheduled. The resource is unavailable for that window.');
        }
        redirect('/maintenance');
    }

    public function vehicles(): void
    {
        View::render('planning/vehicles', [
            'title' => 'Vehicles',
            'activeNav' => 'vehicles',
            'rows' => (new PlanningRepository())->vehicles(),
            'canManage' => can('vehicles.manage'),
        ]);
    }

    public function mileageSave(): void
    {
        $result = (new VehicleService())->recordUsage($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
        } else {
            flash('success', 'Mileage recorded.');
        }
        redirect('/vehicles');
    }

    public function installations(): void
    {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime('+30 days'));
        View::render('planning/installations', [
            'title' => 'Installation planner',
            'activeNav' => 'installations',
            'rows' => (new PlanningRepository())->installationsBetween($start, $end),
        ]);
    }

    public function field(string $id): void
    {
        $installationId = route_id($id);
        $row = (new PlanningRepository())->installation($installationId);
        if ($row === null) {
            abort_not_found('That installation was not found.');
        }
        $ops = new OperationsRepository();
        View::render('planning/field', [
            'title' => 'Field work',
            'activeNav' => 'installations',
            'installation' => $row,
            'checklist' => $ops->checklist($installationId),
            'job' => (new PlanningRepository())->job((int) $row['job_id']),
        ]);
    }

    public function fieldAct(string $id): void
    {
        $errors = (new FieldWorkService())->act((int) $id, (string) ($_POST['action_name'] ?? ''), (int) auth_user()['id'], $_POST);
        $this->flashResult($errors, 'Field update saved.', '/field/' . (int) $id);
    }

    public function recurring(): void
    {
        View::render('planning/recurring', [
            'title' => 'Recurring jobs',
            'activeNav' => 'recurring',
            'rows' => (new PlanningRepository())->recurringAll(),
            'canManage' => can('recurring_jobs.manage'),
        ]);
    }

    public function recurringSave(): void
    {
        $result = (new RecurringJobService())->save($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
        } else {
            flash('success', 'Recurring template saved. Nothing is generated until the date is due.');
        }
        redirect('/recurring');
    }

    public function recurringRun(): void
    {
        $count = (new RecurringJobService())->run();
        flash('success', $count . ' follow-up' . ($count === 1 ? '' : 's') . ' generated.');
        redirect('/recurring');
    }

    public function subcontracts(): void
    {
        View::render('planning/subcontracts', [
            'title' => 'Subcontractors',
            'activeNav' => 'subcontracts',
            'rows' => (new PlanningRepository())->subcontracts(),
            'suppliers' => (new SupplierRepository())->options(),
            'canManage' => can('subcontractors.manage'),
            'showCost' => can('resource_cost.view') || can('subcontractors.manage') || can('costing.view'),
        ]);
    }

    public function subcontractSave(): void
    {
        $result = (new SubcontractService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
        } else {
            flash('success', 'Subcontract order created.');
        }
        redirect('/subcontracts');
    }

    public function subcontractComplete(): void
    {
        $errors = (new SubcontractService())->complete(
            (int) ($_POST['order_id'] ?? 0),
            (string) ($_POST['actual_cost'] ?? ''),
            (int) auth_user()['id']
        );
        $this->flashResult($errors, 'Subcontract cost posted to the job.', '/subcontracts');
    }

    public function utilisation(): void
    {
        $this->report('utilisation', 'Resource utilisation');
    }

    public function downtime(): void
    {
        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        View::render('planning/downtime', [
            'title' => 'Downtime',
            'activeNav' => 'report-downtime',
            'rows' => (new PlanningRepository())->downtimeBetween($from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function demand(): void
    {
        $report = (new ResourceService())->capacity((int) ($_GET['days'] ?? 14));
        $unscheduled = 0;
        foreach ((new PlanningRepository())->unscheduledStages() as $stage) {
            $unscheduled += (int) ($stage['estimated_minutes'] ?? 0);
        }
        View::render('planning/demand', [
            'title' => 'Capacity and demand',
            'activeNav' => 'report-capacity',
            'report' => $report,
            'unscheduled_minutes' => $unscheduled,
        ]);
    }

    public function lateJobs(): void
    {
        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        $planning = new PlanningRepository();
        $rows = $planning->lateJobs($from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00');
        foreach ($rows as &$row) {
            $row['causes'] = $planning->blockReasonsForJob((int) $row['id']);
        }
        unset($row);
        View::render('planning/late', [
            'title' => 'Late jobs',
            'activeNav' => 'report-late',
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function finishSchedule(array $result, string $success): void
    {
        if (empty($result['ok'])) {
            $message = (string) ($result['error'] ?? 'The schedule was not saved.');
            foreach ($result['conflicts'] ?? [] as $conflict) {
                $message .= ' ' . (string) ($conflict['message'] ?? '');
            }
            flash('error', trim($message));
        } else {
            $warning = trim(implode(' ', $result['warnings'] ?? []));
            flash('success', $warning !== '' ? $success . ' ' . $warning : $success);
        }
        redirect('/schedule');
    }

    /**
     * @param array<string, string> $errors
     */
    private function flashResult(array $errors, string $success, string $path): void
    {
        if ($errors !== []) {
            flash('error', (string) reset($errors));
        } else {
            flash('success', $success);
        }
        redirect($path);
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function range(string $range): array
    {
        $start = date('Y-m-d');
        $count = match ($range) {
            'today' => 1,
            'fortnight' => 14,
            'month' => 31,
            default => 7,
        };
        $days = [];
        for ($i = 0; $i < $count; $i++) {
            $days[] = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
        }

        return [$days[0], date('Y-m-d', strtotime(end($days) . ' +1 day')), $days];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unavailableToday(PlanningRepository $planning, string $date): array
    {
        $rows = [];
        foreach ($planning->resources(null, true) as $resource) {
            $away = $planning->unavailabilityBetween((int) $resource['id'], $date . ' 00:00:00', $date . ' 23:59:59');
            foreach ($away as $item) {
                $item['resource_name'] = $resource['name'];
                $item['resource_type'] = $resource['resource_type'];
                $rows[] = $item;
            }
        }

        return $rows;
    }

    private function report(string $view, string $title): void
    {
        $report = (new ResourceService())->capacity((int) ($_GET['days'] ?? 7));
        $actuals = (new PlanningRepository())->actualMinutesByUser($report['from'] . ' 00:00:00', date('Y-m-d', strtotime($report['to'] . ' +1 day')) . ' 00:00:00');
        View::render('planning/' . $view, [
            'title' => $title,
            'activeNav' => 'report-utilisation',
            'report' => $report,
            'actuals' => $actuals,
        ]);
    }
}
