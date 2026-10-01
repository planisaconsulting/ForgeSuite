<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\FieldRepository;
use App\Repositories\SettingRepository;
use App\Services\DeviceService;
use App\Services\FieldPackService;
use App\Services\FieldSettings;
use App\Services\FieldSyncService;
use App\Services\PushNoticeService;
use App\Version;

/**
 * Phone and tablet workflows. Desktop modules stay on their own screens.
 */
final class MobileController
{
    public function home(): void
    {
        $userId = (int) auth_user()['id'];
        $wide = can('device.manage_all');
        $fields = new FieldRepository();
        View::render('mobile/home', [
            'title' => 'Today',
            'activeNav' => 'mobile-home',
            'installations' => can('installation.mobile') || can('installations.view') ? $fields->todayInstallations($userId, $wide) : [],
            'nextInstallations' => can('installation.mobile') || can('installations.view') ? $fields->laterInstallations($userId, $wide, 'next') : [],
            'overdueInstallations' => can('installation.mobile') || can('installations.view') ? $fields->laterInstallations($userId, $wide, 'overdue') : [],
            'returns' => can('installation.mobile') || can('installations.view') ? $fields->laterInstallations($userId, $wide, 'return') : [],
            'surveys' => can('site_survey.mobile') || can('site_surveys.view') ? $fields->surveyQueue($userId, $wide) : [],
            'deliveries' => can('delivery.mobile') || can('dispatch.view') ? $fields->deliveryQueue($userId, $wide) : [],
            'workshop' => can('workshop.tablet') || can('workshop.view') ? $fields->workshopQueue() : [],
            'showApprovals' => can('approvals.view'),
        ]);
    }

    public function syncCentre(): void
    {
        $userId = (int) auth_user()['id'];
        $fields = new FieldRepository();
        View::render('mobile/sync', [
            'title' => 'Sync centre',
            'activeNav' => 'mobile-sync',
            'last' => $fields->lastSuccess($userId),
            'failed' => $fields->failedOperations($userId),
            'conflicts' => $fields->pendingConflicts(),
            'packs' => $fields->packsForUser($userId),
            'bytes' => $fields->storageBytes($userId),
            'warning' => (int) FieldSettings::get('offline_storage_warning_mb'),
            'version' => Version::NUMBER,
            'sw' => 'signforge-shell-v3',
            'schema' => FieldSettings::get('schema_version'),
        ]);
    }

    public function more(): void
    {
        View::render('mobile/more', [
            'title' => 'More',
            'activeNav' => 'mobile-more',
            'version' => Version::NUMBER,
            'sw' => 'signforge-shell-v3',
            'schema' => FieldSettings::get('schema_version'),
        ]);
    }

    public function scan(): void
    {
        View::render('mobile/scan', [
            'title' => 'Scan',
            'activeNav' => 'mobile-scan',
        ]);
    }

    public function survey(string $id): void
    {
        $row = (new FieldRepository())->survey((int) $id);
        if ($row === null) {
            abort_not_found('That survey was not found.');
        }
        $fields = new FieldRepository();
        View::render('mobile/survey', [
            'title' => (string) $row['survey_number'],
            'activeNav' => 'mobile-home',
            'survey' => $row,
            'measurements' => $fields->surveyMeasurements((int) $row['id']),
            'notes' => $fields->notesFor('SITE_SURVEY', (int) $row['id']),
            'photos' => $fields->photosFor('SITE_SURVEY', (int) $row['id']),
        ]);
    }

    public function installation(string $id): void
    {
        $fields = new FieldRepository();
        $row = $fields->installation((int) $id);
        if ($row === null) {
            abort_not_found('That installation was not found.');
        }
        $job = $fields->jobFacts((int) $row['job_id']);
        if ($job === null) {
            abort_not_found('That job was not found.');
        }
        $pack = $fields->latestPack((int) auth_user()['id'], 'JOB_INSTALLATION', (int) $row['id']);
        $fresh = $pack === null ? null : (new FieldPackService())->freshness((int) $pack['id'], (int) auth_user()['id']);
        View::render('mobile/install', [
            'title' => (string) $job['job_number'],
            'activeNav' => 'mobile-home',
            'installation' => $row,
            'job' => $job,
            'artwork' => $fields->approvedArtwork((int) $job['id']),
            'checklist' => $fields->checklist((int) $row['id']),
            'photos' => $fields->photosFor('JOB_INSTALLATION', (int) $row['id']),
            'fresh' => $fresh,
        ]);
    }

