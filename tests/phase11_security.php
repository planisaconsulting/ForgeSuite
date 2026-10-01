<?php

declare(strict_types=1);

/**
 * Permission checks in a fresh process so the signed-in user is not cached.
 *
 *   php tests/phase11_security.php <action> <userId> [extra]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\ProductService;
use App\Services\WorkshopDocumentService;

$action = $argv[1] ?? '';
$userId = (int) ($argv[2] ?? 0);
$_SESSION['user_id'] = $userId;

if ($action === 'workshop-cost') {
    $jobId = (int) ($argv[3] ?? 0);
    if (\App\Services\AuthorizationService::allows(auth_user(), 'costing.view')) {
        fwrite(STDERR, "FAIL workshop can view costing\n");
        exit(1);
    }
    $data = (new WorkshopDocumentService())->cardData($jobId, $userId, true);
    if (!empty($data['show_cost']) || !empty($data['costing'])) {
        fwrite(STDERR, "FAIL job card exposed cost\n");
        exit(1);
    }
    fwrite(STDOUT, "ok   workshop job card hides cost\n");
    exit(0);
}

if ($action === 'installer-cost') {
    $errors = (new ProductService())->update((int) ($argv[3] ?? 1), ['cost_price' => '1'], $userId);
    $message = (string) ($errors['_form'] ?? '');
    if (!str_contains($message, 'cannot edit')) {
        fwrite(STDERR, "FAIL installer cost {$message}\n");
        exit(1);
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/products/' . (int) ($argv[3] ?? 1) . '/edit';
    ob_start();
    $router = new App\Helpers\Router();
    require base_path('app/routes.php');
    $router->dispatch();
    $body = (string) ob_get_clean();
    if (!str_contains($body, 'Not allowed') && !str_contains($body, 'cannot')) {
        fwrite(STDERR, "FAIL installer url did not deny\n");
        exit(1);
    }
    fwrite(STDOUT, "ok   installer cannot edit product cost\n");
    exit(0);
}

fwrite(STDERR, "Unknown action\n");
exit(1);
