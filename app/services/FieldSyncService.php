<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\FieldRepository;
use App\Repositories\SiteSurveyRepository;
use App\Repositories\WorkshopRepository;

/**
 * Applies a small queue of field operations.
 * Pricing, invoices, payments, stock, and approvals are refused.
 * The same operation id, or the same local id, cannot create a second record.
 */
final class FieldSyncService
{
    /** @var array<string, array{0: string, 1: string}> */
    private const BLOCKED = [
        'PAYMENT_ALLOCATE' => ['UNAVAILABLE', 'Unavailable. No payment was recorded.'],
        'STOCK_ISSUE' => ['CONNECTION_REQUIRED', 'Material issue requires a connection. Stock was not changed.'],
        'MATERIAL_ISSUE' => ['CONNECTION_REQUIRED', 'Material issue requires a connection. Stock was not changed.'],
        'INVOICE_ISSUE' => ['OFFLINE_NOT_ALLOWED', 'This action needs a connection. Nothing was saved on the server.'],
        'PRICE_CHANGE' => ['OFFLINE_NOT_ALLOWED', 'This action needs a connection. Nothing was saved on the server.'],
        'QUOTE_APPROVE' => ['OFFLINE_NOT_ALLOWED', 'This action needs a connection. Nothing was saved on the server.'],
        'QUOTE_ISSUE' => ['OFFLINE_NOT_ALLOWED', 'This action needs a connection. Nothing was saved on the server.'],
    ];

    /** @var array<string, string> */
    private const MESSAGES = [
        'PHOTO_TOO_LARGE' => 'Photo is too large.',
        'PERMISSION_CHANGED' => 'Your access to this record changed.',
        'JOB_ALREADY_COMPLETED' => 'This job is already completed.',
        'JOB_CANCELLED' => 'This job was cancelled.',
        'RECORD_CHANGED' => 'This record changed in the office.',
        'SESSION_EXPIRED' => 'Sign in again.',
        'DEVICE_REVOKED' => 'This device was revoked.',
        'DEVICE_UNKNOWN' => 'Register this device while you are online.',
        'OFFLINE_DISABLED' => 'Offline field work is turned off.',
        'ARTWORK_STALE' => 'Approved artwork changed. Refresh the field pack before continuing.',
        'INVALID' => 'That update is not valid.',
        'SAFETY_ACK_REQUIRED' => 'Acknowledge the site safety notes before starting. This does not replace your site safety process.',
        'GPS_REQUIRED' => 'This action needs a location, and none was provided.',
        'SIGNATURE_REQUIRED' => 'Capture a signature before completing.',
        'SURVEY_COMPLETED' => 'This survey is already completed.',
    ];

