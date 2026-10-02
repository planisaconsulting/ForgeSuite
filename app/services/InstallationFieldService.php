<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstallationFieldRepository;
use App\Repositories\LogisticsRepository;

/**
 * Installation is a fulfilment type. Signed off is not the same as the job being complete.
 * The pack omits margin, debt, and internal commercial notes.
 */
final class InstallationFieldService
{
    /** @var list<string> */
    public const STATUSES = [
        'PLANNED', 'CONFIRMED', 'TRAVELLING', 'ON_SITE', 'IN_PROGRESS', 'BLOCKED',
        'PARTIALLY_COMPLETE', 'COMPLETE_PENDING_SIGNOFF', 'SIGNED_OFF', 'SNAGGED', 'CANCELLED',
    ];

    /** @var list<string> */
    public const PHOTO = ['BEFORE', 'DURING', 'AFTER', 'DETAIL', 'PROBLEM', 'SIGNOFF'];

    /** @var list<string> */
    public const SNAG_TYPES = ['INSTALLATION', 'PRODUCT', 'ELECTRICAL', 'FINISH', 'SITE', 'CUSTOMER_REQUEST', 'OTHER'];

    /** @var list<string> */
    public const SEVERITY = ['MINOR', 'MAJOR', 'BLOCKING'];

