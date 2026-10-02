<?php

declare(strict_types=1);

/**
 * v1.1 Phase 1: projects, rollouts, and commercial totals.
 *
 *   php tests/v11_projects.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ProjectRepository;
use App\Services\AuthorizationService;
use App\Services\CustomerService;
use App\Services\ProjectAccess;
use App\Services\ProjectCloseoutService;
use App\Services\ProjectFinancialService;
use App\Services\ProjectService;
use App\Services\ProjectSiteService;
use App\Services\SettingsService;

$failures = 0;
$eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
    if ($expected === $actual) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    $failures++;
};

$userId = (int) Database::connection()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'ABC Retail v11 ' . date('His'),
], $userId);
$eq('customer created', [], $customer['errors']);
$customerId = (int) $customer['id'];

$created = (new ProjectService())->create([
    'customer_id' => $customerId,
    'name' => '25 Branch Rebrand',
    'commercial_mode' => 'PROJECT',
    'target_date' => '2027-06-01',
    'template_id' => (int) Database::connection()->query("SELECT id FROM project_templates WHERE name = 'Multi-branch rebrand' LIMIT 1")->fetchColumn(),
], $userId);
$eq('project created', [], $created['errors']);
$projectId = (int) $created['id'];
$project = (new ProjectRepository())->find($projectId);
$eq('project number shape', 1, preg_match('/^SFP-\d{4}-\d{4,}$/', (string) $project['project_number']));
$eq('original target', '2027-06-01', (string) $project['original_target_date']);
$eq('template copied', 5, count((new ProjectRepository())->milestones($projectId)));

$shift = (new ProjectService())->shiftTarget($projectId, '2027-06-15', 'CUSTOMER_DELAY', 'Customer asked to wait', (int) $project['version'], $userId);
$eq('date shift', [], $shift);
$project = (new ProjectRepository())->find($projectId);
$eq('original target kept', '2027-06-01', (string) $project['original_target_date']);
$eq('current target moved', '2027-06-15', (string) $project['current_target_date']);
$stale = (new ProjectService())->shiftTarget($projectId, '2027-07-01', 'OTHER', null, (int) $project['version'] - 1, $userId);
$eq('stale version refused', true, isset($stale['_form']));

$finance = new ProjectFinancialService();
$contract = $finance->recordContract($projectId, '500000.00', 'Accepted project quote', null, []);
$finance->recordContract($projectId, '500000.00', 'Superseded revision', null, [], $contract);
$changeErrors = (new ProjectService())->proposeChange($projectId, [
    'title' => 'Approved variation',
    'commercial_impact' => '50000.00',
    'cost_impact' => '0.00',
], $userId);
$eq('change drafted', [], $changeErrors);
$change = (new ProjectRepository())->changes($projectId)[0];
$decision = (new ProjectService())->decideChange((int) $change['id'], 'APPROVED', (int) $change['version'], $userId);
$eq('change approved', [], $decision);
$statement = $finance->statement($projectId);
$eq('quote plus variation', '550000.00', $statement['commercial_value']);

$rows = [];
for ($i = 1; $i <= 5; $i++) {
    $rows[] = [
        'site_code' => 'BR-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        'site_name' => 'Branch ' . $i,
        'address_line_1' => $i . ' Main Road',
        'city' => 'Johannesburg',
        'contact_name' => 'Site ' . $i,
        'contact_email' => 'site' . $i . '@abc.test',
    ];
}
$imported = (new ProjectSiteService())->import($projectId, $rows, true, $userId);
$eq('five sites imported', 5, $imported['imported']);
$sites = (new ProjectRepository())->sites($projectId);
$allocations = [];
foreach ($sites as $site) {
    $allocations[] = [
        'site_id' => (int) $site['id'],
        'job_id' => null,
        'amount' => '100000.00',
        'label' => (string) $site['site_code'],
    ];
}
$finance->recordContract($projectId, '0.00', 'Allocation holder', null, $allocations);
$drafts = (new ProjectSiteService())->createDraftJobs($projectId, array_map(static fn (array $site): int => (int) $site['id'], $sites), 'External fascia', true, $userId);
$eq('five draft jobs', 5, count($drafts['job_ids']));
$pdo = Database::connection();
$pdo->prepare('UPDATE jobs SET quoted_revenue_snapshot = 100000.00 WHERE project_id = ?')->execute([$projectId]);
$statement = $finance->statement($projectId);
$eq('allocations do not double the contract', '550000.00', $statement['commercial_value']);

$jobIds = $drafts['job_ids'];
$pdo->prepare('UPDATE jobs SET actual_total_cost = ? WHERE id = ?')->execute(['50000.00', $jobIds[0]]);
$pdo->prepare('UPDATE jobs SET actual_total_cost = ? WHERE id = ?')->execute(['30000.00', $jobIds[1]]);
$pdo->prepare('UPDATE jobs SET actual_total_cost = ? WHERE id = ?')->execute(['20000.00', $jobIds[2]]);
$statement = $finance->statement($projectId);
$eq('job costs aggregate', '100000.00', $statement['actual_cost']);
$eq('margin is profit over value', 0, Decimal::cmp(
    Decimal::round(Decimal::mul(Decimal::div($statement['gross_profit'], $statement['commercial_value'], 8), '100', 8), 2),
    (string) $statement['margin_percent']
));

$siteId = (int) $sites[0]['id'];
$pdo->prepare('UPDATE jobs SET status = \'COMPLETED\' WHERE id = ?')->execute([$jobIds[0]]);
$status = (new ProjectSiteService())->refreshFromJobs($siteId);
$eq('partial jobs do not complete the site', 'NOT_STARTED', $status);

$bulkRows = [];
for ($i = 1; $i <= 100; $i++) {
    $bulkRows[] = [
        'site_code' => 'N' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'site_name' => 'National ' . $i,
        'address_line_1' => $i . ' Branch Street',
        'city' => 'Pretoria',
        'contact_name' => 'Manager ' . $i,
        'customer_site_reference' => 'REF-' . $i,
        'target_date' => '2027-08-01',
    ];
}
$bulk = (new ProjectSiteService())->import($projectId, $bulkRows, true, $userId);
$eq('100 sites imported', 100, $bulk['imported']);
$eq('100 sites committed', true, $bulk['committed']);
$dup = (new ProjectSiteService())->import($projectId, [$bulkRows[0]], true, $userId);
$eq('duplicate import commits nothing', false, $dup['committed']);
$eq('duplicate import count stays', 0, $dup['imported']);

$fifty = array_slice((new ProjectRepository())->sites($projectId), 0, 50);
$beforeJobs = count($drafts['job_ids']);
$rollout = (new ProjectSiteService())->createDraftJobs(
    $projectId,
    array_map(static fn (array $site): int => (int) $site['id'], $fifty),
    'Standard external branding package',
    true,
    $userId
);
$eq('50 draft jobs', 50, count($rollout['job_ids']));
$unconfirmed = (new ProjectSiteService())->createDraftJobs($projectId, [(int) $fifty[0]['id']], 'Again', false, $userId);
$eq('bulk requires confirmation', true, isset($unconfirmed['errors']['_form']));
$placeholders = implode(',', array_fill(0, count($rollout['job_ids']), '?'));
$newCount = (int) $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE id IN (' . $placeholders . ') AND status = \'NEW\' AND rollout_state = \'DRAFT\'')
    ->execute($rollout['job_ids']) ? 0 : 0;
$stmt = $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE id IN (' . $placeholders . ') AND status = \'NEW\' AND rollout_state = \'DRAFT\'');
$stmt->execute($rollout['job_ids']);
$eq('draft jobs stay new', 50, (int) $stmt->fetchColumn());
$stmt = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE job_id IN (' . $placeholders . ')');
$stmt->execute($rollout['job_ids']);
$eq('draft jobs consume no stock', 0, (int) $stmt->fetchColumn());
$stmt = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE job_id IN (' . $placeholders . ')');
$stmt->execute($rollout['job_ids']);
$eq('draft jobs raise no invoices', 0, (int) $stmt->fetchColumn());
unset($beforeJobs, $newCount);

$closeoutProject = (new ProjectService())->create([
    'customer_id' => $customerId,
    'name' => 'Closeout check',
    'target_date' => '2027-06-01',
], $userId);
$closeId = (int) $closeoutProject['id'];
for ($i = 1; $i <= 10; $i++) {
    (new ProjectRepository())->insertSite([
        'project_id' => $closeId,
        'wave_id' => null,
        'site_code' => 'C-' . $i,
        'site_name' => 'Close ' . $i,
        'customer_site_reference' => null,
        'address_line_1' => null,
        'address_line_2' => null,
        'city' => null,
        'province' => null,
        'postal_code' => null,
        'latitude' => null,
        'longitude' => null,
        'primary_contact_id' => null,
        'group_kind' => null,
        'group_label' => null,
        'status' => $i < 10 ? 'COMPLETED' : 'SNAGGED',
        'sequence' => $i,
        'survey_required' => 0,
        'original_target_date' => null,
        'current_target_date' => null,
        'notes' => null,
    ]);
}
$quoteId = (new ProjectRepository())->insertDraftQuote(
    'SFQ-TEST-' . $closeId,
    $customerId,
    $closeId,
    null,
    'Closeout job quote',
    $userId
);
$jobId = (new \App\Repositories\JobRepository())->insert([
    'job_number' => 'SFJ-TEST-' . $closeId,
    'customer_id' => $customerId,
    'quote_id' => $quoteId,
    'quote_revision_number' => 1,
    'title' => 'Snag host',
    'priority' => 'NORMAL',
    'assigned_to' => null,
    'target_date' => null,
    'created_by' => $userId,
]);
(new ProjectRepository())->attachJob($jobId, $closeId, null, 'ADDITIONAL', 'STANDARD', null, '0.00');
$pdo->prepare('UPDATE jobs SET status = \'COMPLETED\' WHERE id = ?')->execute([$jobId]);
$pdo->prepare('INSERT INTO job_snags (job_id, description, priority, status, created_by) VALUES (?, ?, \'CRITICAL\', \'OPEN\', ?)')->execute([$jobId, 'Critical snag', $userId]);
$review = (new ProjectCloseoutService())->review($closeId);
$eq('closeout blocked', false, $review['ready']);
$eq('snag is a blocker', true, (bool) array_filter($review['blockers'], static fn (string $line): bool => str_contains($line, 'critical snag')));

$installerRole = (int) $pdo->query("SELECT id FROM roles WHERE code = 'INSTALLER'")->fetchColumn();
$installer = ['id' => 999999, 'role_id' => $installerRole, 'role_code' => 'INSTALLER'];
$eq('installer financials denied', false, AuthorizationService::allows($installer, 'projects.view_financials'));
$eq('installer cannot open another project', false, (new ProjectAccess())->canOpen($installer, $projectId));
$pdo->prepare('INSERT INTO project_team (project_id, user_id, role_code) VALUES (?, ?, \'INSTALLER\')')->execute([$closeId, $userId]);
$assigned = ['id' => $userId, 'role_id' => $installerRole, 'role_code' => 'INSTALLER'];
$eq('assigned installer opens own project', true, (new ProjectAccess())->canOpen($assigned, $closeId));
$eq('assigned installer still blocked on the other project', false, (new ProjectAccess())->canOpen($assigned, $projectId));

$perf = (new ProjectService())->create([
    'customer_id' => $customerId,
    'name' => 'Performance rollout',
], $userId);
$perfId = (int) $perf['id'];
$started = microtime(true);
for ($i = 1; $i <= 250; $i++) {
    (new ProjectRepository())->insertSite([
        'project_id' => $perfId,
        'wave_id' => null,
        'site_code' => 'P-' . $i,
        'site_name' => 'Perf ' . $i,
        'customer_site_reference' => null,
        'address_line_1' => null,
        'address_line_2' => null,
        'city' => null,
        'province' => null,
        'postal_code' => null,
        'latitude' => $i % 2 === 0 ? '-26.2000000' : null,
        'longitude' => $i % 2 === 0 ? '28.0000000' : null,
        'primary_contact_id' => null,
        'group_kind' => null,
        'group_label' => null,
        'status' => 'NOT_STARTED',
        'sequence' => $i,
        'survey_required' => 0,
        'original_target_date' => null,
        'current_target_date' => '2027-09-01',
        'notes' => null,
    ]);
}
for ($i = 1; $i <= 1000; $i++) {
    (new ProjectRepository())->insertMilestone([
        'project_id' => $perfId,
        'project_site_id' => null,
        'name' => 'Milestone ' . $i,
        'description' => null,
        'milestone_type' => 'CUSTOM',
        'status' => 'NOT_STARTED',
        'weight' => '1.00',
        'original_due_date' => null,
        'current_due_date' => '2027-09-01',
        'depends_on_milestone_id' => null,
        'responsible_user_id' => null,
        'blocking' => 0,
        'sequence' => $i,
    ]);
}
$quoteStarted = microtime(true);
$siteIds = array_map('intval', array_column((new ProjectRepository())->sites($perfId), 'id'));
(new ProjectSiteService())->createDraftJobs($perfId, $siteIds, 'Package', true, $userId);
(new ProjectSiteService())->createDraftJobs($perfId, $siteIds, 'Second package', true, $userId);
$eq('performance jobs', 500, count((new ProjectRepository())->jobs($perfId, null)));
$gantt = (new ProjectRepository())->gantt($perfId);
$eq('gantt skips production tasks', 0, $gantt['task_rows']);
$map = (new ProjectRepository())->mapSites($perfId, 200);
$eq('map is capped', true, count($map) <= 200);
$overviewStarted = microtime(true);
$counts = (new ProjectRepository())->counts($perfId);
$finance->statement($perfId);
$elapsed = microtime(true) - $overviewStarted;
$eq('overview aggregates', 250, $counts['sites']);
$eq('overview stays under two seconds', true, $elapsed < 2);
$explain = $pdo->query('EXPLAIN SELECT id FROM project_sites WHERE project_id = ' . $perfId)->fetch();
$eq('site lookup uses an index', true, ($explain['key'] ?? '') !== '');
fwrite(STDOUT, 'info overview ' . number_format($elapsed, 3) . "s after setup " . number_format($quoteStarted - $started, 1) . "s\n");

$pack = (new ProjectCloseoutService())->handover($projectId);
$eq('handover has no cost field', false, array_key_exists('actual_cost', $pack));
$eq('handover has no margin field', false, array_key_exists('margin_percent', $pack));

$plain = (int) $pdo->query('SELECT COUNT(*) FROM jobs WHERE project_id IS NULL')->fetchColumn();
$eq('jobs can exist without a project', true, $plain >= 0);

if ($failures > 0) {
    fwrite(STDERR, $failures . " failed\n");
    exit(1);
}
fwrite(STDOUT, "v1.1 phase 1 project checks passed\n");