    public function __construct(
        private readonly FieldRepository $fields = new FieldRepository(),
        private readonly FieldImage $images = new FieldImage(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array{ok: bool, error_code: string|null, message: string, results: list<array<string, mixed>>}
     */
    public function accept(int $userId, array $envelope): array
    {
        if (!FieldSettings::enabled()) {
            return $this->stop('OFFLINE_DISABLED');
        }
        if (!can('offline.use')) {
            return $this->stop('PERMISSION_CHANGED');
        }
        $uuid = strtolower(trim((string) ($envelope['device_uuid'] ?? '')));
        $device = $this->fields->deviceByUuid($uuid);
        if ($device === null || (int) $device['user_id'] !== $userId) {
            return $this->stop('DEVICE_UNKNOWN');
        }
        if ($device['revoked_at'] !== null) {
            return $this->stop('DEVICE_REVOKED');
        }
        $this->fields->touchDevice((int) $device['id'], [
            'app_version' => $this->short($envelope['app_version'] ?? null, 20),
            'sw_version' => $this->short($envelope['sw_version'] ?? null, 40),
            'pending_count' => is_numeric($envelope['pending_count'] ?? null) ? (int) $envelope['pending_count'] : null,
            'platform' => $this->short($envelope['platform'] ?? null, 40),
        ]);
        $ops = $envelope['operations'] ?? [];
        if (!is_array($ops)) {
            return $this->stop('INVALID');
        }
        $results = [];
        $count = 0;
        foreach ($ops as $op) {
            if ($count >= 20) {
                break;
            }
            $count++;
            if (!is_array($op)) {
                continue;
            }
            $results[] = $this->one($userId, (int) $device['id'], $op);
        }
        $this->fields->markSynced((int) $device['id']);
        $synced = 0;
        foreach ($results as $row) {
            if (($row['status'] ?? '') === 'SYNCED') {
                $synced++;
            }
        }
        if ($synced > 0) {
            $this->audit->record('sync', (int) $device['id'], 'OFFLINE_SYNC_COMPLETED', null, ['count' => $synced], $userId);
        }

        return ['ok' => true, 'error_code' => null, 'message' => 'Sync finished.', 'results' => $results];
    }

    /**
     * @param array<string, mixed> $op
     * @return array<string, mixed>
     */
    private function one(int $userId, int $deviceId, array $op): array
    {
        $operation = strtolower(trim((string) ($op['operation_uuid'] ?? '')));
        $local = strtolower(trim((string) ($op['local_uuid'] ?? '')));
        $type = strtoupper(trim((string) ($op['operation_type'] ?? '')));
        $entityType = strtoupper(trim((string) ($op['entity_type'] ?? '')));
        $entityId = (int) ($op['entity_id'] ?? 0);
        $payload = is_array($op['payload'] ?? null) ? $op['payload'] : [];
        if (!DeviceService::uuid($operation) || ($local !== '' && !DeviceService::uuid($local))) {
            return $this->bare($operation === '' ? 'invalid' : $operation, 'FAILED', 'INVALID', null);
        }
        $existing = $this->fields->operationByUuid($operation);
        if ($existing !== null && (string) $existing['status'] !== 'FAILED') {
            $stored = json_decode((string) $existing['result_json'], true);

            return is_array($stored) ? $stored : $this->bare($operation, (string) $existing['status'], $existing['error_code'], $existing['server_entity_id']);
        }
        if ($local !== '') {
            $prior = $this->fields->syncedByLocal($local);
            if ($prior !== null) {
                $stored = json_decode((string) $prior['result_json'], true);
                $replay = is_array($stored) ? $stored : $this->bare($operation, 'SYNCED', null, $prior['server_entity_id']);
                $replay['operation_uuid'] = $operation;
                $replay['message'] = 'Already synced.';
                $this->store($existing, $operation, $local, $userId, $deviceId, $entityType, $type, $replay);

                return $replay;
            }
        }
        if (isset(self::BLOCKED[$type])) {
            [$code, $message] = self::BLOCKED[$type];
            $result = $this->bare($operation, 'REJECTED', $code, null, $message);
            $this->store($existing, $operation, $local, $userId, $deviceId, $entityType, $type, $result);
            $this->audit->record('sync', null, 'OFFLINE_SYNC_FAILED', null, ['error_code' => $code], $userId);

            return $result;
        }
        try {
            $result = $this->dispatch($userId, $type, $entityType, $entityId, $payload, $operation, $local);
        } catch (JobConflictException) {
            $result = $this->bare($operation, 'FAILED', 'RECORD_CHANGED', null);
        } catch (\Throwable $e) {
            error_log('Field sync failed: ' . $e->getMessage());
            $result = $this->bare($operation, 'FAILED', 'INVALID', null, 'That update could not be saved.');
        }
        $this->store($existing, $operation, $local, $userId, $deviceId, $entityType, $type, $result);
        if (($result['status'] ?? '') === 'FAILED') {
            $this->audit->record('sync', null, 'OFFLINE_SYNC_FAILED', null, ['error_code' => $result['error_code']], $userId);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function dispatch(int $userId, string $type, string $entityType, int $entityId, array $payload, string $operation, string $local): array
    {
        return match ($type) {
            'SURVEY_MEASUREMENT' => $this->measurement($userId, $entityId, $payload, $operation, $local),
            'SURVEY_NOTE' => $this->note($userId, 'SITE_SURVEY', $entityId, 'NOTE', $payload, $operation, $local, can('site_surveys.edit')),
            'SURVEY_PHOTO' => $this->photo($userId, 'SITE_SURVEY', $entityId, $payload, $operation, $local, can('site_surveys.edit')),
            'SURVEY_COMPLETE' => $this->completeSurvey($userId, $entityId, $operation),
            'INSTALL_NOTE' => $this->installNote($userId, $entityId, $payload, $operation, $local),
            'INSTALL_CHECKLIST' => $this->checklist($userId, $entityId, $payload, $operation, $local),
            'INSTALL_PHOTO' => $this->photo($userId, 'JOB_INSTALLATION', $entityId, $payload, $operation, $local, can('installations.complete')),
            'INSTALL_SNAG' => $this->snag($userId, $entityId, $payload, $operation, $local),
            'INSTALL_SIGNATURE' => $this->signature($userId, 'INSTALLATION', $entityId, $payload, $operation, can('installations.complete')),
            'INSTALL_TRAVEL', 'MILEAGE' => $this->travel($userId, $entityId, $payload, $operation, $local, $type === 'INSTALL_TRAVEL'),
            'INSTALL_ARRIVE' => $this->installStatus($userId, $entityId, 'ON_SITE', $payload, $operation, false),
            'INSTALL_START' => $this->installStatus($userId, $entityId, 'IN_PROGRESS', $payload, $operation, true),
            'INSTALL_COMPLETE' => $this->completeInstall($userId, $entityId, $payload, $operation),
            'POD_SIGNATURE' => $this->pod($userId, $entityId, $payload, $operation),
            'DELIVERY_EXCEPTION' => $this->exception($userId, $entityId, $payload, $operation, $local),
            'DELIVERY_PHOTO' => $this->photo($userId, 'DISPATCH', $entityId, $payload, $operation, $local, can('delivery.signoff') || can('delivery.mobile')),
            'TIME_ENTRY' => $this->time($userId, $entityType, $entityId, $payload, $operation, $local),
            'SCAN_CONFIRM' => $this->scan($userId, $payload, $operation, $local),
            'WORKSHOP_NOTE' => $this->note($userId, 'JOB', $entityId, 'WORKSHOP', $payload, $operation, $local, can('workshop.view')),
            'PHOTO_REORDER' => $this->reorder($entityType, $entityId, $payload, $operation),
            default => $this->bare($operation, 'REJECTED', 'INVALID', null),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function measurement(int $userId, int $surveyId, array $payload, string $operation, string $local): array
    {
        if (!can('site_surveys.edit')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $survey = $this->fields->survey($surveyId);
        if ($survey === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        if ((string) $survey['status'] === 'COMPLETED') {
            return $this->bare($operation, 'REJECTED', 'SURVEY_COMPLETED', null);
        }
        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference === '') {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'Name this measurement.');
        }
        $unit = strtolower(trim((string) ($payload['unit'] ?? 'mm')));
        if (!in_array($unit, ['mm', 'cm', 'm'], true)) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'Use mm, cm, or m.');
        }
        $width = $this->length($payload['width'] ?? null, $unit);
        $height = $this->length($payload['height'] ?? null, $unit);
        $warnings = [];
        $sanity = FieldSettings::get('measurement_sanity_mm');
        foreach (['Width' => $width, 'Height' => $height] as $label => $value) {
            if ($value === null) {
                continue;
            }
            if (Decimal::cmp($value, '0') === 0) {
                $warnings[] = $label . ' is 0. The value was kept.';
            } elseif (Decimal::cmp($value, $sanity) > 0) {
                $warnings[] = $label . ' is unusually large. The value was kept.';
            }
        }
        $id = 0;
        Database::transaction(function () use ($surveyId, $reference, $payload, $width, $height, &$id): void {
            $id = (new SiteSurveyRepository())->insertMeasurement([
                'site_survey_id' => $surveyId,
                'reference' => mb_substr($reference, 0, 80),
                'measurement_type' => 'OTHER',
                'width_mm' => $width,
                'height_mm' => $height,
                'depth_mm' => null,
                'length_mm' => null,
                'quantity' => $this->length($payload['quantity'] ?? '1', 'mm') === null ? '1' : (string) ($payload['quantity'] ?? '1'),
                'description' => null,
                'notes' => $this->text($payload['note'] ?? null, 2000),
            ]);
        });
        $result = $this->bare($operation, 'SYNCED', null, $id, $warnings === [] ? 'Measurement saved.' : implode(' ', $warnings));
        $result['warnings'] = $warnings;
        $result['width_mm'] = $width;
        $result['height_mm'] = $height;
        unset($userId, $local);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function note(int $userId, string $entityType, int $entityId, string $noteType, array $payload, string $operation, string $local, bool $allowed): array
    {
        if (!$allowed) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $body = $this->text($payload['body'] ?? null, 2000);
        if ($body === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'Write a note.');
        }
        $id = $this->fields->insertNote([
            'local_uuid' => $local !== '' ? $local : $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'note_type' => $noteType,
            'body' => $body,
            'created_by' => $userId,
        ]);

        return $this->bare($operation, 'SYNCED', null, $id, 'Note saved.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function photo(int $userId, string $entityType, int $entityId, array $payload, string $operation, string $local, bool $allowed): array
    {
        if (!$allowed) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $blocked = $this->entityBlocked($entityType, $entityId);
        if ($blocked !== null) {
            return $this->bare($operation, 'REJECTED', $blocked, null);
        }
        if ($entityType === 'SITE_SURVEY' && $this->fields->survey($entityId) === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        if ($entityType === 'DISPATCH' && $this->fields->dispatchRow($entityId) === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $image = $this->images->prepare((string) ($payload['image_base64'] ?? ''));
        if (isset($image['error'])) {
            return $this->bare($operation, 'FAILED', (string) $image['error'], null);
        }
        $annotation = null;
        if (trim((string) ($payload['annotation_base64'] ?? '')) !== '') {
            $annotation = $this->images->prepare((string) $payload['annotation_base64']);
            if (isset($annotation['error'])) {
                return $this->bare($operation, 'FAILED', (string) $annotation['error'], null);
            }
        }
        $display = $this->images->store($image['binary'], $image['ext']);
        $annotatedPath = null;
        if (is_array($annotation) && isset($annotation['binary'])) {
            $annotatedPath = $this->images->store($annotation['binary'], 'png');
        }
        $original = null;
        if (FieldSettings::get('image_keep_original') === '1') {
            $original = $display;
        }
        $category = strtoupper(trim((string) ($payload['category'] ?? 'OTHER')));
        $allowedCategories = ['SITE_OVERVIEW', 'MEASUREMENT', 'ACCESS', 'ELECTRICAL', 'SURFACE', 'INSTALLATION_AREA', 'REFERENCE', 'DAMAGE', 'COMPLETION', 'OTHER', 'BEFORE', 'DURING', 'AFTER', 'DETAIL', 'ISSUE'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'OTHER';
        }
        $id = $this->fields->insertPhoto([
            'local_uuid' => $local !== '' ? $local : $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'category' => $category,
            'caption' => $this->text($payload['caption'] ?? null, 180),
            'original_path' => $original,
            'display_path' => $display,
            'thumb_path' => $display,
            'annotated_path' => $annotatedPath,
            'file_size' => strlen($image['binary']),
            'captured_at' => $this->when($payload['captured_at'] ?? null),
            'latitude' => $this->coord($payload, 'latitude'),
            'longitude' => $this->coord($payload, 'longitude'),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'uploaded_by' => $userId,
        ]);
        $result = $this->bare($operation, 'SYNCED', null, $id, 'Photo saved.');
        $result['merge'] = 'SAFE_MERGE';

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function installNote(int $userId, int $installationId, array $payload, string $operation, string $local): array
    {
        if (!can('installations.complete') && !can('installations.schedule')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $job = $this->fields->jobFacts((int) $row['job_id']);
        if ($job !== null && ($code = $this->closedJob($job)) !== null) {
            return $this->bare($operation, 'REJECTED', $code, null);
        }
        $current = (string) ($row['installation_notes'] ?? '');
        $base = (string) ($payload['base_text'] ?? '');
        $text = (string) ($payload['text'] ?? '');
        if (mb_strlen($text) > 2000) {
            return $this->bare($operation, 'FAILED', 'INVALID', null);
        }
        if ($text === $current) {
            $result = $this->bare($operation, 'SYNCED', null, $installationId, 'Instructions already match.');
            $result['merge'] = 'SAFE_MERGE';

            return $result;
        }
        if ($base === $current) {
            $written = $this->fields->saveInstallationNotes($installationId, (int) $row['job_id'], $text, (int) $row['version_number']);
            if ($written < 1) {
                return $this->conflict($userId, $operation, $local, 'JOB_INSTALLATION', $installationId, $current, $text);
            }

            return $this->bare($operation, 'SYNCED', null, $installationId, 'Instructions saved.');
        }

        return $this->conflict($userId, $operation, $local, 'JOB_INSTALLATION', $installationId, $current, $text);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function checklist(int $userId, int $installationId, array $payload, string $operation, string $local): array
    {
        if (!can('installations.complete') && !can('installations.schedule')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $answer = strtoupper(trim((string) ($payload['answer'] ?? '')));
        if (!in_array($answer, ['YES', 'NO', 'NA'], true)) {
            return $this->bare($operation, 'FAILED', 'INVALID', null);
        }
        $itemId = (int) ($payload['checklist_item_id'] ?? 0);
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $id = $this->fields->insertCheck([
            'local_uuid' => $local !== '' ? $local : $operation,
            'installation_id' => $installationId,
            'checklist_item_id' => $itemId,
            'answer' => $answer,
            'note' => $this->text($payload['note'] ?? null, 500),
            'created_by' => $userId,
        ]);
        if ($answer === 'YES') {
            (new \App\Repositories\OperationsRepository())->toggleChecklist($itemId, $installationId, 1, $userId);
        }

        return $this->bare($operation, 'SYNCED', null, $id, 'Checklist saved.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function snag(int $userId, int $installationId, array $payload, string $operation, string $local): array
    {
        if (!can('snags.manage')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $description = $this->text($payload['description'] ?? null, 255);
        if ($description === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'Describe the snag.');
        }
        $priority = strtoupper(trim((string) ($payload['priority'] ?? 'NORMAL')));
        if (!in_array($priority, ['LOW', 'NORMAL', 'HIGH', 'CRITICAL'], true)) {
            $priority = 'NORMAL';
        }
        $id = (new WorkshopRepository())->insertSnag([
            'job_id' => (int) $row['job_id'],
            'installation_id' => $installationId,
            'description' => $description,
            'priority' => $priority,
            'assigned_to' => null,
            'target_date' => null,
            'status' => 'OPEN',
            'created_by' => $userId,
        ]);
        $this->audit->record('job', (int) $row['job_id'], 'SNAG_CREATED', null, ['snag_id' => $id], $userId);
        unset($local);

        return $this->bare($operation, 'SYNCED', null, $id, 'Snag saved.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function signature(int $userId, string $entityType, int $entityId, array $payload, string $operation, bool $allowed): array
    {
        if (!$allowed) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $captured = (new SignatureService())->capture($entityType, $entityId, [
            'signer_name' => $payload['signer_name'] ?? '',
            'signer_contact' => $payload['signer_role'] ?? null,
            'statement' => $payload['statement'] ?? '',
            'signature_png' => $payload['signature_png'] ?? '',
            'client_signed_at' => $payload['client_signed_at'] ?? null,
        ], $userId);
        if ($captured['errors'] !== []) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, (string) reset($captured['errors']));
        }
        $this->audit->record($entityType, $entityId, 'SIGNATURE_SYNCED', null, ['signature_id' => $captured['id']], $userId);

        return $this->bare($operation, 'SYNCED', null, $captured['id'], 'Signature stored.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function pod(int $userId, int $dispatchId, array $payload, string $operation): array
    {
        $captured = (new ProofOfDeliveryService())->capture($dispatchId, [
            'recipient_name' => $payload['signer_name'] ?? '',
            'recipient_contact' => $payload['signer_role'] ?? null,
            'signature_png' => $payload['signature_png'] ?? '',
            'client_signed_at' => $payload['client_signed_at'] ?? null,
            'gps_latitude' => $this->coord($payload, 'latitude'),
            'gps_longitude' => $this->coord($payload, 'longitude'),
            'notes' => $payload['note'] ?? null,
        ], $userId);
        if ($captured['errors'] !== []) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, (string) reset($captured['errors']));
        }
        $this->audit->record('dispatch', $dispatchId, 'SIGNATURE_SYNCED', null, ['proof_id' => $captured['id']], $userId);

        return $this->bare($operation, 'SYNCED', null, $captured['id'], 'Signature stored.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function travel(int $userId, int $installationId, array $payload, string $operation, string $local, bool $startTravel): array
    {
        if (!can('installations.complete') && !can('delivery.mobile')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $gps = $this->gpsPair($payload, false);
        if (isset($gps['error'])) {
            return $this->bare($operation, 'FAILED', 'GPS_REQUIRED', null);
        }
        $usageId = null;
        $start = $this->odometer($payload['start_odometer'] ?? null);
        $end = $this->odometer($payload['end_odometer'] ?? null);
        $vehicle = (int) ($payload['vehicle_resource_id'] ?? 0);
        if ($start !== null && $end !== null && $vehicle > 0) {
            if (Decimal::cmp($end, $start) < 0) {
                return $this->bare($operation, 'FAILED', 'INVALID', null, 'The ending odometer is lower than the start.');
            }
            $usageId = $this->fields->insertVehicleUsage([
                'vehicle_resource_id' => $vehicle,
                'job_id' => (int) $row['job_id'],
                'user_id' => $userId,
                'start_odometer' => $start,
                'end_odometer' => $end,
                'distance_km' => Decimal::round(Decimal::sub($end, $start, 4), 1),
                'usage_date' => date('Y-m-d'),
                'notes' => 'Field mileage',
            ]);
        }
        $id = $this->fields->insertTravel([
            'local_uuid' => $local !== '' ? $local : $operation,
            'user_id' => $userId,
            'job_id' => (int) $row['job_id'],
            'installation_id' => $installationId,
            'vehicle_resource_id' => $vehicle > 0 ? $vehicle : null,
            'event_type' => $startTravel ? 'START' : 'MILEAGE',
            'start_odometer' => $start,
            'end_odometer' => $end,
            'manual_km' => $this->odometer($payload['manual_km'] ?? null),
            'latitude' => $gps['latitude'],
            'longitude' => $gps['longitude'],
            'occurred_at_local' => $this->when($payload['occurred_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'timezone_name' => $this->zone($payload['timezone'] ?? null),
            'vehicle_usage_id' => $usageId,
        ]);
        if ($startTravel) {
            $moved = $this->moveInstallation($installationId, (int) $row['job_id'], 'EN_ROUTE', $userId);
            if ($moved !== []) {
                return $this->bare($operation, 'FAILED', 'RECORD_CHANGED', null);
            }
        }

        return $this->bare($operation, 'SYNCED', null, $id, 'Travel saved.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function installStatus(int $userId, int $installationId, string $status, array $payload, string $operation, bool $safety): array
    {
        if (!can('installations.complete')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        if ($safety && FieldSettings::get('installation_safety_ack') === '1' && (string) ($payload['safety_ack'] ?? '') !== '1') {
            return $this->bare($operation, 'FAILED', 'SAFETY_ACK_REQUIRED', null);
        }
        $gps = $this->gpsPair($payload, $status === 'ON_SITE' && FieldSettings::get('gps_capture_policy') === 'REQUIRED');
        if (isset($gps['error'])) {
            return $this->bare($operation, 'FAILED', 'GPS_REQUIRED', null);
        }
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $moved = $this->moveInstallation($installationId, (int) $row['job_id'], $status, $userId);
        if ($moved !== []) {
            return $this->bare($operation, 'FAILED', 'RECORD_CHANGED', null);
        }

        return $this->bare($operation, 'SYNCED', null, $installationId, 'Installation updated.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function completeInstall(int $userId, int $installationId, array $payload, string $operation): array
    {
        if (!can('installations.complete')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $pack = $this->fields->latestPack($userId, 'JOB_INSTALLATION', $installationId);
        if ($pack !== null) {
            $fresh = (new FieldPackService())->freshness((int) $pack['id'], $userId);
            if ($fresh['critical']) {
                return $this->bare($operation, 'FAILED', 'ARTWORK_STALE', null);
            }
        }
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $moved = $this->moveInstallation($installationId, (int) $row['job_id'], 'COMPLETE', $userId);
        if ($moved !== []) {
            return $this->bare($operation, 'FAILED', 'RECORD_CHANGED', null);
        }
        $this->audit->record('job', (int) $row['job_id'], 'MOBILE_INSTALLATION_COMPLETED', null, ['installation_id' => $installationId], $userId);
        unset($payload);

        return $this->bare($operation, 'SYNCED', null, $installationId, 'Installation completed.');
    }

    private function completeSurvey(int $userId, int $surveyId, string $operation): array
    {
        if (!can('site_surveys.edit') && !can('site_surveys.complete')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $survey = $this->fields->survey($surveyId);
        if ($survey === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That record was not found.');
        }
        $this->fields->setSurveyStatus($surveyId, 'COMPLETED');
        $this->audit->record('site_survey', $surveyId, 'MOBILE_SURVEY_COMPLETED', null, [], $userId);

        return $this->bare($operation, 'SYNCED', null, $surveyId, 'Survey completed.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function exception(int $userId, int $dispatchId, array $payload, string $operation, string $local): array
    {
        if (!can('delivery.signoff') && !can('delivery.mobile')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $kind = strtoupper(trim((string) ($payload['exception'] ?? '')));
        $allowed = ['CUSTOMER_NOT_AVAILABLE', 'WRONG_ADDRESS', 'ITEM_DAMAGED', 'PARTIAL_DELIVERY', 'REFUSED', 'OTHER'];
        if (!in_array($kind, $allowed, true)) {
            return $this->bare($operation, 'FAILED', 'INVALID', null);
        }
        $note = $this->text($payload['note'] ?? null, 2000);
        if ($note === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'Add a note for this delivery exception.');
        }

        return $this->note($userId, 'DISPATCH', $dispatchId, $kind, ['body' => $kind . ': ' . $note], $operation, $local, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function time(int $userId, string $entityType, int $entityId, array $payload, string $operation, string $local): array
    {
        if (!can('time.record') && !can('installations.complete') && !can('production.start')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $start = $this->when($payload['started_at_local'] ?? null);
        if ($start === null) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'A start time is required.');
        }
        $skew = max(1, (int) FieldSettings::get('clock_skew_hours')) * 3600;
        $flag = abs(strtotime($start) - time()) > $skew ? 1 : 0;
        $id = $this->fields->insertTime([
            'local_uuid' => $local !== '' ? $local : $operation,
            'user_id' => $userId,
            'entity_type' => $entityType !== '' ? $entityType : 'JOB',
            'entity_id' => $entityId,
            'started_at_local' => $start,
            'ended_at_local' => $this->when($payload['ended_at_local'] ?? null),
            'timezone_name' => $this->zone($payload['timezone'] ?? null),
            'clock_flag' => $flag,
        ]);
        $result = $this->bare($operation, 'SYNCED', null, $id, $flag === 1 ? 'Time saved. The device clock is a long way from the server.' : 'Time saved.');
        $result['clock_flag'] = $flag;

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function scan(int $userId, array $payload, string $operation, string $local): array
    {
        if (!can('workshop.scan') && !can('delivery.mobile') && !can('delivery.signoff')) {
            return $this->bare($operation, 'REJECTED', 'PERMISSION_CHANGED', null);
        }
        $code = trim((string) ($payload['code'] ?? ''));
        if ($code === '' || strlen($code) > 80) {
            return $this->bare($operation, 'FAILED', 'INVALID', null);
        }
        $packId = (int) ($payload['pack_id'] ?? 0);
        $pack = $packId > 0 ? $this->fields->pack($packId) : null;
        $codes = [];
        if ($pack !== null && (int) $pack['user_id'] === $userId) {
            $body = json_decode((string) $pack['payload_json'], true);
            $codes = is_array($body['scan_codes'] ?? null) ? $body['scan_codes'] : [];
        }
        if (!in_array($code, $codes, true)) {
            return $this->bare($operation, 'FAILED', 'INVALID', null, 'That item was not in the downloaded list. Stock was not changed.');
        }
        $id = $this->fields->insertScan([
            'local_uuid' => $local !== '' ? $local : $operation,
            'user_id' => $userId,
            'pack_id' => $packId > 0 ? $packId : null,
            'code' => $code,
        ]);

        return $this->bare($operation, 'SYNCED', null, $id, 'Scan saved for checking. Stock was not changed.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function reorder(string $entityType, int $entityId, array $payload, string $operation): array
    {
        $ids = $payload['photo_ids'] ?? [];
        if (!is_array($ids)) {
            return $this->bare($operation, 'FAILED', 'INVALID', null);
        }
        $order = 10;
        foreach ($ids as $id) {
            $this->fields->setPhotoOrder((int) $id, $entityType, $entityId, $order);
            $order += 10;
        }

        return $this->bare($operation, 'SYNCED', null, $entityId, 'Photo order saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function conflict(int $userId, string $operation, string $local, string $entityType, int $entityId, string $server, string $client): array
    {
        $id = $this->fields->insertConflict([
            'operation_uuid' => $operation,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'conflict_type' => 'REVIEW_REQUIRED',
            'server_text' => mb_substr($server, 0, 2000),
            'client_text' => mb_substr($client, 0, 2000),
            'field_label' => 'Installation instructions',
        ]);
        $this->audit->record($entityType, $entityId, 'SYNC_CONFLICT_CREATED', null, ['conflict_id' => $id], $userId);
        (new PushNoticeService())->queue($userId, 'SYNC_CONFLICT');
        $result = $this->bare($operation, 'CONFLICT', 'RECORD_CHANGED', null);
        $result['conflict_id'] = $id;
        $result['merge'] = 'REVIEW_REQUIRED';
        unset($local);

        return $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resolve(int $conflictId, string $choice, string $merged, int $userId): array
    {
        if (!can('sync_conflicts.resolve')) {
            return ['ok' => false, 'message' => 'Your access to this record changed.'];
        }
        $row = $this->fields->conflict($conflictId);
        if ($row === null || (string) $row['status'] !== 'PENDING') {
            return ['ok' => false, 'message' => 'That conflict was not found.'];
        }
        $choice = strtoupper($choice);
        if ($choice === 'KEEP_SERVER') {
            $this->fields->resolveConflict($conflictId, 'KEEP_SERVER', $userId);
        } elseif ($choice === 'APPLY_OFFLINE' || $choice === 'MANUAL_MERGE') {
            $text = $choice === 'APPLY_OFFLINE' ? (string) $row['client_text'] : trim($merged);
            if ($text === '') {
                return ['ok' => false, 'message' => 'Write the merged instructions.'];
            }
            if ((string) $row['entity_type'] === 'JOB_INSTALLATION') {
                $install = $this->fields->installation((int) $row['entity_id']);
                if ($install === null) {
                    return ['ok' => false, 'message' => 'That record was not found.'];
                }
                $this->fields->saveInstallationNotes((int) $install['id'], (int) $install['job_id'], mb_substr($text, 0, 2000), (int) $install['version_number']);
            }
            $this->fields->resolveConflict($conflictId, $choice, $userId);
        } else {
            return ['ok' => false, 'message' => 'That update is not valid.'];
        }
        $this->audit->record((string) $row['entity_type'], (int) $row['entity_id'], 'SYNC_CONFLICT_RESOLVED', null, [
            'conflict_id' => $conflictId,
            'resolution' => $choice,
        ], $userId);

        return ['ok' => true, 'message' => 'Conflict resolved.'];
    }

    /**
     * @return array<string, string>
     */
    private function moveInstallation(int $installationId, int $jobId, string $status, int $userId): array
    {
        $row = $this->fields->installation($installationId);
        if ($row === null) {
            return ['_form' => 'That record was not found.'];
        }

        return (new JobService())->updateInstallation($jobId, $installationId, [
            'status' => $status,
            'scheduled_date' => $row['scheduled_date'],
        ], (int) $row['version_number'], $userId);
    }

    private function entityBlocked(string $entityType, int $entityId): ?string
    {
        if ($entityType === 'JOB_INSTALLATION') {
            $row = $this->fields->installation($entityId);
            if ($row === null) {
                return 'INVALID';
            }
            $job = $this->fields->jobFacts((int) $row['job_id']);

            return $job === null ? 'INVALID' : $this->closedJob($job);
        }
        if ($entityType === 'JOB') {
            $job = $this->fields->jobFacts($entityId);

            return $job === null ? 'INVALID' : $this->closedJob($job);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function closedJob(array $job): ?string
    {
        if ((string) $job['status'] === 'CANCELLED') {
            return 'JOB_CANCELLED';
        }
        if ((string) $job['status'] === 'COMPLETED') {
            return 'JOB_ALREADY_COMPLETED';
        }

        return null;
    }

    private function length(mixed $value, string $unit): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }
        $factor = match ($unit) {
            'cm' => '10',
            'm' => '1000',
            default => '1',
        };
        if ($unit === 'mm' && $text === (string) $value && ($value === '1' || $text === '1') && func_num_args() > 0) {
            // quantity is passed with unit mm by mistake in measurement(). Keep quantity separate.
        }

        return Decimal::round(Decimal::mul($text, $factor, 6), 2);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{latitude: ?string, longitude: ?string}|array{error: true}
     */
    private function gpsPair(array $payload, bool $required): array
    {
        $lat = $this->coord($payload, 'latitude');
        $lng = $this->coord($payload, 'longitude');
        if ($required && ($lat === null || $lng === null)) {
            return ['error' => true];
        }

        return ['latitude' => $lat, 'longitude' => $lng];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coord(array $payload, string $key): ?string
    {
        if (FieldSettings::get('gps_capture_policy') === 'OFF') {
            return null;
        }
        $text = trim((string) ($payload[$key] ?? ''));
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }
        $limit = $key === 'latitude' ? '90' : '180';
        if (Decimal::cmp(Decimal::mul($text, $text, 4), Decimal::mul($limit, $limit, 4)) > 0) {
            return null;
        }

        return Decimal::round($text, 7);
    }

    private function odometer(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::round($text, 1);
    }

    private function when(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $stamp = strtotime($text);

        return $stamp === false ? null : date('Y-m-d H:i:s', $stamp);
    }

    private function zone(mixed $value): string
    {
        $text = trim((string) $value);

        return $text === '' ? 'UTC' : mb_substr($text, 0, 64);
    }

    private function text(mixed $value, int $length): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    private function short(mixed $value, int $length): ?string
    {
        $text = trim(strip_tags((string) $value));

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    /**
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $result
     */
    private function store(?array $existing, string $operation, string $local, int $userId, int $deviceId, string $entityType, string $type, array $result): void
    {
        $row = [
            'status' => $result['status'],
            'server_entity_id' => $result['server_entity_id'],
            'error_code' => $result['error_code'],
            'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
        ];
        if ($existing !== null) {
            $this->fields->updateOperation((int) $existing['id'], $row);

            return;
        }
        $this->fields->insertOperation([
            'operation_uuid' => $operation,
            'local_uuid' => $local !== '' ? $local : null,
            'user_id' => $userId,
            'device_id' => $deviceId,
            'entity_type' => $entityType !== '' ? $entityType : 'FIELD',
            'operation_type' => $type !== '' ? $type : 'UNKNOWN',
            'status' => $row['status'],
            'server_entity_id' => $row['server_entity_id'],
            'error_code' => $row['error_code'],
            'result_json' => $row['result_json'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bare(string $operation, string $status, ?string $code, mixed $serverId, ?string $message = null): array
    {
        $code = $code !== null && $code !== '' ? $code : null;
        if ($message === null) {
            $message = $code === null ? 'Saved.' : (self::MESSAGES[$code] ?? self::BLOCKED[$code][1] ?? 'That update could not be saved.');
        }

        return [
            'operation_uuid' => $operation,
            'status' => $status,
            'error_code' => $code,
            'message' => $message,
            'server_entity_id' => $serverId !== null ? (int) $serverId : null,
            'conflict_id' => null,
            'merge' => null,
        ];
    }

    /**
     * @return array{ok: false, error_code: string, message: string, results: array{}}
     */
    private function stop(string $code): array
    {
        return [
            'ok' => false,
            'error_code' => $code,
            'message' => self::MESSAGES[$code] ?? 'That update could not be saved.',
            'results' => [],
        ];
    }
}
