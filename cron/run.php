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

$settings = new SettingRepository();
$started = microtime(true);
$settings->put('last_cron_started_at', date('Y-m-d H:i:s'));
$settings->put('last_cron_status', 'RUNNING');
SettingsService::forget();

try {
$alerts = (new AlertService())->evaluate();
$leadAlerts = (new LeadAlertService())->evaluate();
$snagAlerts = (new \App\Services\SnagService())->flagOverdue();
$scheduleAlerts = (new ScheduleAlertService())->evaluate();
$recurring = (new RecurringJobService())->run();
$reports = (new AutomationService())->deliverScheduledReports();
$webhookRetries = (new \App\Services\OutboundWebhookService())->processDue();
$approvalReminders = (new \App\Services\ApprovalService())->escalate();
$integrationRetries = (new \App\Services\IntegrationIssueService())->retryDue();
$fieldPacks = (new \App\Services\FieldPackService())->cleanup();
$projectMilestones = (new \App\Services\ProjectHealthService())->notifyDue();
$warrantyNotices = (new \App\Services\WarrantyService())->notifyExpiring();
$maintenanceDue = (new \App\Services\AssetMaintenanceService())->notifyDue();
$productionWatch = (new \App\Repositories\ProductionControlRepository())->watchdogCounts();
$removed = (new SystemHealthService())->cleanupTemporary();
$settings->put('last_cron_at', date('Y-m-d H:i:s'));
$settings->put('last_cron_status', 'SUCCESS');
$settings->put('last_cron_seconds', (string) max(0, (int) round(microtime(true) - $started)));
SettingsService::forget();
} catch (Throwable $e) {
    $settings->put('last_cron_status', 'FAILED');
    $safe = preg_replace('/(password|secret|token)[^\\s]*/i', '[redacted]', $e->getMessage()) ?? 'cron failed';
    $settings->put('last_cron_error', mb_substr($safe, 0, 180));
    SettingsService::forget();
    fwrite(STDERR, "cron_failed\n");
    exit(1);
}

$warrantyCount = (int) ($warrantyNotices['notified'] ?? 0);
$maintenanceCount = (int) ($maintenanceDue['notified'] ?? 0);
fwrite(STDOUT, "alerts_created={$alerts} lead_alerts={$leadAlerts} snag_alerts={$snagAlerts} schedule_alerts={$scheduleAlerts} recurring_followups={$recurring} scheduled_reports={$reports} webhook_retries={$webhookRetries} approval_reminders={$approvalReminders} integration_retries={$integrationRetries} field_packs_closed={$fieldPacks} warranty_notices={$warrantyCount} maintenance_notices={$maintenanceCount} production_overdue={$productionWatch['overdue']} temp_removed={$removed}\n");