    public function __construct(
        private readonly InstallationFieldRepository $repo = new InstallationFieldRepository(),
        private readonly LogisticsRepository $logistics = new LogisticsRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function schedule(array $input, int $userId): array
    {
        if (!can('installation.schedule') && !can('installations.schedule')) {
            return ['errors' => ['_form' => 'You cannot schedule an installation.'], 'id' => null];
        }
        $jobId = (int) ($input['job_id'] ?? 0);
        if ($this->repo->jobContext($jobId) === null) {
            return ['errors' => ['job_id' => 'Choose a job.'], 'id' => null];
        }
        $id = $this->repo->insert([
            'job_id' => $jobId,
            'project_id' => $this->positive($input['project_id'] ?? 0),
            'project_site_id' => $this->positive($input['project_site_id'] ?? 0),
            'fulfilment_requirement_id' => $this->positive($input['fulfilment_requirement_id'] ?? 0),
            'service_request_id' => $this->positive($input['service_request_id'] ?? 0),
            'contractor_work_order_id' => $this->positive($input['contractor_work_order_id'] ?? 0),
            'scheduled_date' => $this->date($input['scheduled_date'] ?? null),
            'site_address' => $this->blank($input['site_address'] ?? null),
            'site_contact_name' => $this->blank($input['site_contact_name'] ?? null),
            'site_contact_phone' => $this->blank($input['site_contact_phone'] ?? null),
            'installation_notes' => $this->blank($input['installation_notes'] ?? null),
            'created_by' => $userId,
        ]);
        $kind = strtoupper(trim((string) ($input['checklist'] ?? '')));
        if ($kind !== '') {
            foreach ($this->repo->template($kind) as $row) {
                $this->repo->copyCheck($id, (string) $row['label'], (int) $row['sort_order']);
            }
        }
        BusinessEventDispatcher::emit('INSTALLATION_CONFIRMED', 'INSTALLATION', $id, $userId, []);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function pack(int $installationId): array
    {
        $row = $this->repo->find($installationId);
        if ($row === null) {
            return [];
        }
        $job = $this->repo->jobContext((int) $row['job_id']);
        $customer = '';
        if ($job !== null) {
            $customer = trim((string) ($job['company_name'] ?? ''));
            if ($customer === '') {
                $customer = trim((string) $job['first_name'] . ' ' . (string) $job['last_name']);
            }
        }

        return [
            'customer' => $customer,
            'site_address' => (string) ($row['site_address'] ?? ''),
            'contact' => trim((string) ($row['site_contact_name'] ?? '') . ' ' . (string) ($row['site_contact_phone'] ?? '')),
            'job_number' => (string) ($job['job_number'] ?? ''),
            'artwork' => array_map(static fn (array $art): string => (string) $art['title'], $this->repo->artworkTitles((int) $row['job_id'])),
            'checklist' => array_map(static fn (array $check): string => (string) $check['label'], $this->repo->checks($installationId)),
            'safety_notes' => 'Follow the site access notes. This pack is not a safety certificate.',
            'access_notes' => (string) ($row['installation_notes'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function arrive(int $id, int $userId, ?string $latitude, ?string $longitude, ?string $deviceAt): array
    {
        if (!can('installation.execute') && !can('installations.complete')) {
            return ['_form' => 'You cannot update this installation.'];
        }
        $this->repo->setStatus($id, 'ON_SITE', [
            'arrived_at' => date('Y-m-d H:i:s'),
            'arrival_latitude' => $this->coord($latitude),
            'arrival_longitude' => $this->coord($longitude),
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function start(int $id, int $userId, ?string $deviceAt): array
    {
        if (!can('installation.execute') && !can('installations.complete')) {
            return ['_form' => 'You cannot update this installation.'];
        }
        $this->repo->setStatus($id, 'IN_PROGRESS', [
            'started_at' => date('Y-m-d H:i:s'),
            'device_started_at' => $this->when($deviceAt),
        ]);
        BusinessEventDispatcher::emit('INSTALLATION_STARTED', 'INSTALLATION', $id, $userId, []);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function tick(int $installationId, int $itemId, int $userId): array
    {
        $this->repo->checkItem($itemId, $userId);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function photo(int $installationId, string $category, string $path, int $userId): array
    {
        $category = strtoupper($category);
        if (!in_array($category, self::PHOTO, true)) {
            return ['errors' => ['category' => 'That photo category is not valid.'], 'id' => null];
        }
        $id = $this->repo->insertPhoto($this->uuid(), 'JOB_INSTALLATION', $installationId, $category, $path, $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function snag(int $installationId, array $input, int $userId): array
    {
        $row = $this->repo->find($installationId);
        if ($row === null) {
            return ['errors' => ['_form' => 'That installation was not found.'], 'id' => null];
        }
        $type = strtoupper((string) ($input['snag_type'] ?? 'OTHER'));
        $severity = strtoupper((string) ($input['severity'] ?? 'MINOR'));
        if (!in_array($type, self::SNAG_TYPES, true) || !in_array($severity, self::SEVERITY, true)) {
            return ['errors' => ['_form' => 'Check the snag type and severity.'], 'id' => null];
        }
        $id = $this->repo->insertSnag([
            'job_id' => (int) $row['job_id'],
            'installation_id' => $installationId,
            'project_site_id' => $row['project_site_id'],
            'description' => mb_substr(trim((string) ($input['description'] ?? 'Snag')), 0, 255),
            'snag_type' => $type,
            'severity' => $severity,
            'source_category' => $this->blank($input['source_category'] ?? null),
            'priority' => $severity === 'BLOCKING' ? 'HIGH' : 'NORMAL',
            'created_by' => $userId,
        ]);
        if ($severity === 'BLOCKING') {
            $this->repo->setStatus($installationId, 'SNAGGED');
        }
        BusinessEventDispatcher::emit('INSTALLATION_BLOCKED', 'INSTALLATION', $installationId, $userId, ['snag' => $id]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, job_status: string|null}
     */
    public function signOff(int $installationId, array $input, int $userId): array
    {
        if (!can('installation.signoff') && !can('installations.complete')) {
            return ['errors' => ['_form' => 'You cannot sign off an installation.'], 'id' => null, 'job_status' => null];
        }
        $row = $this->repo->find($installationId);
        if ($row === null) {
            return ['errors' => ['_form' => 'That installation was not found.'], 'id' => null, 'job_status' => null];
        }
        $name = trim((string) ($input['signer_name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['signer_name' => 'Enter the signer name.'], 'id' => null, 'job_status' => null];
        }
        if ($this->repo->openBlocking($installationId) > 0) {
            return [
                'errors' => ['_form' => 'A blocking snag is open. The job is not completed.'],
                'id' => null,
                'job_status' => $this->repo->jobStatus((int) $row['job_id']),
            ];
        }
        $id = $this->repo->insertSignoff([
            'installation_id' => $installationId,
            'job_item_id' => $this->positive($input['job_item_id'] ?? 0),
            'project_site_id' => $this->positive($input['project_site_id'] ?? $row['project_site_id'] ?? 0),
            'signer_name' => mb_substr($name, 0, 120),
            'signer_role' => $this->blank($input['signer_role'] ?? null),
            'statement_version' => (string) SettingsService::get('install_statement_version', 'INSTALL-1'),
            'signature_id' => $this->positive($input['signature_id'] ?? 0),
            'device_signed_at' => $this->when($input['device_signed_at'] ?? null),
            'signed_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ]);
        $partial = (int) ($input['job_item_id'] ?? 0) > 0;
        $this->repo->setStatus($installationId, $partial ? 'PARTIALLY_COMPLETE' : 'SIGNED_OFF', [
            'customer_signoff_name' => mb_substr($name, 0, 120),
            'signoff_role' => $this->blank($input['signer_role'] ?? null),
            'signoff_statement_version' => (string) SettingsService::get('install_statement_version', 'INSTALL-1'),
            'signoff_date' => date('Y-m-d'),
        ]);
        $fulfilmentId = (int) ($row['fulfilment_requirement_id'] ?? 0);
        if ($fulfilmentId > 0 && !$partial) {
            $line = $this->logistics->fulfilment($fulfilmentId);
            if ($line !== null) {
                $this->logistics->addInstalled($fulfilmentId, (string) $line['quantity']);
            }
        }
        BusinessEventDispatcher::emit('INSTALLATION_SIGNED_OFF', 'INSTALLATION', $installationId, $userId, []);

        return ['errors' => [], 'id' => $id, 'job_status' => $this->repo->jobStatus((int) $row['job_id'])];
    }

    public function certificateAllowed(int $installationId): bool
    {
        $row = $this->repo->find($installationId);
        if ($row === null || (string) $row['status'] !== 'SIGNED_OFF') {
            return false;
        }

        return $this->repo->openBlocking($installationId) === 0 && $this->repo->signoffCount($installationId) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function positive(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function date(mixed $value): ?string
    {
        $text = trim((string) $value);
        $parsed = $text === '' ? false : strtotime($text);

        return $parsed === false ? null : date('Y-m-d', $parsed);
    }

    private function when(mixed $value): ?string
    {
        $text = trim((string) $value);
        $parsed = $text === '' ? false : strtotime($text);

        return $parsed === false ? null : date('Y-m-d H:i:s', $parsed);
    }

    private function coord(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' || !is_numeric($text) ? null : $text;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
