<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\AttachmentService;
use App\Services\ProjectAccess;
use App\Services\ProjectCloseoutService;
use App\Services\ProjectMilestoneService;
use App\Services\ProjectService;
use App\Services\ProjectSiteService;

/**
 * Project workspace. Operational work stays on the job screens.
 */
final class ProjectController
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'health' => strtoupper(trim((string) ($_GET['health'] ?? ''))),
            'manager' => (int) ($_GET['manager'] ?? 0),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'type_id' => (int) ($_GET['type'] ?? 0),
            'salesperson' => (int) ($_GET['salesperson'] ?? 0),
        ];
        $access = new ProjectAccess();
        $user = auth_user();
        $repo = new ProjectRepository();
        $money = $access->canViewFinancials($user);
        View::render('projects/index', [
            'title' => 'Projects',
            'activeNav' => 'projects',
            'rows' => $repo->search($filters, 100, 0, $access->listUserId($user)),
            'portfolio' => $repo->portfolio($money),
            'filters' => $filters,
            'types' => $repo->types(),
            'staff' => (new UserRepository())->listAll(),
            'showMoney' => $money,
            'canCreate' => can('projects.create'),
            'upcoming' => $access->listUserId($user) === null ? $repo->dueMilestones(14) : [],
        ]);
    }

    public function createForm(): void
    {
        if (!can('projects.create')) {
            deny_access('You cannot create a project.');
        }
        $repo = new ProjectRepository();
        View::render('projects/form', [
            'title' => 'New project',
            'activeNav' => 'projects',
            'types' => $repo->types(),
            'templates' => $repo->templates(),
            'staff' => (new UserRepository())->listAll(),
            'old' => [
                'customer_id' => (int) ($_GET['customer_id'] ?? 0),
                'source_opportunity_id' => (int) ($_GET['opportunity_id'] ?? 0),
                'source_quote_id' => (int) ($_GET['quote_id'] ?? 0),
                'commercial_mode' => 'PROJECT',
                'priority' => 'NORMAL',
            ],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!can('projects.create')) {
            deny_access('You cannot create a project.');
        }
        $result = (new ProjectService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $repo = new ProjectRepository();
            View::render('projects/form', [
                'title' => 'New project',
                'activeNav' => 'projects',
                'types' => $repo->types(),
                'templates' => $repo->templates(),
                'staff' => (new UserRepository())->listAll(),
                'old' => $_POST,
                'errors' => $result['errors'],
            ]);

            return;
        }
        redirect('/projects/' . $result['id']);
    }

    public function show(string $id): void
    {
        $projectId = route_id($id);
        $this->open($projectId);
        $tab = (string) ($_GET['tab'] ?? 'overview');
        $allowed = ['overview', 'sites', 'jobs', 'milestones', 'gantt', 'calendar', 'financials', 'documents', 'contacts', 'risks', 'activity'];
        if (!in_array($tab, $allowed, true)) {
            $tab = 'overview';
        }
        if ($tab === 'financials' && !(new ProjectAccess())->canViewFinancials(auth_user())) {
            deny_access('You cannot view project financials.');
        }
        $service = new ProjectService();
        $data = $service->workspace($projectId);
        $repo = new ProjectRepository();
        $scale = strtoupper((string) ($_GET['scale'] ?? 'WEEK'));
        if (!in_array($scale, ['DAY', 'WEEK', 'MONTH'], true)) {
            $scale = 'WEEK';
        }
        View::render('projects/show', [
            'title' => (string) $data['project']['project_number'],
            'activeNav' => 'projects',
            'tab' => $tab,
            'scale' => $scale,
            'showMoney' => (new ProjectAccess())->canViewFinancials(auth_user()),
            'gantt' => $repo->gantt($projectId),
            'calendar' => $repo->calendar($projectId, date('Y-m-01'), date('Y-m-t', strtotime('+2 months'))),
            'map' => $repo->mapSites($projectId, 200),
            'activity' => $repo->activity($projectId, strtoupper((string) ($_GET['filter'] ?? 'ALL'))),
            'activityFilter' => strtoupper((string) ($_GET['filter'] ?? 'ALL')),
            'closeout' => (new ProjectCloseoutService())->review($projectId),
            'reasons' => $repo->delayReasons(),
            'staff' => (new UserRepository())->listAll(),
            'canEdit' => can('projects.edit'),
            'canSites' => can('projects.manage_sites'),
            'canImport' => can('projects.import_sites'),
            'canJobs' => can('projects.bulk_create_jobs'),
            'canMilestones' => can('projects.manage_milestones'),
            'canRisks' => can('projects.manage_risks'),
            'canIssues' => can('projects.manage_issues'),
            'canBudget' => can('projects.manage_budget'),
            'canChanges' => can('projects.manage_changes'),
            'canComplete' => can('projects.complete'),
            'canHandover' => can('projects.generate_handover'),
            'canTeam' => can('projects.manage_team'),
            'assetCount' => can('assets.view') ? (new \App\Repositories\AssetRepository())->countForProject($projectId) : 0,
            'assets' => can('assets.view') ? (new \App\Repositories\AssetRepository())->forProject($projectId, 30) : [],
            ...$data,
        ]);
    }

    public function update(string $id): void
    {
        if (!can('projects.edit')) {
            deny_access('You cannot edit this project.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $errors = (new ProjectService())->update($projectId, $_POST, (int) ($_POST['version'] ?? 0), (int) auth_user()['id']);
        $this->back($projectId, $errors, 'overview');
    }

    public function shiftDate(string $id): void
    {
        if (!can('projects.edit')) {
            deny_access('You cannot change the project date.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $errors = (new ProjectService())->shiftTarget(
            $projectId,
            (string) ($_POST['current_target_date'] ?? ''),
            (string) ($_POST['reason_code'] ?? 'OTHER'),
            (string) ($_POST['notes'] ?? ''),
            (int) ($_POST['version'] ?? 0),
            (int) auth_user()['id']
        );
        $this->back($projectId, $errors, 'overview');
    }

    public function addSite(string $id): void
    {
        if (!can('projects.manage_sites')) {
            deny_access('You cannot add sites.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $result = (new ProjectSiteService())->add($projectId, $_POST, (int) auth_user()['id']);
        $this->back($projectId, $result['errors'], 'sites');
    }

    public function importForm(string $id): void
    {
        if (!can('projects.import_sites')) {
            deny_access('You cannot import sites.');
        }
        $projectId = route_id($id);
        $project = $this->open($projectId);
        View::render('projects/import', [
            'title' => 'Import sites',
            'activeNav' => 'projects',
            'project' => $project,
            'report' => null,
        ]);
    }

    public function import(string $id): void
    {
        if (!can('projects.import_sites')) {
            deny_access('You cannot import sites.');
        }
        $projectId = route_id($id);
        $project = $this->open($projectId);
        $rows = $this->parseSites((string) ($_POST['csv'] ?? ''));
        $confirm = (string) ($_POST['confirm'] ?? '') === '1';
        $report = (new ProjectSiteService())->import($projectId, $rows, $confirm, (int) auth_user()['id']);
        if ($report['committed']) {
            flash('success', $report['imported'] . ' sites imported.');
            redirect('/projects/' . $projectId . '?tab=sites');
        }
        View::render('projects/import', [
            'title' => 'Import sites',
            'activeNav' => 'projects',
            'project' => $project,
            'report' => $report,
            'csv' => (string) ($_POST['csv'] ?? ''),
        ]);
    }

    public function bulkJobs(string $id): void
    {
        if (!can('projects.bulk_create_jobs')) {
            deny_access('You cannot create rollout jobs.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $ids = array_map('intval', (array) ($_POST['site_ids'] ?? []));
        $result = (new ProjectSiteService())->createDraftJobs(
            $projectId,
            $ids,
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['confirm'] ?? '') === '1',
            (int) auth_user()['id']
        );
        if ($result['errors'] === [] ) {
            flash('success', count($result['job_ids']) . ' draft jobs created. They are not released to production.');
        }
        $this->back($projectId, $result['errors'], 'jobs');
    }

    public function addWave(string $id): void
    {
        if (!can('projects.manage_sites')) {
            deny_access('You cannot add a wave.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectService())->addWave($projectId, $_POST, (int) auth_user()['id']), 'sites');
    }

    public function addMilestone(string $id): void
    {
        if (!can('projects.manage_milestones')) {
            deny_access('You cannot add milestones.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectMilestoneService())->add($projectId, $_POST, (int) auth_user()['id']), 'milestones');
    }

    public function milestoneStatus(string $id, string $milestoneId): void
    {
        if (!can('projects.manage_milestones')) {
            deny_access('You cannot update milestones.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $errors = (new ProjectMilestoneService())->setStatus(route_id($milestoneId), (string) ($_POST['status'] ?? ''), (int) ($_POST['version'] ?? 0), (int) auth_user()['id']);
        (new ProjectService())->refreshCache($projectId);
        $this->back($projectId, $errors, 'milestones');
    }

    public function addRisk(string $id): void
    {
        if (!can('projects.manage_risks')) {
            deny_access('You cannot add risks.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectService())->addRisk($projectId, $_POST, (int) auth_user()['id']), 'risks');
    }

    public function addIssue(string $id): void
    {
        if (!can('projects.manage_issues')) {
            deny_access('You cannot add issues.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectService())->addIssue($projectId, $_POST, (int) auth_user()['id']), 'risks');
    }

    public function issueStatus(string $id, string $issueId): void
    {
        if (!can('projects.manage_issues')) {
            deny_access('You cannot update issues.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectService())->resolveIssue(route_id($issueId), (string) ($_POST['status'] ?? ''), (int) ($_POST['version'] ?? 0), (int) auth_user()['id']), 'risks');
    }

    public function addNote(string $id): void
    {
        if (!can('projects.edit')) {
            deny_access('You cannot add notes.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body !== '') {
            $visibility = strtoupper((string) ($_POST['visibility'] ?? 'INTERNAL')) === 'CUSTOMER' ? 'CUSTOMER' : 'INTERNAL';
            (new ProjectRepository())->insertNote($projectId, null, $visibility, $body, (int) auth_user()['id']);
        }
        redirect('/projects/' . $projectId . '?tab=overview');
    }

    public function addChange(string $id): void
    {
        if (!can('projects.manage_changes')) {
            deny_access('You cannot raise a project change.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectService())->proposeChange($projectId, $_POST, (int) auth_user()['id']), 'financials');
    }

    public function decideChange(string $id, string $changeId): void
    {
        if (!can('projects.manage_changes')) {
            deny_access('You cannot decide a project change.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $this->back($projectId, (new ProjectService())->decideChange(route_id($changeId), (string) ($_POST['decision'] ?? ''), (int) ($_POST['version'] ?? 0), (int) auth_user()['id']), 'financials');
    }

    public function saveBudget(string $id): void
    {
        if (!can('projects.manage_budget')) {
            deny_access('You cannot edit the project budget.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $category = strtoupper(trim((string) ($_POST['category'] ?? 'OTHER')));
        $amount = trim((string) ($_POST['amount'] ?? ''));
        if ($category !== '' && $amount !== '') {
            (new ProjectRepository())->saveBudget($projectId, mb_substr($category, 0, 40), $amount, null);
            (new ProjectService())->refreshCache($projectId);
        }
        redirect('/projects/' . $projectId . '?tab=financials');
    }

    public function addCost(string $id): void
    {
        if (!can('projects.manage_budget')) {
            deny_access('You cannot add a project cost.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $amount = trim((string) ($_POST['amount'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($description !== '' && $amount !== '') {
            (new ProjectRepository())->insertCost(
                $projectId,
                strtoupper(trim((string) ($_POST['category'] ?? 'OTHER'))) ?: 'OTHER',
                mb_substr($description, 0, 180),
                $amount,
                preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['cost_date'] ?? '')) ? (string) $_POST['cost_date'] : date('Y-m-d'),
                (int) auth_user()['id']
            );
            (new ProjectService())->refreshCache($projectId);
        }
        redirect('/projects/' . $projectId . '?tab=financials');
    }

    public function complete(string $id): void
    {
        if (!can('projects.complete')) {
            deny_access('You cannot complete this project.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $errors = (new ProjectCloseoutService())->complete(
            $projectId,
            (string) ($_POST['notes'] ?? ''),
            (int) auth_user()['id'],
            (string) ($_POST['acknowledge'] ?? '') === '1'
        );
        $this->back($projectId, $errors, 'overview');
    }

    public function handover(string $id): void
    {
        if (!can('projects.generate_handover')) {
            deny_access('You cannot generate a handover pack.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $pack = (new ProjectCloseoutService())->handover($projectId);
        View::render('projects/handover', [
            'title' => 'Handover ' . ($pack['project_number'] ?? ''),
            'activeNav' => 'projects',
            'pack' => $pack,
        ]);
    }

    public function upload(string $id): void
    {
        if (!can('projects.edit')) {
            deny_access('You cannot add project documents.');
        }
        $projectId = route_id($id);
        $this->open($projectId);
        $stored = (new AttachmentService())->store('project', $projectId, $_FILES['file'] ?? [], (int) auth_user()['id'], (string) ($_POST['category'] ?? 'OTHER'));
        $latest = (new \App\Repositories\AttachmentRepository())->forEntity('project', $projectId);
        $attachment = $latest[0] ?? null;
        if ($stored === [] && $attachment !== null) {
            (new ProjectRepository())->recordDocument(
                $projectId,
                null,
                (int) $attachment['id'],
                strtoupper(trim((string) ($_POST['category'] ?? 'OTHER'))) ?: 'OTHER',
                (string) ($_POST['share_with_jobs'] ?? '') === '1',
                (string) ($_POST['customer_visible'] ?? '') === '1'
            );
        } else {
            flash('error', 'The file was not stored.');
        }
        redirect('/projects/' . $projectId . '?tab=documents');
    }

    public function report(string $slug): void
    {
        if (!can('projects.view_reports') && !can('projects.view')) {
            deny_access('You cannot view project reports.');
        }
        $slug = strtolower($slug);
        $known = ['summary', 'progress', 'sites', 'rollout', 'profitability', 'cost-variance', 'timeline', 'installations', 'snags', 'materials', 'purchasing', 'financials', 'delays'];
        if (!in_array($slug, $known, true)) {
            abort_not_found('That report was not found.');
        }
        if (in_array($slug, ['profitability', 'cost-variance', 'financials'], true) && !(new ProjectAccess())->canViewFinancials(auth_user())) {
            deny_access('You cannot view project financials.');
        }
        $repo = new ProjectRepository();
        View::render('projects/report', [
            'title' => 'Project ' . str_replace('-', ' ', $slug),
            'activeNav' => 'projects',
            'slug' => $slug,
            'rows' => $repo->search([], 200, 0, (new ProjectAccess())->listUserId(auth_user())),
            'showMoney' => (new ProjectAccess())->canViewFinancials(auth_user()),
        ]);
    }

    public function mobileIndex(): void
    {
        $access = new ProjectAccess();
        $rows = (new ProjectRepository())->search([], 30, 0, $access->listUserId(auth_user()));
        View::render('mobile/projects', [
            'title' => 'My projects',
            'rows' => $rows,
        ], 'layouts/app');
    }

    public function mobileProject(string $id): void
    {
        $projectId = route_id($id);
        $this->open($projectId);
        View::render('mobile/project', [
            'title' => 'Project sites',
            'project' => (new ProjectRepository())->find($projectId),
            'sites' => (new ProjectRepository())->sites($projectId),
        ]);
    }

    public function site(string $id): void
    {
        $site = (new ProjectRepository())->site(route_id($id));
        if ($site === null) {
            abort_not_found('That site was not found.');
        }
        $this->open((int) $site['project_id']);
        $repo = new ProjectRepository();
        View::render('projects/site', [
            'title' => (string) $site['site_code'],
            'activeNav' => 'projects',
            'site' => $site,
            'jobs' => $repo->jobs((int) $site['project_id'], (int) $site['id']),
            'pack' => (new ProjectSiteService())->fieldPack((int) $site['id']),
            'showMoney' => (new ProjectAccess())->canViewFinancials(auth_user()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function open(int $projectId): array
    {
        $project = (new ProjectRepository())->find($projectId);
        if ($project === null) {
            abort_not_found('That project was not found.');
        }
        if (!(new ProjectAccess())->canOpen(auth_user(), $projectId)) {
            deny_access('You cannot open that project.');
        }

        return $project;
    }

    /**
     * @param array<string, string> $errors
     */
    private function back(int $projectId, array $errors, string $tab): void
    {
        if ($errors !== []) {
            flash('error', (string) reset($errors));
        }
        redirect('/projects/' . $projectId . '?tab=' . $tab);
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseSites(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        if ($lines === [] || trim((string) $lines[0]) === '') {
            return [];
        }
        $header = str_getcsv((string) array_shift($lines));
        $map = [];
        foreach ($header as $index => $column) {
            $key = strtolower(trim((string) $column));
            $key = match ($key) {
                'branch code', 'code' => 'site_code',
                'branch', 'name' => 'site_name',
                'address', 'street' => 'address_line_1',
                'contact' => 'contact_name',
                'phone' => 'contact_phone',
                'email' => 'contact_email',
                'target', 'target date' => 'target_date',
                'region', 'group' => 'group_label',
                'reference', 'branch reference' => 'customer_site_reference',
                default => str_replace([' ', '-'], '_', $key),
            };
            $map[$index] = $key;
        }
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $row = [];
            foreach ($cells as $index => $value) {
                $key = $map[$index] ?? ('column_' . $index);
                $row[$key] = trim((string) $value);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
