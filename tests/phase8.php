<?php

declare(strict_types=1);

/**
 * Phase 8 scheduling, capacity, dependencies, and costing checks.
 *
 *   php tests/phase8.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\JobRepository;
use App\Repositories\PlanningRepository;
use App\Repositories\UserRepository;
use App\Services\CapacityService;
use App\Services\CustomerService;
use App\Services\JobReadinessService;
use App\Services\ProductService;
use App\Services\QuoteService;
use App\Services\RecurringJobService;
use App\Services\ResourceService;
use App\Services\ScheduleService;
use App\Services\SubcontractService;
use App\Services\SupplierService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "Admin user is missing.\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $admin['id'];
$userId = (int) $admin['id'];
$stamp = date('YmdHis') . random_int(10, 99);
$pdo = Database::connection();

$capacity = new CapacityService();
$week = $capacity->summarise('2400', '1920');
if ($week['utilisation_percent'] !== '80.00') {
    $fail('utilisation ' . $week['utilisation_percent']);
} else {
    $ok('40h available and 32h scheduled is 80%');
}
$over = $capacity->summarise('2400', '2880');
if ($over['utilisation_percent'] !== '120.00' || $over['over_capacity_hours'] !== '8.00') {
    $fail('over capacity ' . json_encode($over));
} else {
    $ok('40h available and 48h scheduled is 120% and 8h over');
}

$readiness = new JobReadinessService();
if ($readiness->materialState('10', '4') !== 'SHORTAGE') {
    $fail('material state');
} else {
    $ok('10 required and 4 reserved is a material shortage');
}

$resources = new ResourceService();
$schedule = new ScheduleService();
$printer = $resources->save(null, [
    'resource_type' => 'MACHINE',
    'code' => 'PRN-' . $stamp,
    'name' => 'Printer ' . $stamp,
    'capacity_type' => 'MINUTES',
    'default_daily_capacity' => '480',
    'concurrent_capacity' => '1',
    'status' => 'AVAILABLE',
    'active' => '1',
], $userId);
if ($printer['id'] === null) {
    fwrite(STDERR, json_encode($printer['errors']) . "\n");
    exit(1);
}
$first = $schedule->book([
    'entity_type' => 'OTHER',
    'entity_id' => 1,
    'resource_id' => $printer['id'],
    'start_datetime' => '2026-10-05 08:00:00',
    'end_datetime' => '2026-10-05 10:00:00',
    'status' => 'PLANNED',
], $userId);
if (empty($first['ok'])) {
    $fail('first printer booking ' . json_encode($first));
} else {
    $ok('printer booked 08:00–10:00');
}
$second = $schedule->book([
    'entity_type' => 'OTHER',
    'entity_id' => 2,
    'resource_id' => $printer['id'],
    'start_datetime' => '2026-10-05 09:00:00',
    'end_datetime' => '2026-10-05 11:00:00',
    'status' => 'PLANNED',
], $userId);
if (!empty($second['ok']) || ($second['code'] ?? '') !== 'CONFLICT') {
    $fail('overlap was allowed ' . json_encode($second));
} else {
    $ok('overlapping printer booking is a conflict');
}

$person = $resources->save(null, [
    'resource_type' => 'USER',
    'code' => 'EMP-' . $stamp,
    'name' => 'Planner ' . $stamp,
    'active' => '1',
], $userId);
$resources->addUnavailability([
    'resource_id' => $person['id'],
    'reason_type' => 'LEAVE',
    'start_datetime' => '2026-10-05 00:00:00',
    'end_datetime' => '2026-10-05 23:59:59',
    'description' => 'Annual leave',
], $userId);
$onLeave = $schedule->book([
    'entity_type' => 'OTHER',
    'entity_id' => 3,
    'resource_id' => $person['id'],
    'start_datetime' => '2026-10-05 08:00:00',
    'end_datetime' => '2026-10-05 10:00:00',
], $userId);
if (!empty($onLeave['ok']) || ($onLeave['code'] ?? '') !== 'LEAVE') {
    $fail('leave was ignored ' . json_encode($onLeave));
} else {
    $ok('leave blocks the Monday booking');
}
$overridden = $schedule->book([
    'entity_type' => 'OTHER',
    'entity_id' => 4,
    'resource_id' => $person['id'],
    'start_datetime' => '2026-10-05 08:00:00',
    'end_datetime' => '2026-10-05 10:00:00',
    'override_reason' => 'Customer emergency, covered by another planner.',
], $userId);
if (empty($overridden['ok'])) {
    $fail('authorised leave override ' . json_encode($overridden));
} else {
    $ok('authorised planner recorded a leave override');
}

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase 8 Co ' . $stamp,
    'billing_address' => '8 Plan Street',
    'active' => '1',
], $userId);
$category = $pdo->query('SELECT id FROM product_categories ORDER BY id ASC LIMIT 1')->fetch();
$product = (new ProductService())->create([
    'category_id' => (int) $category['id'],
    'sku' => 'P8-' . $stamp,
    'name' => 'Phase 8 board ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '10',
    'cost_unit' => 'unit',
    'active' => '1',
], $userId);
$quotes = new QuoteService();
$created = $quotes->create(['customer_id' => $customer['id']], $userId);
$quoteId = (int) $created['id'];
$repo = new \App\Repositories\QuoteRepository();
$quotes->addProductLine($quoteId, [
    'product_id' => $product['id'],
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
], (int) $repo->find($quoteId)['version_number'], $userId);
$quotes->changeStatus($quoteId, 'READY', (int) $repo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, ['accepted_by_name' => 'Owner', 'acceptance_method' => 'EMAIL'], (int) $repo->find($quoteId)['version_number'], $userId);
$converted = $quotes->convert($quoteId, [
    'title' => 'Phase 8 job ' . $stamp,
    'delivery_method' => 'INSTALLATION',
    'target_date' => '2026-10-16',
], (int) $repo->find($quoteId)['version_number'], $userId);
if ($converted['id'] === null) {
    fwrite(STDERR, json_encode($converted['errors']) . "\n");
    exit(1);
}
$jobId = (int) $converted['id'];
$pdo->prepare('UPDATE job_items SET artwork_required = 0 WHERE job_id = ?')->execute([$jobId]);
$printStage = (int) $pdo->query("SELECT id FROM production_stages WHERE name = 'Printing'")->fetchColumn();
$laminateStage = (int) $pdo->query("SELECT id FROM production_stages WHERE name = 'Lamination'")->fetchColumn();
$ops = new \App\Repositories\OperationsRepository();
$printId = $ops->insertStage($jobId, null, $printStage, 10);
$laminateId = $ops->insertStage($jobId, null, $laminateStage, 20);
$pdo->prepare('INSERT INTO job_expected_labour (job_id, description, expected_minutes, hourly_cost_snapshot, source) VALUES (?, ?, ?, ?, ?)')
    ->execute([$jobId, 'Printing', '35.00', '0', 'RECIPE']);
$schedule->linkStageSequence($jobId);
$printRow = (new PlanningRepository())->stage($printId);
if ((int) $printRow['estimated_minutes'] !== 35) {
    $fail('expected minutes ' . json_encode($printRow['estimated_minutes']));
} else {
    $ok('recipe labour initialised the print estimate at 35 minutes');
}
$blocked = $schedule->book([
    'entity_type' => 'PRODUCTION_STAGE',
    'entity_id' => $laminateId,
    'job_id' => $jobId,
    'resource_id' => $printer['id'],
    'start_datetime' => '2026-10-06 08:00:00',
    'end_datetime' => '2026-10-06 09:00:00',
    'status' => 'CONFIRMED',
], $userId);
$codes = array_column($blocked['conflicts'] ?? [], 'code');
if (!empty($blocked['ok']) || !in_array('DEPENDENCY', $codes, true)) {
    $fail('laminate confirmed early ' . json_encode($blocked));
} else {
    $ok('lamination cannot be confirmed before printing');
}
(new PlanningRepository())->completeStage($printId, 70);
$covered = (new PlanningRepository())->requiredQuantity($jobId);
if (\App\Helpers\Decimal::cmp($covered, '0') > 0) {
    $pdo->prepare('INSERT INTO stock_reservations (job_id, product_id, stock_location_id, quantity, unit, status) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$jobId, $product['id'], (int) $pdo->query('SELECT id FROM stock_locations ORDER BY id ASC LIMIT 1')->fetchColumn(), $covered, 'unit', 'RESERVED']);
}
$printAfter = (new PlanningRepository())->stage($printId);
if ((int) $printAfter['estimated_minutes'] !== 35 || (int) $printAfter['actual_minutes'] !== 70) {
    $fail('estimate overwritten');
} else {
    $ok('actual 70 minutes did not replace the 35 minute estimate');
}
$ready = $schedule->book([
    'entity_type' => 'PRODUCTION_STAGE',
    'entity_id' => $laminateId,
    'job_id' => $jobId,
    'resource_id' => $printer['id'],
    'start_datetime' => '2026-10-06 08:00:00',
    'end_datetime' => '2026-10-06 09:00:00',
    'estimated_minutes' => 25,
    'status' => 'CONFIRMED',
], $userId);
if (empty($ready['ok'])) {
    $fail('laminate after print ' . json_encode($ready));
} else {
    $ok('lamination is ready after printing is complete');
}

$pdo->prepare('INSERT INTO job_material_requirements (job_id, product_id, required_quantity, unit, final_required_quantity, source) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$jobId, $product['id'], '10.0000', 'm2', '10.0000', 'MANUAL']);
$location = (int) $pdo->query('SELECT id FROM stock_locations ORDER BY id ASC LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO stock_reservations (job_id, product_id, stock_location_id, quantity, unit, status) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$jobId, $product['id'], $location, '4.0000', 'm2', 'RESERVED']);
$evaluated = $readiness->evaluate($jobId);
if (($evaluated['materials'] ?? '') !== 'SHORTAGE') {
    $fail('job material status ' . json_encode($evaluated));
} else {
    $ok('job readiness reports the material shortage');
}
$tentative = $schedule->book([
    'entity_type' => 'JOB',
    'entity_id' => $jobId,
    'job_id' => $jobId,
    'resource_id' => $person['id'],
    'start_datetime' => '2026-10-07 08:00:00',
    'end_datetime' => '2026-10-07 09:00:00',
    'status' => 'PLANNED',
], $userId);
if (empty($tentative['ok']) || !str_contains(implode(' ', $tentative['warnings'] ?? []), 'shortage')) {
    $fail('tentative shortage ' . json_encode($tentative));
} else {
    $ok('shortage keeps a planned booking tentative');
}

$down = $resources->markOutOfService((int) $printer['id'], 'Print head failure', $userId);
$affectedEntry = (new PlanningRepository())->entry((int) $ready['id']);
if (empty($down['ok']) || !in_array((int) $ready['id'], $down['affected'], true) || ($affectedEntry['start_datetime'] ?? '') === '') {
    $fail('breakdown ' . json_encode($down));
} else {
    $ok('breakdown lists affected work and leaves the time in place');
}
$health = $readiness->jobHealth($jobId);
if (!in_array($health['status'], ['AT_RISK', 'BLOCKED', 'OVERDUE'], true)) {
    $fail('risk status ' . json_encode($health));
} else {
    $ok('job health states ' . $health['status']);
}

$team = $resources->save(null, ['resource_type' => 'TEAM', 'code' => 'CRW-' . $stamp, 'name' => 'Crew ' . $stamp, 'active' => '1'], $userId);
$vehicle = $resources->save(null, ['resource_type' => 'VEHICLE', 'code' => 'VAN-' . $stamp, 'name' => 'Van ' . $stamp, 'active' => '1'], $userId);
$ladder = $resources->save(null, ['resource_type' => 'EQUIPMENT', 'code' => 'LAD-' . $stamp, 'name' => 'Ladder ' . $stamp, 'active' => '1'], $userId);
$pdo->prepare('INSERT INTO vehicle_details (resource_id, registration_number, odometer) VALUES (?, ?, ?)')
    ->execute([$vehicle['id'], 'P8' . substr($stamp, -6), '1000.0']);
$install = $schedule->book([
    'entity_type' => 'INSTALLATION',
    'entity_id' => 1,
    'job_id' => $jobId,
    'resource_id' => $team['id'],
    'resource_ids' => [$team['id'], $vehicle['id'], $ladder['id']],
    'start_datetime' => '2026-10-08 08:00:00',
    'end_datetime' => '2026-10-08 13:00:00',
    'status' => 'PLANNED',
], $userId);
$clash = $schedule->book([
    'entity_type' => 'INSTALLATION',
    'entity_id' => 2,
    'resource_id' => $vehicle['id'],
    'start_datetime' => '2026-10-08 10:00:00',
    'end_datetime' => '2026-10-08 12:00:00',
    'status' => 'PLANNED',
], $userId);
if (empty($install['ok']) || !empty($clash['ok']) || ($clash['code'] ?? '') !== 'CONFLICT') {
    $fail('vehicle clash ' . json_encode([$install, $clash]));
} else {
    $ok('the van cannot be on two installations');
}

$recurring = new RecurringJobService();
$template = $recurring->save([
    'customer_id' => $customer['id'],
    'name' => 'Monthly display ' . $stamp,
    'frequency_type' => 'MONTHLY',
    'interval_value' => 1,
    'next_run_date' => date('Y-m-d'),
], $userId);
$recurring->run();
$recurring->run();
$count = (new PlanningRepository())->followupCount((int) $template['id']);
if ($count !== 1) {
    $fail('recurring count ' . $count);
} else {
    $ok('monthly template generated one follow-up');
}

$supplier = (new SupplierService())->save(null, [
    'name' => 'Crane hire ' . $stamp,
    'is_subcontractor' => '1',
    'capabilities' => 'Crane hire',
    'active' => '1',
]);
$orders = new SubcontractService();
$order = $orders->create([
    'job_id' => $jobId,
    'supplier_id' => $supplier['id'],
    'description' => 'Crane for the install',
    'estimated_cost' => '5000.00',
], $userId);
$orders->complete((int) $order['id'], '5500.00', $userId);
$orders->complete((int) $order['id'], '5500.00', $userId);
$job = (new JobRepository())->find($jobId);
$otherRows = $pdo->prepare('SELECT COUNT(*) FROM job_other_costs WHERE job_id = ?');
$otherRows->execute([$jobId]);
$otherCount = (int) $otherRows->fetchColumn();
if ($otherCount !== 1 || Decimal::cmp((string) $job['actual_other_cost'], '5500.00') !== 0 || Decimal::cmp((string) $job['actual_total_cost'], '5500.00') !== 0) {
    $fail('subcontract cost ' . $otherCount . ' ' . $job['actual_other_cost'] . ' ' . $job['actual_total_cost']);
} else {
    $ok('subcontract actual cost posted once at 5500');
}

$promise = '2026-10-20';
$promiseErrors = $schedule->setCustomerPromise($jobId, $promise, 'Agreed with the customer', $userId);
$schedule->move((int) $install['id'], '2026-10-09 08:00:00', '2026-10-09 13:00:00', $userId, 'Crew moved to Friday');
$targetErrors = $schedule->changeInternalTarget($jobId, '2026-10-18', 'Internal production moved', $userId);
$job = (new JobRepository())->find($jobId);
if ($promiseErrors !== [] || $targetErrors !== [] || (string) $job['customer_promised_date'] !== $promise || (string) $job['target_date'] !== '2026-10-18') {
    $fail('dates ' . json_encode([$promiseErrors, $targetErrors, $job['customer_promised_date'], $job['target_date']]));
} else {
    $ok('internal schedule changes left the customer promised date alone');
}

$roles = [];
foreach ($pdo->query('SELECT id, code FROM roles') as $role) {
    $roles[$role['code']] = (int) $role['id'];
}
$users = new UserRepository();
$installerId = $users->insert([
    'name' => 'Installer ' . $stamp,
    'email' => 'installer-p8-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Install#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['INSTALLER'],
    'active' => 1,
    'must_change_password' => 0,
]);
$managerId = $users->insert([
    'name' => 'Manager ' . $stamp,
    'email' => 'manager-p8-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Manage#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['MANAGEMENT'],
    'active' => 1,
    'must_change_password' => 0,
]);
$denied = $schedule->book([
    'entity_type' => 'OTHER',
    'entity_id' => 9,
    'resource_id' => $ladder['id'],
    'start_datetime' => '2026-10-12 08:00:00',
    'end_datetime' => '2026-10-12 09:00:00',
], $installerId);
$allowed = $schedule->book([
    'entity_type' => 'OTHER',
    'entity_id' => 10,
    'resource_id' => $ladder['id'],
    'start_datetime' => '2026-10-12 08:00:00',
    'end_datetime' => '2026-10-12 09:00:00',
], $managerId);
if (!empty($denied['ok']) || empty($allowed['ok'])) {
    $fail('permissions ' . json_encode([$denied, $allowed]));
} else {
    $ok('installer cannot schedule and a manager can');
}

$http = static function (int $actor) : string {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phase8_http.php') . ' ' . $actor;
    $output = [];
    exec($command . ' 2>&1', $output);

    return implode("\n", $output);
};
$installerPage = $http($installerId);
$managerPage = $http($managerId);
if (!str_contains($installerPage, 'Your role cannot open that page.')) {
    $fail('installer url ' . $installerPage);
} else {
    $ok('installer post to the schedule is denied');
}
if (!str_contains($managerPage, 'form token')) {
    $fail('manager url ' . $managerPage);
} else {
    $ok('manager reaches the schedule action and still needs a form token');
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} phase 8 checks failed.\n");
    exit(1);
}
fwrite(STDOUT, "Phase 8 checks passed.\n");
