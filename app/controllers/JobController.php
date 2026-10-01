<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\ArtworkApprovalMethod;
use App\Domain\ArtworkStatus;
use App\Domain\DeliveryMethod;
use App\Domain\InstallationStatus;
use App\Domain\JobItemStatus;
use App\Domain\JobStatus;
use App\Domain\MaterialUsageType;
use App\Domain\OtherCostType;
use App\Domain\QcStatus;
use App\Domain\TaskStatus;
use App\Domain\TaskType;
use App\Domain\WasteReason;
use App\Domain\WorkType;
use App\Helpers\View;
use App\Repositories\AttachmentRepository;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UserRepository;
use App\Services\JobConflictException;
use App\Services\JobCostingService;
use App\Services\JobService;
use App\Services\JobWorkflowService;
use App\Services\QuotePdf;
use App\Services\SettingsService;

/**
 * Job list, the operational workspace, and the workshop screens.
 */
final class JobController
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'priority' => strtoupper(trim((string) ($_GET['priority'] ?? ''))),
            'assigned_to' => (int) ($_GET['assigned_to'] ?? 0),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'team_id' => (int) ($_GET['team_id'] ?? 0),
            'stage' => trim((string) ($_GET['stage'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'install_from' => trim((string) ($_GET['install_from'] ?? '')),
            'install_to' => trim((string) ($_GET['install_to'] ?? '')),
            'overdue' => isset($_GET['overdue']) ? 1 : 0,
            'archived' => isset($_GET['archived']) ? 1 : 0,
        ];
        $ops = new OperationsRepository();
        View::render('jobs/index', [
            'title' => 'Jobs',
            'activeNav' => 'jobs',
            'rows' => (new JobRepository())->search($filters),
            'filters' => $filters,
            'statuses' => JobStatus::cases(),
            'staff' => (new UserRepository())->listAll(),
            'teams' => $ops->teams(),
            'stages' => $ops->catalogueStages(),
        ]);
    }

    public function show(string $id): void
    {
        $job = $this->job($id);
        $tab = (string) ($_GET['tab'] ?? 'overview');
        $tabs = $this->tabs();
        if (!isset($tabs[$tab])) {
            $tab = 'overview';
        }
        $ops = new OperationsRepository();
        $jobId = (int) $job['id'];
        $userId = (int) auth_user()['id'];
        $data = [
            'title' => (string) $job['job_number'],
            'activeNav' => 'jobs',
            'job' => $job,
            'tab' => $tab,
            'tabs' => $tabs,
            'errors' => [],
            'staff' => (new UserRepository())->listAll(),
            'items' => $ops->items($jobId),
            'scheduleHealth' => (new \App\Services\JobReadinessService())->jobHealth($jobId),
            'earliest' => (new \App\Services\ScheduleService())->earliestCompletion($jobId),
        ];
        if ($tab === 'finance') {
            $data['finance'] = (new \App\Services\JobFinancialService())->report($jobId);
        } elseif ($tab === 'overview') {
            $data['choices'] = (new JobWorkflowService())->choices((string) $job['status'], (string) $job['delivery_method']);
            $data['checks'] = (new JobService())->checks($job);
            $data['costing'] = can('costing.view') ? (new JobCostingService())->report($jobId) : null;
        } elseif ($tab === 'artwork') {
            $artworks = $ops->artworks($jobId);
            $approvals = [];
            foreach ($artworks as $artwork) {
                $approvals[(int) $artwork['id']] = $ops->approvals((int) $artwork['id']);
            }
            $data['artworks'] = $artworks;
            $data['approvals'] = $approvals;
            $data['methods'] = ArtworkApprovalMethod::cases();
            $data['artworkStatuses'] = ArtworkStatus::cases();
        } elseif ($tab === 'tasks') {
            $data['tasks'] = $ops->tasks($jobId);
            $data['teams'] = $ops->teams();
            $data['taskTypes'] = TaskType::cases();
        } elseif ($tab === 'production') {
            $data['stages'] = $ops->stages($jobId);
            $data['templates'] = $ops->templates();
            $data['tasks'] = $ops->tasks($jobId);
            $data['checks'] = $ops->qualityChecks($jobId);
            $data['definitions'] = $ops->qcDefinitions();
            $data['qcStatuses'] = QcStatus::cases();
        } elseif ($tab === 'materials') {
            $data['requirements'] = $ops->requirements($jobId);
            $data['usage'] = $ops->usage($jobId);
            $data['products'] = (new ProductRepository())->search('', 'active', null, 300);
            $data['usageTypes'] = MaterialUsageType::cases();
            $data['reasons'] = WasteReason::cases();
            $data['variance'] = can('costing.view') ? ((new JobCostingService())->report($jobId)['materials'] ?? []) : [];
            if (can('inventory.view')) {
                $inventory = new \App\Repositories\InventoryRepository();
                $purchasing = new \App\Repositories\PurchasingRepository();
                $data['locations'] = $inventory->locations(true);
                $data['reservations'] = $inventory->reservationsForJob($jobId);
                $data['openItems'] = $inventory->searchItems(['open_only' => 1], 80);
                $data['jobOrders'] = $purchasing->ordersForJob($jobId);
                $coverage = [];
                foreach ($data['requirements'] as $requirement) {
                    $productId = (int) ($requirement['product_id'] ?? 0);
                    $required = (string) ($requirement['final_required_quantity'] ?: $requirement['required_quantity']);
                    $reserved = '0';
                    foreach ($data['reservations'] as $reservation) {
                        if ((int) $reservation['product_id'] === $productId && (string) $reservation['status'] === 'RESERVED') {
                            $reserved = \App\Helpers\Decimal::add($reserved, (string) $reservation['quantity'], 4);
                        }
                    }
                    $issued = '0';
                    foreach ($data['usage'] as $usageRow) {
                        if ((int) ($usageRow['product_id'] ?? 0) === $productId && \App\Helpers\Decimal::cmp((string) $usageRow['quantity'], '0') > 0) {
                            $issued = \App\Helpers\Decimal::add($issued, (string) $usageRow['quantity'], 4);
                        }
                    }
                    $short = \App\Helpers\Decimal::sub($required, \App\Helpers\Decimal::add($reserved, $issued, 4), 4);
                    $coverage[$productId] = [
                        'required' => $required,
                        'reserved' => $reserved,
                        'issued' => $issued,
                        'shortage' => \App\Helpers\Decimal::cmp($short, '0') > 0 ? $short : '0.0000',
                    ];
                }
                $data['coverage'] = $coverage;
                $hints = [];
                $matcher = new \App\Services\RecipeStockMatcher();
                foreach ($data['requirements'] as $requirement) {
                    $productId = (int) ($requirement['product_id'] ?? 0);
                    if ($productId < 1) {
                        continue;
                    }
                    $hints[$productId] = $matcher->suggest($productId, (string) ($requirement['final_required_quantity'] ?? '0'));
                }
                $data['stockHints'] = $hints;
            } else {
                $data['locations'] = [];
                $data['reservations'] = [];
                $data['openItems'] = [];
                $data['jobOrders'] = [];
                $data['coverage'] = [];
                $data['stockHints'] = [];
            }
        } elseif ($tab === 'labour') {
            $data['expectedLabour'] = $ops->expectedLabour($jobId);
            $entries = $ops->timeEntries($jobId);
            if (!can('time.view_all') && !can('costing.view')) {
                $entries = array_values(array_filter(
                    $entries,
                    static fn (array $row): bool => (int) $row['user_id'] === $userId
                ));
            }
            $data['entries'] = $entries;
            $data['tasks'] = $ops->tasks($jobId);
            $data['workTypes'] = WorkType::cases();
            $data['timer'] = $ops->openTimer($userId);
            $data['labour'] = can('costing.view') ? ((new JobCostingService())->report($jobId)['labour'] ?? null) : null;
            $data['showCost'] = can('costing.view');
        } elseif ($tab === 'installation') {
            $rows = $ops->installations($jobId);
            $lists = [];
            foreach ($rows as $row) {
                $lists[(int) $row['id']] = $ops->checklist((int) $row['id']);
            }
            $data['installations'] = $rows;
            $data['checklists'] = $lists;
            $data['teams'] = $ops->teams();
            $data['photos'] = [];
            foreach ($rows as $row) {
                $data['photos'][(int) $row['id']] = (new AttachmentRepository())->forEntity('installation', (int) $row['id']);
            }
        } elseif ($tab === 'files') {
            $data['files'] = (new AttachmentRepository())->forEntity('job', $jobId);
        } elseif ($tab === 'costing') {
            if (!can('costing.view')) {
                deny_access('Your role cannot see job costing.');
            }
            $data['costing'] = (new JobCostingService())->report($jobId);
            $data['usage'] = $ops->usage($jobId);
            $data['entries'] = can('time.view_all') || can('costing.view') ? $ops->timeEntries($jobId) : [];
            $data['others'] = $ops->otherCosts($jobId);
            $data['costTypes'] = OtherCostType::cases();
            $data['suppliers'] = (new SupplierRepository())->options();
        } elseif ($tab === 'activity') {
            $data['timeline'] = (new JobRepository())->timeline($jobId);
        }
        View::render('jobs/show', $data);
    }

    public function save(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->saveOverview((int) $job['id'], $_POST, (int) ($_POST['version_number'] ?? 0), $this->userId());
        }, (int) $job['id'], 'overview', 'Job saved.');
    }

    public function status(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->changeStatus(
                (int) $job['id'],
                (string) ($_POST['status'] ?? ''),
                (int) ($_POST['version_number'] ?? 0),
                $this->userId(),
                (string) ($_POST['notes'] ?? ''),
                (string) ($_POST['override_reason'] ?? '')
            );
        }, (int) $job['id'], 'overview', 'Status updated.');
    }

    public function task(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->addTask((int) $job['id'], $_POST, $this->userId());
        }, (int) $job['id'], 'tasks', 'Task added.');
    }

    public function taskStatus(string $id, string $taskId): void
    {
        $job = $this->job($id);
        $back = (string) ($_POST['back'] ?? 'tasks');
        $this->act(function () use ($job, $taskId): array {
            return (new JobService())->updateTask(
                (int) $job['id'],
                route_id($taskId),
                (string) ($_POST['status'] ?? ''),
                (int) ($_POST['version_number'] ?? 0),
                $this->userId()
            );
        }, (int) $job['id'], $back === 'workshop' ? 'tasks' : $back, 'Task updated.');
    }

    public function route(string $id): void
    {
        $job = $this->job($id);
        $item = (int) ($_POST['job_item_id'] ?? 0);
        $this->act(function () use ($job, $item): array {
            return (new JobService())->applyTemplate((int) $job['id'], (int) ($_POST['template_id'] ?? 0), $item > 0 ? $item : null, $this->userId());
        }, (int) $job['id'], 'production', 'Production route applied.');
    }

    public function stage(string $id, string $stageId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $stageId): array {
            return (new JobService())->updateStage((int) $job['id'], route_id($stageId), (string) ($_POST['status'] ?? ''), $this->userId());
        }, (int) $job['id'], 'production', 'Stage updated.');
    }

    public function itemStatus(string $id, string $itemId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $itemId): array {
            return (new JobService())->updateItemStatus((int) $job['id'], route_id($itemId), (string) ($_POST['status'] ?? ''));
        }, (int) $job['id'], 'production', 'Item updated.');
    }

    public function artwork(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->storeArtwork((int) $job['id'], $_FILES['file'] ?? [], $_POST, $this->userId());
        }, (int) $job['id'], 'artwork', 'Proof stored. Older revisions of this title stay available.');
    }

    public function artworkStatus(string $id, string $artworkId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $artworkId): array {
            return (new JobService())->setArtworkStatus(
                (int) $job['id'],
                route_id($artworkId),
                (string) ($_POST['status'] ?? ''),
                (int) ($_POST['version_number'] ?? 0)
            );
        }, (int) $job['id'], 'artwork', 'Proof status updated.');
    }

    public function approval(string $id, string $artworkId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $artworkId): array {
            return (new JobService())->recordApproval((int) $job['id'], route_id($artworkId), $_POST, $this->userId());
        }, (int) $job['id'], 'artwork', 'Customer approval recorded. This is a staff record, not a digital signature.');
    }

    public function artworkFile(string $id, string $artworkId): void
    {
        $job = $this->job($id);
        $art = (new OperationsRepository())->artwork(route_id($artworkId));
        if ($art === null || (int) $art['job_id'] !== (int) $job['id']) {
            abort_not_found('That proof was not found.');
        }
        $relative = (string) $art['stored_filename'];
        if (str_contains($relative, '..')) {
            abort_not_found('That proof was not found.');
        }
        $path = base_path('storage/uploads/' . $relative);
        if (!is_file($path)) {
            abort_not_found('That proof file is missing.');
        }
        header('Content-Type: ' . (string) $art['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) $art['original_filename']) . '"');
        readfile($path);
        exit;
    }

    public function material(string $id): void
    {
        $job = $this->job($id);
        $result = (new JobService())->recordMaterial((int) $job['id'], $_POST, $this->userId());
        $this->finish($result['errors'], (int) $job['id'], 'materials', 'Material recorded.');
    }

    public function requirement(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->addRequirement((int) $job['id'], $_POST, $this->userId());
        }, (int) $job['id'], 'materials', 'Requirement added.');
    }

    public function time(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->addTime((int) $job['id'], $_POST, $this->userId());
        }, (int) $job['id'], 'labour', 'Time recorded.');
    }

    public function timerStart(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->startTimer((int) $job['id'], $_POST, $this->userId());
        }, (int) $job['id'], 'labour', 'Timer started.');
    }

    public function timerStop(string $id): void
    {
        $job = $this->job($id);
        $this->act(function (): array {
            return (new JobService())->stopTimer($this->userId());
        }, (int) $job['id'], 'labour', 'Timer stopped.');
    }

    public function other(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->addOtherCost((int) $job['id'], $_POST, $this->userId());
        }, (int) $job['id'], 'costing', 'Cost recorded.');
    }

    public function quality(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->recordQuality((int) $job['id'], $_POST, $this->userId());
        }, (int) $job['id'], 'production', 'Quality check recorded.');
    }

    public function installation(string $id): void
    {
        $job = $this->job($id);
        $result = (new JobService())->scheduleInstallation((int) $job['id'], $_POST, $this->userId());
        $this->finish($result['errors'], (int) $job['id'], 'installation', 'Installation scheduled.');
    }

    public function installationUpdate(string $id, string $installationId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $installationId): array {
            return (new JobService())->updateInstallation(
                (int) $job['id'],
                route_id($installationId),
                $_POST,
                (int) ($_POST['version_number'] ?? 0),
                $this->userId()
            );
        }, (int) $job['id'], 'installation', 'Installation updated.');
    }

    public function checklist(string $id, string $installationId, string $itemId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $installationId, $itemId): array {
            return (new JobService())->toggleChecklist(
                (int) $job['id'],
                route_id($installationId),
                route_id($itemId),
                isset($_POST['checked']),
                $this->userId()
            );
        }, (int) $job['id'], 'installation', 'Checklist updated.');
    }

    public function photo(string $id, string $installationId): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job, $installationId): array {
            return (new JobService())->completionPhoto(
                (int) $job['id'],
                route_id($installationId),
                $_FILES['file'] ?? [],
                (string) ($_POST['notes'] ?? ''),
                $this->userId()
            );
        }, (int) $job['id'], 'installation', 'Photo stored.');
    }

    public function jobFile(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new \App\Services\AttachmentService())->store(
                'job',
                (int) $job['id'],
                $_FILES['file'] ?? [],
                $this->userId(),
                (string) ($_POST['purpose'] ?? 'GENERAL'),
                (string) ($_POST['notes'] ?? '')
            );
        }, (int) $job['id'], 'files', 'File stored.');
    }

    public function archive(string $id): void
    {
        $job = $this->job($id);
        $this->act(function () use ($job): array {
            return (new JobService())->archive((int) $job['id'], empty($_POST['restore']), $this->userId());
        }, (int) $job['id'], 'overview', empty($_POST['restore']) ? 'Job archived.' : 'Job restored to the active list.');
    }

    public function card(string $id): void
    {
        $job = $this->job($id);
        $showCost = can('costing.view') && (string) ($_GET['costing'] ?? '') === '1';
        View::render('jobs/card', (new \App\Services\WorkshopDocumentService())->cardData((int) $job['id'], $this->userId(), $showCost), null);
    }

    public function cardPdf(string $id): void
    {
        $job = $this->job($id);
        $showCost = can('costing.view') && (string) ($_GET['costing'] ?? '') === '1';
        $built = (new \App\Services\WorkshopDocumentService())->jobCard((int) $job['id'], $this->userId(), $showCost);
        $binary = (new QuotePdf())->render($built['html']);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $job['job_number']) ?: 'job';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $name . '-job-card.pdf"');
        header('X-Content-Type-Options: nosniff');
        echo $binary;
        exit;
    }

    public function board(): void
    {
        $columns = [
            'READY_FOR_PRODUCTION' => 'Ready',
            'IN_PRODUCTION' => 'In production',
            'QUALITY_CONTROL' => 'QC',
            'READY_FOR_INSTALLATION' => 'Ready for installation',
            'COMPLETED' => 'Completed',
        ];
        $grouped = [];
        foreach (array_keys($columns) as $status) {
            $grouped[$status] = [];
        }
        foreach ((new JobRepository())->board() as $job) {
            $status = (string) $job['status'];
            if ($status === 'INSTALLATION_SCHEDULED') {
                $status = 'READY_FOR_INSTALLATION';
            }
            if (isset($grouped[$status])) {
                $grouped[$status][] = $job;
            }
        }
        View::render('jobs/board', [
            'title' => 'Production board',
            'activeNav' => 'board',
            'columns' => $columns,
            'grouped' => $grouped,
        ]);
    }

    public function workshop(): void
    {
        View::render('jobs/workshop', [
            'title' => 'Workshop',
            'activeNav' => 'workshop',
            'rows' => (new OperationsRepository())->workshop(),
        ]);
    }

    public function design(): void
    {
        View::render('jobs/design', [
            'title' => 'Design queue',
            'activeNav' => 'design',
            'rows' => (new OperationsRepository())->designQueue(),
        ]);
    }

    public function installations(): void
    {
        $today = [];
        $upcoming = [];
        $returns = [];
        $todayDate = date('Y-m-d');
        foreach ((new OperationsRepository())->installationBoard() as $row) {
            if ((string) $row['status'] === 'RETURN_REQUIRED') {
                $returns[] = $row;
            } elseif ((string) ($row['scheduled_date'] ?? '') === $todayDate) {
                $today[] = $row;
            } elseif ((string) ($row['scheduled_date'] ?? '') > $todayDate || (string) ($row['scheduled_date'] ?? '') === '') {
                $upcoming[] = $row;
            }
        }
        View::render('jobs/installations', [
            'title' => 'Installations',
            'activeNav' => 'installations',
            'today' => $today,
            'upcoming' => $upcoming,
            'returns' => $returns,
        ]);
    }

    public function schedule(): void
    {
        $today = [];
        $week = [];
        $overdue = [];
        $upcoming = [];
        $todayDate = date('Y-m-d');
        $weekEnd = date('Y-m-d', strtotime('+7 days'));
        foreach ((new OperationsRepository())->schedule() as $row) {
            $due = (string) $row['due_date'];
            if ($due < $todayDate) {
                $overdue[] = $row;
            } elseif ($due === $todayDate) {
                $today[] = $row;
            } elseif ($due <= $weekEnd) {
                $week[] = $row;
            } else {
                $upcoming[] = $row;
            }
        }
        View::render('jobs/schedule', [
            'title' => 'Production schedule',
            'activeNav' => 'schedule',
            'groups' => [
                'Overdue' => $overdue,
                'Today' => $today,
                'This week' => $week,
                'Upcoming' => $upcoming,
            ],
        ]);
    }

    public function setup(): void
    {
        $ops = new OperationsRepository();
        View::render('jobs/setup', [
            'title' => 'Production setup',
            'activeNav' => 'setup',
            'stages' => $ops->catalogueStages(),
            'templates' => $ops->templates(),
            'templateStages' => array_map(
                static fn (array $template): array => $ops->templateStages((int) $template['id']),
                $ops->templates()
            ),
            'teams' => $ops->teams(),
            'staff' => (new UserRepository())->listAll(),
            'memberships' => $ops->teamMemberships(),
            'errors' => [],
        ]);
    }

    public function saveStage(): void
    {
        $errors = (new JobService())->saveCatalogueStage($_POST);
        $this->setupBack($errors, 'Stage added.');
    }

    public function saveTemplate(): void
    {
        $errors = (new JobService())->saveTemplate($_POST);
        $this->setupBack($errors, 'Route template saved. Jobs keep their own copy when you apply it.');
    }

    public function saveTeam(): void
    {
        $errors = (new JobService())->setTeamMember(
            (int) ($_POST['team_id'] ?? 0),
            (int) ($_POST['user_id'] ?? 0),
            isset($_POST['member'])
        );
        $this->setupBack($errors, 'Team updated.');
    }

    /**
     * @return array<string, string>
     */
    private function tabs(): array
    {
        $tabs = [
            'overview' => 'Overview',
            'artwork' => 'Artwork',
            'tasks' => 'Tasks',
            'production' => 'Production',
            'materials' => 'Materials',
            'labour' => 'Time & labour',
            'installation' => 'Installation',
            'files' => 'Files',
            'costing' => 'Costing',
            'finance' => 'Finance',
            'activity' => 'Activity',
        ];
        if (!can('invoices.view')) {
            unset($tabs['finance']);
        }
        if (!can('materials.view') && !can('materials.record_usage')) {
            unset($tabs['materials']);
        }
        if (!can('time.record') && !can('time.view_all') && !can('costing.view')) {
            unset($tabs['labour']);
        }
        if (!can('installations.view') && !can('installations.schedule') && !can('installations.complete')) {
            unset($tabs['installation']);
        }
        if (!can('costing.view')) {
            unset($tabs['costing']);
        }
        if (!can('production.view') && !can('production.update')) {
            unset($tabs['production']);
        }

        return $tabs;
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(string $id): array
    {
        $job = $this->job($id);
        $ops = new OperationsRepository();
        $jobId = (int) $job['id'];

        return [
            'job' => $job,
            'items' => $ops->items($jobId),
            'requirements' => $ops->requirements($jobId),
            'artworks' => $ops->artworks($jobId),
            'stages' => $ops->stages($jobId),
            'company' => SettingsService::get('company_name', 'Sign-Forge Signs'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function job(string $id): array
    {
        $job = (new JobRepository())->find(route_id($id));
        if ($job === null) {
            abort_not_found('That job was not found.');
        }

        return $job;
    }

    private function userId(): int
    {
        return (int) auth_user()['id'];
    }

    /**
     * @param callable(): array<string, string> $action
     */
    private function act(callable $action, int $jobId, string $tab, string $success): void
    {
        try {
            $errors = $action();
        } catch (JobConflictException $e) {
            flash('error', $e->getMessage());
            redirect('/jobs/' . $jobId . '?tab=' . $tab);
        }
        $this->finish($errors, $jobId, $tab, $success);
    }

    /**
     * @param array<string, string> $errors
     */
    private function finish(array $errors, int $jobId, string $tab, string $success): void
    {
        if ($errors !== []) {
            flash('error', (string) reset($errors));
        } else {
            flash('success', $success);
        }
        $back = (string) ($_POST['back'] ?? '');
        if ($back === 'workshop') {
            redirect('/jobs/workshop');
        }
        redirect('/jobs/' . $jobId . '?tab=' . $tab);
    }

    /**
     * @param array<string, string> $errors
     */
    private function setupBack(array $errors, string $success): void
    {
        flash($errors === [] ? 'success' : 'error', $errors === [] ? $success : (string) reset($errors));
        redirect('/jobs/setup');
    }
}
