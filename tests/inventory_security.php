<?php

declare(strict_types=1);

/**
 * Fresh-process checks so the signed-in user is not the admin cache.
 *
 *   php tests/inventory_security.php <action> <userId> <productId> <locationId> <orderId>
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Csrf;
use App\Services\PurchasingService;
use App\Services\StockMovementService;

$action = $argv[1] ?? '';
$userId = (int) ($argv[2] ?? 0);
$productId = (int) ($argv[3] ?? 0);
$locationId = (int) ($argv[4] ?? 0);
$orderId = (int) ($argv[5] ?? 0);
$_SESSION['user_id'] = $userId;

if ($action === 'adjust') {
    $result = (new StockMovementService())->adjust([
        'product_id' => $productId,
        'stock_location_id' => $locationId,
        'direction' => 'OUT',
        'quantity' => '1',
        'reason' => 'Should fail',
    ], $userId);
    $message = (string) ($result['errors']['_form'] ?? '');
    if (!str_contains($message, 'cannot adjust')) {
        fwrite(STDERR, "FAIL adjust {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "ok   production cannot adjust stock\n");
    exit(0);
}

if ($action === 'approve') {
    $errors = (new PurchasingService())->setStatus($orderId, 'APPROVED', $userId);
    $message = (string) ($errors['_form'] ?? '');
    if (!str_contains($message, 'cannot approve')) {
        fwrite(STDERR, "FAIL approve {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "ok   sales cannot approve a purchase order\n");
    exit(0);
}

if ($action === 'csrf') {
    $_SESSION['csrf'] = 'expected-token';
    $_POST['_token'] = 'wrong-token';
    Csrf::verify();
    fwrite(STDERR, "FAIL csrf did not stop\n");
    exit(1);
}

fwrite(STDERR, "Unknown action\n");
exit(1);