    public function delivery(string $id): void
    {
        $row = (new FieldRepository())->dispatchRow((int) $id);
        if ($row === null) {
            abort_not_found('That delivery was not found.');
        }
        View::render('mobile/delivery', [
            'title' => (string) $row['dispatch_number'],
            'activeNav' => 'mobile-home',
            'dispatch' => $row,
            'items' => (new FieldRepository())->dispatchItems((int) $row['id']),
        ]);
    }

    public function workshop(): void
    {
        View::render('mobile/workshop', [
            'title' => 'Workshop',
            'activeNav' => 'mobile-workshop',
            'rows' => (new FieldRepository())->workshopQueue(),
        ]);
    }

    public function job(string $id): void
    {
        $job = (new FieldRepository())->jobFacts((int) $id);
        if ($job === null) {
            abort_not_found('That job was not found.');
        }
        View::render('mobile/job', [
            'title' => (string) $job['job_number'],
            'activeNav' => 'mobile-home',
            'job' => $job,
            'artwork' => (new FieldRepository())->approvedArtwork((int) $job['id']),
        ]);
    }

    public function customer(string $id): void
    {
        $row = (new FieldRepository())->customerCard((int) $id);
        if ($row === null) {
            abort_not_found('That customer was not found.');
        }
        $fields = new FieldRepository();
        View::render('mobile/customer', [
            'title' => customer_label($row),
            'activeNav' => 'mobile-more',
            'customer' => $row,
            'quotes' => can('quotes.view') ? $fields->openQuotes((int) $row['id']) : [],
            'jobs' => can('jobs.view') ? $fields->activeJobs((int) $row['id']) : [],
        ]);
    }

    public function quote(string $id): void
    {
        $row = (new FieldRepository())->quoteSummary((int) $id);
        if ($row === null) {
            abort_not_found('That quote was not found.');
        }
        View::render('mobile/quote', [
            'title' => (string) $row['quote_number'],
            'activeNav' => 'mobile-more',
            'quote' => $row,
        ]);
    }

    public function lead(string $id): void
    {
        $row = (new FieldRepository())->leadCard((int) $id);
        if ($row === null) {
            abort_not_found('That lead was not found.');
        }
        View::render('mobile/lead', [
            'title' => (string) $row['lead_number'],
            'activeNav' => 'mobile-more',
            'lead' => $row,
        ]);
    }

    public function devices(): void
    {
        $userId = (int) auth_user()['id'];
        $rows = can('device.manage_all') ? (new FieldRepository())->allDevices() : (new FieldRepository())->devicesForUser($userId);
        View::render('mobile/devices', [
            'title' => 'Devices',
            'activeNav' => 'mobile-devices',
            'rows' => $rows,
            'admin' => can('device.manage_all'),
        ]);
    }

    public function saveDevice(): void
    {
        $name = (string) ($_POST['device_name'] ?? '');
        $id = (int) ($_POST['device_id'] ?? 0);
        if ($id > 0) {
            $error = (new DeviceService())->rename($id, (int) auth_user()['id'], $name);
            flash($error === '' ? 'success' : 'error', $error === '' ? 'Device renamed.' : $error);
        }
        redirect('/m/devices');
    }

    public function revokeDevice(string $id): void
    {
        $error = (new DeviceService())->revoke((int) $id, (int) auth_user()['id']);
        flash($error === '' ? 'success' : 'error', $error === '' ? 'Device revoked. It cannot sync again.' : $error);
        redirect('/m/devices');
    }

    public function conflicts(): void
    {
        View::render('mobile/conflicts', [
            'title' => 'Sync conflicts',
            'activeNav' => 'mobile-sync',
            'rows' => (new FieldRepository())->pendingConflicts(),
        ]);
    }

    public function resolveConflict(string $id): void
    {
        $result = (new FieldSyncService())->resolve(
            (int) $id,
            (string) ($_POST['choice'] ?? ''),
            (string) ($_POST['merged'] ?? ''),
            (int) auth_user()['id']
        );
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/m/conflicts');
    }

    public function settings(): void
    {
        $current = [];
        foreach (FieldSettings::DEFAULTS as $key => $default) {
            $current[$key] = FieldSettings::get($key);
        }
        View::render('mobile/settings', [
            'title' => 'Offline settings',
            'activeNav' => 'mobile-settings',
            'values' => $current,
        ]);
    }

