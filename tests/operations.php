<?php

declare(strict_types=1);

/**
 * Job costing formulas. No database.
 *
 *   php tests/operations.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Services\JobCostingService;
use App\Services\JobWorkflowService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};

$costing = new JobCostingService();
$summary = $costing->summarise('20000', '5000', '6000', '3000', '1000');
$check = static function (string $label, string $expected, mixed $actual) use ($fail, $ok): void {
    if (Decimal::cmp(Decimal::round($expected, 2), Decimal::round((string) $actual, 2)) !== 0) {
        $fail("{$label} expected {$expected} got {$actual}");

        return;
    }
    $ok($label);
};
$check('actual cost', '10000.00', $summary['actual_total_cost']);
$check('gross profit', '10000.00', $summary['actual_profit']);
$check('gross margin', '50.00', $summary['actual_margin_percent']);
$check('quoted profit', '15000.00', $summary['quoted_profit']);
$check('cost variance', '5000.00', $summary['cost_variance']);
$check('variance percent', '100.00', $summary['cost_variance_percent']);
if ($summary['overrun'] !== true) {
    $fail('overrun flag');
} else {
    $ok('overrun highlighted');
}

$materials = $costing->summarise('0', '0', '0', '0', '0', [
    ['product_id' => 1, 'name' => 'Vinyl', 'unit' => 'm2', 'quantity' => '10', 'kind' => 'estimated'],
    ['product_id' => 1, 'name' => 'Vinyl', 'unit' => 'm2', 'quantity' => '9.6', 'kind' => 'production'],
    ['product_id' => 1, 'name' => 'Vinyl', 'unit' => 'm2', 'quantity' => '1.7', 'kind' => 'waste'],
]);
$row = $materials['materials'][0];
$check('estimated material', '10.0000', $row['estimated']);
$check('production material', '9.6000', $row['production']);
$check('waste material', '1.7000', $row['waste']);
$check('actual material qty', '11.3000', $row['actual']);
$check('material variance', '1.3000', $row['variance']);

$labour = $costing->summarise('0', '0', '0', '0', '0', [], [
    ['estimated_minutes' => 240, 'actual_minutes' => 0],
    ['estimated_minutes' => 0, 'actual_minutes' => 390],
]);
if ($labour['labour']['variance_minutes'] !== 150) {
    $fail('labour variance ' . $labour['labour']['variance_minutes']);
} else {
    $ok('labour variance +2.5 hours');
}

$empty = $costing->summarise('0', '0', '0', '0', '0');
if ($empty['actual_margin_percent'] !== null) {
    $fail('margin on zero revenue');
} else {
    $ok('zero quoted revenue has no margin');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$workflow = new JobWorkflowService();
$job = ['status' => 'COMPLETED', 'delivery_method' => 'INSTALLATION'];
$decision = $workflow->evaluate($job, 'IN_PRODUCTION', '', '', [
    'artwork_blocking' => false,
    'items_open' => false,
    'qc_blocking' => false,
    'installation_open' => false,
]);
if ($decision['ok']) {
    $fail('completed job reopened without permission ' . json_encode($decision));
} else {
    $ok('completed job stays locked');
}

$blocked = $workflow->evaluate(
    ['status' => 'AWAITING_CUSTOMER_APPROVAL', 'delivery_method' => 'INSTALLATION'],
    'APPROVED_FOR_PRODUCTION',
    '',
    '',
    ['artwork_blocking' => true, 'items_open' => false, 'qc_blocking' => false, 'installation_open' => false]
);
if (($blocked['errors']['_form'] ?? '') !== 'Artwork has not been approved by the customer.') {
    $fail('artwork gate ' . json_encode($blocked['errors'] ?? []));
} else {
    $ok('production blocked until artwork is approved');
}

$collection = $workflow->choices('QUALITY_CONTROL', 'COLLECTION');
if (in_array('READY_FOR_INSTALLATION', $collection, true) || !in_array('READY_FOR_COLLECTION', $collection, true)) {
    $fail('collection route ' . implode(',', $collection));
} else {
    $ok('collection skips installation');
}

exit($failures > 0 ? 1 : 0);
