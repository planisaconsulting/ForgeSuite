<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FieldRepository;

/**
 * A field pack is the assigned job, survey, or delivery. It is not a copy of the customer database.
 * The phone is not a backup. After a successful sync the server record is the source.
 */
final class FieldPackService
{
    public function __construct(
        private readonly FieldRepository $fields = new FieldRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{ok: bool, error_code: string|null, message: string, pack: array<string, mixed>|null}
     */
    public function download(int $userId, string $type, int $entityId, int $usedBytes, ?int $deviceId): array
    {
        if (!FieldSettings::enabled()) {
            return $this->fail('OFFLINE_DISABLED', 'Offline field work is turned off.');
        }
        if (!can('field_pack.download')) {
            return $this->fail('PERMISSION_CHANGED', 'Your access to this record changed.');
        }
        $limit = (int) FieldSettings::get('offline_storage_warning_mb') * 1024 * 1024;
        if ($usedBytes > 0 && $limit > 0 && $usedBytes >= $limit) {
            return $this->fail('STORAGE_LIMIT', 'Offline storage is too full for a new field pack. Unsynced work was left as it is.');
        }
        $built = $this->build($type, $entityId);
        if ($built === null) {
            return $this->fail('INVALID', 'That record was not found.');
        }
        $hours = max(1, (int) FieldSettings::get('field_pack_max_age_hours'));
        $id = $this->fields->insertPack([
            'pack_uuid' => $this->uuid(),
            'user_id' => $userId,
            'device_id' => $deviceId,
            'pack_type' => $built['pack_type'],
            'entity_type' => $built['entity_type'],
            'entity_id' => $entityId,
            'artwork_revision' => $built['artwork_revision'],
            'critical_hash' => $built['critical_hash'],
            'payload_json' => json_encode($built['payload'], JSON_THROW_ON_ERROR),
            'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600),
        ]);
        $this->audit->record('field_pack', $id, 'FIELD_PACK_DOWNLOADED', null, [
            'pack_type' => $built['pack_type'],
            'entity_id' => $entityId,
        ], $userId);
        $pack = $this->fields->pack($id);

        return ['ok' => true, 'error_code' => null, 'message' => 'Field pack downloaded.', 'pack' => $this->present($pack)];
    }

    /**
     * @return array{ok: bool, stale: bool, critical: bool, message: string, pack: array<string, mixed>|null}
     */
    public function freshness(int $packId, int $userId): array
    {
        $pack = $this->fields->pack($packId);
        if ($pack === null || ((int) $pack['user_id'] !== $userId && !can('device.manage_all'))) {
            return ['ok' => false, 'stale' => false, 'critical' => false, 'message' => 'That field pack was not found.', 'pack' => null];
        }
        $payload = json_decode((string) $pack['payload_json'], true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $live = $this->liveCritical($pack);
        $critical = $live !== null && $live['hash'] !== (string) $pack['critical_hash'];
        if ($critical && (string) $pack['status'] === 'ACTIVE') {
            $this->fields->setPackStatus($packId, 'STALE');
            $pack = $this->fields->pack($packId);
        }

        return [
            'ok' => true,
            'stale' => $critical,
            'critical' => $critical,
            'message' => $critical
                ? 'Update available. Approved artwork, the address, or the job status changed. Refresh before the critical step.'
                : 'Available offline.',
            'pack' => $this->present($pack),
        ];
    }

    public function cleanup(): int
    {
        $days = max(1, (int) FieldSettings::get('field_pack_cleanup_days'));

        return $this->fields->closeOldPacks($days);
    }

    /**
     * @param array<string, mixed>|null $pack
     * @return array<string, mixed>|null
     */
    public function present(?array $pack): ?array
    {
        if ($pack === null) {
            return null;
        }
        $payload = json_decode((string) $pack['payload_json'], true);

        return [
            'id' => (int) $pack['id'],
            'pack_uuid' => $pack['pack_uuid'],
            'pack_type' => $pack['pack_type'],
            'entity_type' => $pack['entity_type'],
            'entity_id' => (int) $pack['entity_id'],
            'artwork_revision' => (int) $pack['artwork_revision'],
            'status' => $pack['status'],
            'downloaded_at' => $pack['downloaded_at'],
            'expires_at' => $pack['expires_at'],
            'offline_until' => date('Y-m-d H:i:s', time() + max(1, (int) FieldSettings::get('offline_session_hours')) * 3600),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @return array{pack_type: string, entity_type: string, artwork_revision: int, critical_hash: string, payload: array<string, mixed>}|null
     */
    private function build(string $type, int $entityId): ?array
    {
        $type = strtoupper($type);
        if ($type === 'SURVEY') {
            $survey = $this->fields->survey($entityId);
            if ($survey === null || !can('site_surveys.view')) {
                return null;
            }
            $payload = [
                'survey_number' => $survey['survey_number'],
                'site_name' => $survey['site_name'],
                'customer' => customer_label($survey),
                'phone' => $survey['site_contact_phone'] ?: ($survey['mobile'] ?: $survey['phone']),
                'address' => trim((string) $survey['address_line_1'] . ' ' . (string) $survey['city']),
                'access_notes' => (string) ($survey['access_notes'] ?? ''),
                'electrical_notes' => (string) ($survey['electrical_notes'] ?? ''),
                'installation_notes' => (string) ($survey['installation_notes'] ?? ''),
                'measurements' => $this->fields->surveyMeasurements($entityId),
                'risks' => trim((string) ($survey['height_notes'] ?? '') . ' ' . (string) ($survey['special_access'] ?? '')),
                'emergency_contact' => (string) ($survey['site_contact_name'] ?? ''),
                'scan_codes' => [],
                'critical' => ['status' => $survey['status']],
            ];

            return [
                'pack_type' => 'SURVEY',
                'entity_type' => 'SITE_SURVEY',
                'artwork_revision' => 0,
                'critical_hash' => hash('sha256', (string) $survey['status']),
                'payload' => $payload,
            ];
        }
        if ($type === 'INSTALLATION') {
            $install = $this->fields->installation($entityId);
            if ($install === null || !can('installations.view')) {
                return null;
            }
            $job = $this->fields->jobFacts((int) $install['job_id']);
            if ($job === null) {
                return null;
            }
            $art = $this->fields->approvedArtwork((int) $job['id']);
            $revision = $art === null ? 0 : (int) $art['revision_number'];
            $critical = $this->critical($job, $revision);
            $payload = [
                'job_id' => (int) $job['id'],
                'job_number' => $job['job_number'],
                'title' => $job['title'],
                'customer' => customer_label($job),
                'phone' => $job['site_contact_phone'] ?: ($job['mobile'] ?: $job['phone']),
                'address' => (string) ($install['site_address'] ?: $job['site_address']),
                'description' => (string) ($job['description'] ?? ''),
                'installation_notes' => (string) ($install['installation_notes'] ?? ''),
                'job_instructions' => (string) ($job['installation_notes'] ?? ''),
                'customer_notes' => (string) ($job['customer_notes'] ?? ''),
                'artwork' => $art === null ? null : [
                    'id' => (int) $art['id'],
                    'title' => $art['title'],
                    'revision' => $revision,
                    'approved_at' => $art['customer_approved_at'],
                    'label' => 'APPROVED REVISION',
                ],
                'checklist' => $this->fields->checklist($entityId),
                'measurements' => [],
                'risks' => (string) ($job['installation_notes'] ?? ''),
                'emergency_contact' => (string) ($job['site_contact_name'] ?? ''),
                'safety_notice' => 'Acknowledge site risks, electrical work, height, and equipment before you start. This does not replace your site safety process.',
                'scan_codes' => [],
                'critical' => $critical,
            ];

            return [
                'pack_type' => 'INSTALLATION',
                'entity_type' => 'JOB_INSTALLATION',
                'artwork_revision' => $revision,
                'critical_hash' => hash('sha256', json_encode($critical, JSON_THROW_ON_ERROR)),
                'payload' => $payload,
            ];
        }
        if ($type === 'DELIVERY') {
            $dispatch = $this->fields->dispatchRow($entityId);
            if ($dispatch === null || !can('dispatch.view')) {
                return null;
            }
            $items = $this->fields->dispatchItems($entityId);
            $codes = [];
            foreach ($items as $item) {
                $codes[] = (string) $item['description'];
            }
            $payload = [
                'dispatch_number' => $dispatch['dispatch_number'],
                'job_number' => $dispatch['job_number'],
                'customer' => customer_label($dispatch),
                'phone' => $dispatch['mobile'] ?: $dispatch['phone'],
                'address' => (string) ($dispatch['site_address'] ?? ''),
                'items' => $items,
                'scan_codes' => $codes,
                'critical' => ['status' => $dispatch['status']],
            ];

            return [
                'pack_type' => 'DELIVERY',
                'entity_type' => 'DISPATCH',
                'artwork_revision' => 0,
                'critical_hash' => hash('sha256', (string) $dispatch['status']),
                'payload' => $payload,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $pack
     * @return array{hash: string}|null
     */
    private function liveCritical(array $pack): ?array
    {
        if ((string) $pack['entity_type'] === 'JOB_INSTALLATION') {
            $install = $this->fields->installation((int) $pack['entity_id']);
            if ($install === null) {
                return ['hash' => 'missing'];
            }
            $job = $this->fields->jobFacts((int) $install['job_id']);
            if ($job === null) {
                return ['hash' => 'missing'];
            }
            $art = $this->fields->approvedArtwork((int) $job['id']);
            $revision = $art === null ? 0 : (int) $art['revision_number'];

            return ['hash' => hash('sha256', json_encode($this->critical($job, $revision), JSON_THROW_ON_ERROR))];
        }

        return ['hash' => (string) $pack['critical_hash']];
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function critical(array $job, int $revision): array
    {
        return [
            'status' => $job['status'],
            'site_address' => (string) ($job['site_address'] ?? ''),
            'installation_date' => (string) ($job['installation_date'] ?? ''),
            'artwork_revision' => $revision,
            'installation_notes' => (string) ($job['installation_notes'] ?? ''),
        ];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @return array{ok: false, error_code: string, message: string, pack: null}
     */
    private function fail(string $code, string $message): array
    {
        return ['ok' => false, 'error_code' => $code, 'message' => $message, 'pack' => null];
    }
}