    public function saveSettings(): void
    {
        $repo = new SettingRepository();
        foreach (FieldSettings::DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$key]);
            if ($key === 'schema_version') {
                continue;
            }
            $repo->put($key, mb_substr($value, 0, 40));
        }
        \App\Services\SettingsService::forget();
        flash('success', 'Offline settings saved.');
        redirect('/admin/offline-settings');
    }

    public function photo(string $id): void
    {
        $row = (new FieldRepository())->photo((int) $id);
        if ($row === null || !$this->maySeePhoto($row)) {
            abort_not_found('That photo was not found.');
        }
        $which = (string) ($_GET['copy'] ?? 'thumb');
        $path = match ($which) {
            'original' => (string) ($row['original_path'] ?: $row['display_path']),
            'annotated' => (string) ($row['annotated_path'] ?: $row['display_path']),
            default => (string) $row['thumb_path'],
        };
        $full = base_path($path);
        if (!is_file($full) || !str_starts_with(realpath($full) ?: '', realpath(base_path('storage/field-photos')) ?: 'storage/field-photos')) {
            abort_not_found('That photo was not found.');
        }
        $mime = str_ends_with($full, '.jpg') ? 'image/jpeg' : 'image/png';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($full);
        exit;
    }

    public function config(): void
    {
        json_response([
            'app_version' => Version::NUMBER,
            'sw_version' => 'signforge-shell-v3',
            'schema_version' => FieldSettings::get('schema_version'),
            'offline_enabled' => FieldSettings::enabled(),
            'offline_session_hours' => (int) FieldSettings::get('offline_session_hours'),
            'image_compression_quality' => (int) FieldSettings::get('image_compression_quality'),
            'image_max_upload_bytes' => (int) FieldSettings::get('image_max_upload_bytes'),
            'gps_capture_policy' => FieldSettings::get('gps_capture_policy'),
            'auto_sync' => FieldSettings::get('auto_sync') === '1',
            'storage_warning_mb' => (int) FieldSettings::get('offline_storage_warning_mb'),
            'measurement_sanity_mm' => FieldSettings::get('measurement_sanity_mm'),
        ]);
    }

    public function registerDevice(): void
    {
        $result = (new DeviceService())->register((int) auth_user()['id'], $this->json());
        json_response($result, $result['ok'] ? 200 : 422);
    }

    public function sync(): void
    {
        $result = (new FieldSyncService())->accept((int) auth_user()['id'], $this->json());
        json_response($result, $result['ok'] ? 200 : 403);
    }

    public function downloadPack(): void
    {
        $body = $this->json();
        $device = (new FieldRepository())->deviceByUuid(strtolower(trim((string) ($body['device_uuid'] ?? ''))));
        $result = (new FieldPackService())->download(
            (int) auth_user()['id'],
            (string) ($body['pack_type'] ?? ''),
            (int) ($body['entity_id'] ?? 0),
            (int) ($body['used_bytes'] ?? 0),
            $device !== null && $device['revoked_at'] === null ? (int) $device['id'] : null
        );
        json_response($result, $result['ok'] ? 200 : 422);
    }

    public function subscribe(): void
    {
        $body = $this->json();
        $device = (new FieldRepository())->deviceByUuid(strtolower(trim((string) ($body['device_uuid'] ?? ''))));
        $result = (new PushNoticeService())->subscribe(
            (int) auth_user()['id'],
            $body,
            $device !== null ? (int) $device['id'] : null
        );
        json_response($result, $result['ok'] ? 200 : 422);
    }

    public function report(): void
    {
        $body = $this->json();
        $code = strtoupper(trim((string) ($body['error_code'] ?? 'UNKNOWN')));
        if ($code === '' || preg_match('/PASSWORD|SECRET|API_KEY|TOKEN/', $code) === 1) {
            json_response(['ok' => false, 'message' => 'That report was not stored.'], 422);
        }
        (new FieldRepository())->insertReport([
            'user_id' => (int) auth_user()['id'],
            'app_version' => mb_substr(trim((string) ($body['app_version'] ?? Version::NUMBER)), 0, 20),
            'sw_version' => mb_substr(trim((string) ($body['sw_version'] ?? '')), 0, 40),
            'platform' => mb_substr(trim((string) ($body['platform'] ?? '')), 0, 40),
            'module_name' => mb_substr(trim((string) ($body['module'] ?? 'field')), 0, 40),
            'online_flag' => (string) ($body['online'] ?? '1') === '1' ? 1 : 0,
            'error_code' => mb_substr($code, 0, 40),
        ]);
        json_response(['ok' => true, 'message' => 'Report stored without customer records.']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function maySeePhoto(array $row): bool
    {
        return can('site_surveys.view') || can('installations.view') || can('dispatch.view') || can('jobs.view');
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw === false ? '' : $raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        return $data;
    }
}
