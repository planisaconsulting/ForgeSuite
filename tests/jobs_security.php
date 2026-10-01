<?php

declare(strict_types=1);

/**
 * One security check in a fresh process so the signed-in user is not cached.
 *
 *   php tests/jobs_security.php <action> <userId> <jobId> <version>
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Csrf;
use App\Services\JobService;

$action = $argv[1] ?? '';
$userId = (int) ($argv[2] ?? 0);
$jobId = (int) ($argv[3] ?? 0);
$version = (int) ($argv[4] ?? 0);
$_SESSION['user_id'] = $userId;

if ($action === 'installer-status') {
    $errors = (new JobService())->changeStatus($jobId, 'IN_PRODUCTION', $version, $userId, '', '');
    $message = (string) ($errors['_form'] ?? '');
    if ($message === '' || !str_contains($message, 'cannot change')) {
        fwrite(STDERR, "FAIL installer status {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "ok   installer cannot change job status\n");
    exit(0);
}

if ($action === 'production-override') {
    $errors = (new JobService())->changeStatus($jobId, 'APPROVED_FOR_PRODUCTION', $version, $userId, '', 'Skip the proof');
    $message = (string) ($errors['_form'] ?? '');
    if ($message !== 'Artwork has not been approved by the customer.') {
        fwrite(STDERR, "FAIL production override {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "ok   production cannot override artwork approval\n");
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
