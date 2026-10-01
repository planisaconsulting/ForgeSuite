<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\JobRepository;
use App\Repositories\ProjectRepository;

/**
 * Project sites, CSV import, and draft rollout jobs.
 * A draft job is a real job in NEW status. It does not release production,
 * consume stock, or raise an invoice.
 */
final class ProjectSiteService
{
    public function __construct(
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly JobRepository $jobs = new JobRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function add(int $projectId, array $input, int $userId): array
    {
        $project = $this->projects->find($projectId);
        if ($project === null) {
            return ['errors' => ['_form' => 'That project was not found.'], 'id' => null];
        }
        $code = strtoupper(trim((string) ($input['site_code'] ?? '')));
        $name = trim((string) ($input['site_name'] ?? ''));
        $errors = [];
        if ($code === '') {
            $errors['site_code'] = 'Site code is required.';
        }
        if ($name === '') {
            $errors['site_name'] = 'Site name is required.';
        }
        if ($code !== '' && $this->projects->siteCodeExists($projectId, $code)) {
            $errors['site_code'] = 'That site code is already on this project.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $contactId = null;
        $contactName = trim((string) ($input['contact_name'] ?? ''));
        if ($contactName !== '') {
            $contactId = $this->projects->insertContact(
                (int) $project['customer_id'],
                $contactName,
                $this->blank($input['contact_email'] ?? null),
                $this->blank($input['contact_phone'] ?? null)
            );
        }
        $target = $this->date($input['target_date'] ?? null);
        $id = $this->projects->insertSite($this->siteRow($projectId, [
            'site_code' => $code,
            'site_name' => $name,
            'customer_site_reference' => $this->blank($input['customer_site_reference'] ?? null),
            'address_line_1' => $this->blank($input['address_line_1'] ?? null),
            'address_line_2' => $this->blank($input['address_line_2'] ?? null),
            'city' => $this->blank($input['city'] ?? null),
            'province' => $this->blank($input['province'] ?? null),
            'postal_code' => $this->blank($input['postal_code'] ?? null),
            'latitude' => $this->blank($input['latitude'] ?? null),
            'longitude' => $this->blank($input['longitude'] ?? null),
            'group_kind' => $this->blank($input['group_kind'] ?? null),
            'group_label' => $this->blank($input['group_label'] ?? null),
            'wave_id' => (int) ($input['wave_id'] ?? 0),
            'survey_required' => empty($input['survey_required']) ? 0 : 1,
            'notes' => $this->blank($input['notes'] ?? null),
            'target_date' => $target,
            'primary_contact_id' => $contactId,
        ], $this->projects->maxSiteSequence($projectId) + 1));
        if ($contactId !== null) {
            $this->projects->assignContact($projectId, $id, $contactId, 'SITE_CONTACT');
        }
        $this->audit->record('project', $projectId, 'SITE_ADDED', null, ['site_id' => $id, 'site_code' => $code], $userId);
        BusinessEventDispatcher::emit('PROJECT_SITE_ADDED', 'PROJECT', $projectId, $userId, ['site_id' => $id]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * Preview or commit a site list. Invalid rows stop the whole import.
     *
     * @param list<array<string, string>> $rows
     * @return array{imported: int, skipped: int, failed: int, warnings: list<string>, errors: list<string>, committed: bool}
     */
    public function import(int $projectId, array $rows, bool $confirm, int $userId): array
    {
        $project = $this->projects->find($projectId);
        $report = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'warnings' => [], 'errors' => [], 'committed' => false];
        if ($project === null) {
            $report['errors'][] = 'That project was not found.';
            $report['failed'] = count($rows);

            return $report;
        }
        $seen = [];
        $prepared = [];
        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $code = strtoupper(trim((string) ($row['site_code'] ?? '')));
            $name = trim((string) ($row['site_name'] ?? ''));
            if ($code === '' || $name === '') {
                $report['failed']++;
                $report['errors'][] = 'Row ' . $line . ' needs a site code and a site name.';
                continue;
            }
            if (isset($seen[$code]) || $this->projects->siteCodeExists($projectId, $code)) {
                $report['skipped']++;
                $report['errors'][] = 'Row ' . $line . ' repeats site code ' . $code . '.';
                continue;
            }
            $reference = trim((string) ($row['customer_site_reference'] ?? ''));
            if ($reference !== '' && $this->projects->referenceExists($projectId, $reference)) {
                $report['skipped']++;
                $report['errors'][] = 'Row ' . $line . ' uses branch reference ' . $reference . ' which is already on the project.';
                continue;
            }
            $seen[$code] = true;
            $address = trim((string) ($row['address_line_1'] ?? ''));
            if ($address !== '') {
                foreach ($this->projects->sites($projectId) as $existing) {
                    if (strcasecmp((string) $existing['address_line_1'], $address) === 0) {
                        $report['warnings'][] = 'Row ' . $line . ' has the same street as ' . $existing['site_code'] . '. It was not merged.';
                    }
                }
            }
            $prepared[] = $row + ['site_code' => $code, 'site_name' => $name];
        }
        if ($report['failed'] > 0 || $report['skipped'] > 0) {
            $report['imported'] = 0;

            return $report;
        }
        if (!$confirm) {
            $report['imported'] = count($prepared);
            $report['warnings'][] = 'Preview only. Nothing was saved.';

            return $report;
        }
        $sequence = $this->projects->maxSiteSequence($projectId);
        Database::transaction(function () use ($project, $prepared, $projectId, &$sequence, $userId, &$report): void {
            foreach ($prepared as $row) {
                $sequence++;
                $contactId = null;
                $contactName = trim((string) ($row['contact_name'] ?? ''));
                if ($contactName !== '') {
                    $contactId = $this->projects->insertContact(
                        (int) $project['customer_id'],
                        $contactName,
                        $this->blank($row['contact_email'] ?? null),
                        $this->blank($row['contact_phone'] ?? null)
                    );
                }
                $siteId = $this->projects->insertSite($this->siteRow($projectId, [
                    'site_code' => (string) $row['site_code'],
                    'site_name' => (string) $row['site_name'],
                    'customer_site_reference' => $this->blank($row['customer_site_reference'] ?? null),
                    'address_line_1' => $this->blank($row['address_line_1'] ?? null),
                    'address_line_2' => $this->blank($row['address_line_2'] ?? null),
                    'city' => $this->blank($row['city'] ?? null),
                    'province' => $this->blank($row['province'] ?? null),
                    'postal_code' => $this->blank($row['postal_code'] ?? null),
                    'latitude' => $this->blank($row['latitude'] ?? null),
                    'longitude' => $this->blank($row['longitude'] ?? null),
                    'group_kind' => $this->blank($row['group_kind'] ?? null),
                    'group_label' => $this->blank($row['group_label'] ?? null),
                    'wave_id' => (int) ($row['wave_id'] ?? 0),
                    'survey_required' => empty($row['survey_required']) ? 0 : 1,
                    'notes' => $this->blank($row['notes'] ?? null),
                    'target_date' => $this->date($row['target_date'] ?? null),
                    'primary_contact_id' => $contactId,
                ], $sequence));
                if ($contactId !== null) {
                    $this->projects->assignContact($projectId, $siteId, $contactId, 'SITE_CONTACT');
                }
                $report['imported']++;
            }
        });
        $report['committed'] = true;
        $this->audit->record('project', $projectId, 'SITE_IMPORT', null, ['imported' => $report['imported']], $userId);

        return $report;
    }

    /**
     * @param list<int> $siteIds
     * @return array{errors: array<string, string>, job_ids: list<int>}
     */
    public function createDraftJobs(int $projectId, array $siteIds, string $title, bool $confirm, int $userId): array
    {
        if (!$confirm) {
            return ['errors' => ['_form' => 'Confirm before creating draft jobs.'], 'job_ids' => []];
        }
        $project = $this->projects->find($projectId);
        if ($project === null) {
            return ['errors' => ['_form' => 'That project was not found.'], 'job_ids' => []];
        }
        $title = trim($title);
        if ($title === '') {
            return ['errors' => ['title' => 'A package name is required.'], 'job_ids' => []];
        }
        $ids = [];
        Database::transaction(function () use ($project, $projectId, $siteIds, $title, $userId, &$ids): void {
            foreach ($siteIds as $siteId) {
                $site = $this->projects->site((int) $siteId);
                if ($site === null || (int) $site['project_id'] !== $projectId) {
                    continue;
                }
                $quoteId = $this->projects->insertDraftQuote(
                    $this->numbers->quote(),
                    (int) $project['customer_id'],
                    $projectId,
                    (int) $site['id'],
                    'Rollout draft for ' . $site['site_code'] . '. Commercial value stays on the project. Do not convert this quote again.',
                    $userId
                );
                $address = trim((string) $site['address_line_1'] . ' ' . (string) $site['city']);
                $jobId = $this->jobs->insert([
                    'job_number' => $this->numbers->job(),
                    'customer_id' => (int) $project['customer_id'],
                    'quote_id' => $quoteId,
                    'quote_revision_number' => 1,
                    'title' => mb_substr($title . ' — ' . $site['site_code'], 0, 180),
                    'priority' => 'NORMAL',
                    'assigned_to' => null,
                    'target_date' => $site['current_target_date'],
                    'created_by' => $userId,
                ]);
                $this->projects->attachJob($jobId, $projectId, (int) $site['id'], 'INCLUDED_IN_CONTRACT', 'DRAFT', $address, '0.00');
                $this->jobs->insertHistory($jobId, null, 'NEW', $userId, 'Draft rollout job. Not released to production.');
                $ids[] = $jobId;
            }
        });
        $this->audit->record('project', $projectId, 'SITE_BULK_JOBS', null, ['jobs' => count($ids)], $userId);

        return ['errors' => [], 'job_ids' => $ids];
    }

    /**
     * Site completion is not inferred from a partial set of jobs.
     */
    public function refreshFromJobs(int $siteId): string
    {
        $site = $this->projects->site($siteId);
        if ($site === null) {
            return '';
        }
        $jobs = $this->projects->jobs((int) $site['project_id'], $siteId);
        if ($jobs === []) {
            return (string) $site['status'];
        }
        $allComplete = true;
        $anyProduction = false;
        foreach ($jobs as $job) {
            if (!in_array((string) $job['status'], ['COMPLETED', 'CANCELLED'], true)) {
                $allComplete = false;
            }
            if (in_array((string) $job['status'], ['IN_PRODUCTION', 'QUALITY_CONTROL', 'READY_FOR_PRODUCTION'], true)) {
                $anyProduction = true;
            }
        }
        if ($allComplete) {
            return (string) $site['status'];
        }
        if ($anyProduction && (string) $site['status'] === 'NOT_STARTED') {
            $this->projects->setSiteStatus($siteId, 'IN_PRODUCTION');

            return 'IN_PRODUCTION';
        }

        return (string) $site['status'];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldPack(int $siteId): array
    {
        $site = $this->projects->site($siteId);
        if ($site === null) {
            return [];
        }
        $projectId = (int) $site['project_id'];

        return [
            'project' => [
                'id' => $projectId,
                'number' => (string) $site['project_number'],
                'name' => (string) $site['project_name'],
            ],
            'site' => $site,
            'jobs' => $this->projects->jobs($projectId, $siteId),
            'contacts' => array_values(array_filter(
                $this->projects->contacts($projectId),
                static fn (array $contact): bool => $contact['project_site_id'] === null || (int) $contact['project_site_id'] === $siteId
            )),
            'documents' => $this->projects->documents($projectId, true),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function siteRow(int $projectId, array $input, int $sequence): array
    {
        return [
            'project_id' => $projectId,
            'wave_id' => ($input['wave_id'] ?? 0) > 0 ? (int) $input['wave_id'] : null,
            'site_code' => (string) $input['site_code'],
            'site_name' => mb_substr((string) $input['site_name'], 0, 180),
            'customer_site_reference' => $input['customer_site_reference'],
            'address_line_1' => $input['address_line_1'],
            'address_line_2' => $input['address_line_2'],
            'city' => $input['city'],
            'province' => $input['province'],
            'postal_code' => $input['postal_code'],
            'latitude' => $input['latitude'],
            'longitude' => $input['longitude'],
            'primary_contact_id' => $input['primary_contact_id'],
            'group_kind' => $input['group_kind'],
            'group_label' => $input['group_label'],
            'status' => 'NOT_STARTED',
            'sequence' => $sequence,
            'survey_required' => (int) $input['survey_required'],
            'original_target_date' => $input['target_date'],
            'current_target_date' => $input['target_date'],
            'notes' => $input['notes'],
        ];
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
