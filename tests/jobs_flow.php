<?php

declare(strict_types=1);

/**
 * Quote conversion through artwork, production, materials, time, QC, and installation.
 *
 *   php tests/jobs_flow.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\AttachmentService;
use App\Services\AuthorizationService;
use App\Services\CustomerService;
use App\Services\JobService;
use App\Services\ProductService;
use App\Services\QuoteService;
use App\Services\QuoteTotals;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$money = static function (string $label, string $expected, mixed $actual) use ($fail, $ok): void {
    $actualText = $actual === null ? '0' : (string) $actual;
    if (Decimal::cmp(Decimal::round($expected, 2), Decimal::round($actualText, 2)) !== 0) {
        $fail("{$label} expected {$expected} got {$actualText}");

        return;
    }
    $ok($label);
};

$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "Admin user is missing.\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $admin['id'];
$userId = (int) $admin['id'];
register_shutdown_function(static function () use ($userId): void {
    Database::connection()->prepare('UPDATE users SET hourly_cost = NULL WHERE id = ?')->execute([$userId]);
});

$stamp = date('YmdHis');
$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase3 Flow ' . $stamp,
    'active' => '1',
], $userId);
$customerId = (int) $customer['id'];
$categories = (new App\Repositories\CategoryRepository())->allWithParent();
$categoryId = (int) $categories[0]['id'];
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P3-' . $stamp,
    'name' => 'Phase3 vinyl ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'AREA',
    'cost_price' => '100',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
], $userId);
$productId = (int) $product['id'];

$quotes = new QuoteService();
$repo = new QuoteRepository();
$made = $quotes->create(['customer_id' => $customerId], $userId);
$quoteId = (int) $made['id'];
$quotes->addProductLine($quoteId, [
    'product_id' => $productId,
    'width_mm' => '1000',
    'height_mm' => '1000',
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
    'customer_description' => 'Shopfront graphic',
    'internal_description' => 'Print then laminate',
], (int) $repo->find($quoteId)['version_number'], $userId);
$quotes->addCustomLine($quoteId, [
    'customer_description' => 'Optional lighting',
    'quantity' => '1',
    'unit_cost' => '500',
    'final_sell_price' => '900',
    'is_optional' => '1',
], (int) $repo->find($quoteId)['version_number'], $userId);
$quotes->changeStatus($quoteId, 'READY', (int) $repo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, [
    'accepted_by_name' => 'Site owner',
    'acceptance_method' => 'EMAIL',
], (int) $repo->find($quoteId)['version_number'], $userId);
$quote = $repo->find($quoteId);
$expected = (new QuoteTotals())->summarise($repo->items($quoteId), $quote);
$converted = $quotes->convert($quoteId, [
    'title' => 'Phase 3 shopfront ' . $stamp,
    'priority' => 'HIGH',
    'delivery_method' => 'INSTALLATION',
    'target_date' => date('Y-m-d', strtotime('+10 days')),
    'customer_po_number' => 'PO-' . $stamp,
], (int) $quote['version_number'], $userId);
if ($converted['id'] === null) {
    fwrite(STDERR, 'convert ' . json_encode($converted['errors']) . "\n");
    exit(1);
}
$jobId = (int) $converted['id'];
$jobs = new JobRepository();
$ops = new OperationsRepository();
$service = new JobService();
$job = $jobs->find($jobId);
if ((int) $job['customer_id'] !== $customerId || (int) $job['quote_id'] !== $quoteId || (int) $job['quote_revision_number'] !== (int) $quote['revision_number']) {
    $fail('job linkage');
} else {
    $ok('job keeps customer, quote, and revision');
}
$money('quoted revenue snapshot', (string) $expected['revenue'], $job['quoted_revenue_snapshot']);
$money('quoted cost snapshot', (string) $expected['cost'], $job['quoted_cost_snapshot']);
$items = $ops->items($jobId);
if (count($items) !== 1 || (string) $items[0]['description'] !== 'Shopfront graphic') {
    $fail('job items ' . count($items));
} else {
    $ok('included quote line became a job item');
}
$requirements = $ops->requirements($jobId);
if (count($requirements) !== 1 || (string) $requirements[0]['source'] !== 'QUOTE') {
    $fail('material requirement');
} else {
    $ok('quote material requirement stored separately from usage');
}

$blocked = $service->changeStatus($jobId, 'APPROVED_FOR_PRODUCTION', (int) $jobs->find($jobId)['version_number'], $userId);
if (($blocked['_form'] ?? '') !== 'Artwork has not been approved by the customer.') {
    $fail('artwork gate ' . json_encode($blocked));
} else {
    $ok('production blocked with the artwork warning');
}
$override = $service->changeStatus(
    $jobId,
    'APPROVED_FOR_PRODUCTION',
    (int) $jobs->find($jobId)['version_number'],
    $userId,
    '',
    'Phone confirmation while the proof file is prepared'
);
if ($override !== []) {
    $fail('override ' . json_encode($override));
} else {
    $ok('authorised override stored');
}
$job = $jobs->find($jobId);
if ((string) $job['artwork_override_reason'] === '' || (int) $job['artwork_override_by'] !== $userId) {
    $fail('override columns');
} else {
    $ok('override reason and user recorded');
}

$png = sys_get_temp_dir() . '/sf-proof-' . $stamp . '.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$file = ['name' => 'proof.png', 'tmp_name' => $png, 'size' => filesize($png), 'error' => 0];
$first = $service->storeArtwork($jobId, $file, ['title' => 'Main sign proof'], $userId, false);
$second = $service->storeArtwork($jobId, $file, ['title' => 'Main sign proof'], $userId, false);
if ($first !== [] || $second !== []) {
    $fail('artwork upload ' . json_encode($first) . json_encode($second));
}
$artworks = $ops->artworks($jobId);
$superseded = 0;
$current = null;
foreach ($artworks as $artwork) {
    if ((string) $artwork['status'] === 'SUPERSEDED') {
        $superseded++;
    } else {
        $current = $artwork;
    }
}
if ($superseded !== 1 || $current === null || (int) $current['revision_number'] !== 2) {
    $fail('revisions superseded=' . $superseded);
} else {
    $ok('revision 2 is current and revision 1 is superseded');
}
$approved = $service->recordApproval($jobId, (int) $current['id'], [
    'customer_name' => 'Site owner',
    'approval_method' => 'EMAIL',
    'reference' => 'mail-' . $stamp,
], $userId);
if ($approved !== [] || !$ops->artworkApproved($jobId)) {
    $fail('approval ' . json_encode($approved));
} else {
    $ok('customer approval recorded');
}

$templateId = 0;
foreach ($ops->templates() as $template) {
    if ((string) $template['code'] === 'PRINTED_VINYL') {
        $templateId = (int) $template['id'];
    }
}
$routed = $service->applyTemplate($jobId, $templateId, (int) $items[0]['id'], $userId);
if ($routed !== [] || count($ops->stages($jobId)) < 5) {
    $fail('route ' . json_encode($routed));
} else {
    $ok('printed vinyl route copied onto the job');
}
$stage = $ops->stages($jobId)[0];
$service->updateStage($jobId, (int) $stage['id'], 'IN_PROGRESS', $userId);
$service->updateStage($jobId, (int) $stage['id'], 'COMPLETE', $userId);
$stage = $ops->stage((int) $stage['id']);
if ((string) $stage['status'] !== 'COMPLETE' || $stage['started_at'] === null) {
    $fail('stage progress');
} else {
    $ok('stage started and completed');
}
$task = $ops->tasks($jobId)[0];
$blockedTask = $service->updateTask($jobId, (int) $task['id'], 'BLOCKED', (int) $task['version_number'], $userId);
if ($blockedTask !== [] || (string) $ops->task((int) $task['id'])['status'] !== 'BLOCKED') {
    $fail('block task ' . json_encode($blockedTask));
} else {
    $ok('task blocked');
}
$service->updateItemStatus($jobId, (int) $items[0]['id'], 'COMPLETE');
if ((string) $ops->item((int) $items[0]['id'])['production_status'] !== 'COMPLETE') {
    $fail('item status');
} else {
    $ok('item completed');
}

$service->changeStatus($jobId, 'IN_PRODUCTION', (int) $jobs->find($jobId)['version_number'], $userId);
$moved = $service->changeStatus($jobId, 'QUALITY_CONTROL', (int) $jobs->find($jobId)['version_number'], $userId);
if ($moved !== [] || (string) $jobs->find($jobId)['status'] !== 'QUALITY_CONTROL') {
    $fail('move to qc ' . json_encode($moved));
} else {
    $ok('job moved to quality control');
}
$service->recordQuality($jobId, ['check_type' => 'Correct spelling', 'status' => 'FAIL', 'notes' => 'Letter missing'], $userId);
$rework = false;
foreach ($ops->tasks($jobId) as $row) {
    if (str_starts_with((string) $row['title'], 'Rework:')) {
        $rework = true;
    }
}
if (!$rework) {
    $fail('rework task missing');
} else {
    $ok('failed check created a rework task');
}
$service->recordQuality($jobId, ['check_type' => 'Correct spelling', 'status' => 'PASS', 'notes' => 'Corrected'], $userId);
if ($ops->qcBlocking($jobId)) {
    $fail('latest pass still blocks');
} else {
    $ok('latest pass clears the check');
}

$used = $service->recordMaterial($jobId, [
    'product_id' => $productId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '2',
    'unit_cost' => '0.01',
    'total_cost' => '0.01',
], $userId);
$waste = $service->recordMaterial($jobId, [
    'product_id' => $productId,
    'usage_type' => 'WASTE',
    'quantity' => '0.5',
    'reason' => 'TRIM',
    'unit_cost' => '0.01',
], $userId);
$reworkUse = $service->recordMaterial($jobId, [
    'product_id' => $productId,
    'usage_type' => 'REWORK',
    'quantity' => '0.25',
    'reason' => 'REWORK',
], $userId);
if ($used['id'] === null || $waste['id'] === null || $reworkUse['id'] === null) {
    $fail('usage ' . json_encode([$used, $waste, $reworkUse]));
}
$usage = $ops->usage($jobId)[0];
$money('usage ignores posted cost', '100.00', $usage['unit_cost_snapshot']);
(new ProductService())->update($productId, [
    'category_id' => $categoryId,
    'sku' => 'P3-' . $stamp,
    'name' => 'Phase3 vinyl ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'AREA',
    'cost_price' => '180',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
], $userId);
$again = $ops->usage($jobId);
$money('historical usage stays at R100', '100.00', $again[count($again) - 1]['unit_cost_snapshot']);
$report = (new App\Services\JobCostingService())->report($jobId);
$money('material total', '275.00', $report['actual_material_cost']);

Database::connection()->prepare('UPDATE users SET hourly_cost = ? WHERE id = ?')->execute(['200.00', $userId]);
$timed = $service->addTime($jobId, [
    'work_type' => 'DESIGN',
    'hours' => '1',
    'minutes' => '30',
    'hourly_cost' => '1',
    'description' => 'Artwork revision 2',
], $userId);
if ($timed !== []) {
    $fail('time ' . json_encode($timed));
}
$entry = $ops->timeEntries($jobId)[0];
$money('hourly snapshot', '200.00', $entry['hourly_cost_snapshot']);
$money('labour line', '300.00', $entry['total_cost']);
$service->startTimer($jobId, ['work_type' => 'PRINTING'], $userId);
$open = $ops->openTimer($userId);
Database::connection()->prepare('UPDATE job_time_entries SET started_at = ? WHERE id = ?')->execute([
    date('Y-m-d H:i:s', time() - 5400),
    (int) $open['id'],
]);
$stopped = $service->stopTimer($userId);
$closed = $ops->timeEntries($jobId)[0];
if ($stopped !== [] || (int) $closed['minutes'] < 89) {
    $fail('timer ' . json_encode($stopped) . ' minutes ' . ($closed['minutes'] ?? ''));
} else {
    $ok('timer stopped at ' . $closed['minutes'] . ' minutes');
}
$report = (new App\Services\JobCostingService())->report($jobId);
if (Decimal::cmp((string) $report['actual_labour_cost'], '300') <= 0) {
    $fail('labour total ' . $report['actual_labour_cost']);
} else {
    $ok('labour total includes manual time and the timer');
}

$service->addOtherCost($jobId, [
    'cost_type' => 'COURIER',
    'description' => 'Overnight',
    'quantity' => '1',
    'unit_cost' => '1000',
    'total_cost' => '1',
], $userId);
$report = (new App\Services\JobCostingService())->report($jobId);
$money('other cost ignores posted total', '1000.00', $report['actual_other_cost']);

$teamId = (int) $ops->teams()[0]['id'];
$scheduled = $service->scheduleInstallation($jobId, [
    'scheduled_date' => date('Y-m-d'),
    'scheduled_start_time' => '09:00',
    'assigned_team_id' => $teamId,
    'assigned_user_id' => $userId,
    'site_address' => '12 Main Road',
    'site_contact_name' => 'Site owner',
    'site_contact_phone' => '0820000000',
], $userId);
$installationId = (int) $scheduled['id'];
$installation = $ops->installation($installationId);
$checks = $ops->checklist($installationId);
if ($checks === [] || (int) $installation['assigned_team_id'] !== $teamId) {
    $fail('installation schedule');
} else {
    $ok('installation scheduled with a checklist');
}
$service->updateInstallation($jobId, $installationId, [
    'status' => 'ON_SITE',
    'scheduled_date' => $installation['scheduled_date'],
    'assigned_team_id' => $teamId,
    'assigned_user_id' => $userId,
], (int) $installation['version_number'], $userId);
$service->toggleChecklist($jobId, $installationId, (int) $checks[0]['id'], true, $userId);
if ((int) $ops->checklist($installationId)[0]['checked'] !== 1) {
    $fail('checklist');
} else {
    $ok('checklist item ticked');
}
$photo = (new AttachmentService())->store('installation', $installationId, $file, $userId, 'COMPLETION_PHOTO', 'Front elevation', false);
if ($photo !== []) {
    $fail('photo ' . json_encode($photo));
} else {
    $ok('completion photo stored');
}
$installation = $ops->installation($installationId);
$done = $service->updateInstallation($jobId, $installationId, [
    'status' => 'COMPLETE',
    'scheduled_date' => $installation['scheduled_date'],
    'assigned_team_id' => $teamId,
    'assigned_user_id' => $userId,
    'customer_signoff_name' => 'Site owner',
    'signoff_notes' => 'Typed name on the job record',
], (int) $installation['version_number'], $userId);
$installation = $ops->installation($installationId);
if ($done !== [] || (string) $installation['status'] !== 'COMPLETE' || (string) $installation['customer_signoff_name'] !== 'Site owner') {
    $fail('sign-off ' . json_encode($done));
} else {
    $ok('installation completed with a recorded name');
}

foreach (['READY_FOR_INSTALLATION', 'INSTALLATION_SCHEDULED', 'INSTALLATION_IN_PROGRESS', 'COMPLETED'] as $status) {
    $step = $service->changeStatus($jobId, $status, (int) $jobs->find($jobId)['version_number'], $userId);
    if ($step !== []) {
        $fail($status . ' ' . json_encode($step));
    }
}
if ((string) $jobs->find($jobId)['status'] === 'COMPLETED') {
    $ok('job completed after installation');
}

$php = sys_get_temp_dir() . '/sf-evil-' . $stamp . '.php';
file_put_contents($php, "<?php echo 'no';\n");
$rejected = (new AttachmentService())->inspect([
    'name' => 'evil.php',
    'tmp_name' => $php,
    'size' => (int) filesize($php),
    'error' => 0,
], false);
if ($rejected['ok']) {
    $fail('php upload accepted');
} else {
    $ok('php upload rejected');
}

$roles = [];
foreach ((new RoleRepository())->all() as $role) {
    $roles[(string) $role['code']] = (int) $role['id'];
}
$users = new UserRepository();
$installerId = $users->insert([
    'name' => 'Installer ' . $stamp,
    'email' => 'installer-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Install#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['INSTALLER'],
    'active' => 1,
    'must_change_password' => 0,
]);
$productionId = $users->insert([
    'name' => 'Production ' . $stamp,
    'email' => 'production-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Produce#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['PRODUCTION'],
    'active' => 1,
    'must_change_password' => 0,
]);
$installer = $users->find($installerId);
if (AuthorizationService::allows($installer, 'costing.view')) {
    $fail('installer can see costing');
} else {
    $ok('installer cannot see costing');
}
$freshJob = $quotes->create(['customer_id' => $customerId], $userId);
$freshId = (int) $freshJob['id'];
$quotes->addProductLine($freshId, [
    'product_id' => $productId,
    'width_mm' => '500',
    'height_mm' => '500',
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
], (int) $repo->find($freshId)['version_number'], $userId);
$quotes->changeStatus($freshId, 'READY', (int) $repo->find($freshId)['version_number'], $userId);
$quotes->accept($freshId, ['accepted_by_name' => 'Owner', 'acceptance_method' => 'PHONE'], (int) $repo->find($freshId)['version_number'], $userId);
$second = $quotes->convert($freshId, ['title' => 'Security job ' . $stamp, 'delivery_method' => 'COLLECTION'], (int) $repo->find($freshId)['version_number'], $userId);
$securityJob = (int) $second['id'];
$securityVersion = (int) $jobs->find($securityJob)['version_number'];
$run = static function (string $action, int $actor) use ($securityJob, $securityVersion): string {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/jobs_security.php')
        . ' ' . escapeshellarg($action) . ' ' . $actor . ' ' . $securityJob . ' ' . $securityVersion;
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);

    return trim(implode("\n", $output));
};
$installerOut = $run('installer-status', $installerId);
if (!str_contains($installerOut, 'ok   installer')) {
    $fail('installer child ' . $installerOut);
} else {
    $ok('installer status change refused in a separate process');
}
$productionOut = $run('production-override', $productionId);
if (!str_contains($productionOut, 'ok   production')) {
    $fail('production child ' . $productionOut);
} else {
    $ok('unauthorised approval override refused');
}
$csrfOut = $run('csrf', $userId);
if (!str_contains($csrfOut, 'form token')) {
    $fail('csrf ' . $csrfOut);
} else {
    $ok('missing form token is rejected');
}
$foreign = $service->updateTask($securityJob, (int) $task['id'], 'COMPLETE', 1, $userId);
if (!isset($foreign['_form'])) {
    $fail('task id from another job was accepted');
} else {
    $ok('task id from another job was refused');
}

exit($failures > 0 ? 1 : 0);
