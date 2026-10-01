<?php

declare(strict_types=1);

/**
 * Sign-Forge scheduled tasks.
 *
 * Run from the server clock, not from a browser:
 *   php /path/to/signforge/cron/run.php
 *
 * On Xneelo, add a cron job for that command every hour.
 * The .htaccess in this folder refuses HTTP access as well.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Repositories\SettingRepository;
use App\Services\AlertService;
use App\Services\AutomationService;
use App\Services\SettingsService;
use App\Services\SystemHealthService;

$alerts = (new AlertService())->evaluate();
$reports = (new AutomationService())->deliverScheduledReports();
$removed = (new SystemHealthService())->cleanupTemporary();
(new SettingRepository())->put('last_cron_at', date('Y-m-d H:i:s'));
SettingsService::forget();

fwrite(STDOUT, "alerts_created={$alerts} scheduled_reports={$reports} temp_removed={$removed}\n");
