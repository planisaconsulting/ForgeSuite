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
use App\Services\LeadAlertService;
use App\Services\RecurringJobService;
use App\Services\ScheduleAlertService;
use App\Services\SettingsService;
use App\Services\SystemHealthService;

$alerts = (new AlertService())->evaluate();
$leadAlerts = (new LeadAlertService())->evaluate();
$snagAlerts = (new \App\Services\SnagService())->flagOverdue();
$scheduleAlerts = (new ScheduleAlertService())->evaluate();
$recurring = (new RecurringJobService())->run();
$reports = (new AutomationService())->deliverScheduledReports();
$webhookRetries = (new \App\Services\OutboundWebhookService())->processDue();
$removed = (new SystemHealthService())->cleanupTemporary();
(new SettingRepository())->put('last_cron_at', date('Y-m-d H:i:s'));
SettingsService::forget();

fwrite(STDOUT, "alerts_created={$alerts} lead_alerts={$leadAlerts} snag_alerts={$snagAlerts} schedule_alerts={$scheduleAlerts} recurring_followups={$recurring} scheduled_reports={$reports} webhook_retries={$webhookRetries} temp_removed={$removed}\n");
