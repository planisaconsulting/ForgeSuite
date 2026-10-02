<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ArtworkProofingRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductionControlRepository;

/**
 * Release checks are facts with a policy severity.
 * A block stays a block. An override is a separate record.
 */
final class ReleaseReadinessService
{
    /** @var list<string> */
    public const CODES = [
        'JOB_VALID', 'CUSTOMER_CONFIRMED', 'SCOPE_CONFIRMED', 'QUANTITIES_CONFIRMED', 'DIMENSIONS_CONFIRMED',
        'ARTWORK_APPROVED', 'ARTWORK_REVISION', 'SPECIFICATION_CONFIRMED', 'TECHNICAL_REVIEW',
        'BOM_GENERATED', 'MATERIAL_REQUIREMENTS', 'MATERIAL_AVAILABILITY', 'SHORTAGES_IDENTIFIED',
        'PRODUCTION_ROUTE', 'PRODUCTION_FILES', 'QC_CHECKLIST', 'TARGET_DATE', 'FULFILMENT_METHOD',
        'LOCATION_CONFIRMED', 'SPECIAL_INSTRUCTIONS',
    ];

    public function __construct(
        private readonly ProductionControlRepository $repo = new ProductionControlRepository(),
        private readonly InventoryRepository $inventory = new InventoryRepository(),
        private readonly MaterialReadinessService $materials = new MaterialReadinessService()
    ) {
    }

    /**
     * @return array{found: bool, blocked: bool, policy: string, checks: list<array<string, string>>, materials: list<array<string, mixed>>}
     */
    public function evaluate(int $jobId): array
    {
        $job = $this->repo->job($jobId);
        if ($job === null) {
            return ['found' => false, 'blocked' => true, 'policy' => '', 'checks' => [], 'materials' => []];
        }
        $snapshot = json_decode((string) ($job['technical_snapshot_json'] ?? ''), true);
        $specCode = is_array($snapshot) ? (string) ($snapshot['specification_code'] ?? '') : '';
        $policy = $this->repo->policyFor((string) $job['job_type'], $specCode !== '' ? $specCode : null);
        $severities = $policy === null ? [] : $this->repo->severities((int) $policy['id']);
        $artwork = $this->repo->artworkFacts($jobId);
        $items = $this->repo->items($jobId);
        $requirements = $this->repo->requirements($jobId);
        $stages = $this->repo->stages($jobId);
        $fulfilments = $this->repo->fulfilments($jobId);
        $materialRows = $this->materials->forJob($jobId);
        $issues = $this->issues($job, $items, $artwork, $requirements, $stages, $fulfilments, $snapshot, $materialRows);
        $checks = [];
        $blocked = false;
        foreach (self::CODES as $code) {
            $severity = $severities[$code] ?? 'INFORMATIONAL';
            $state = $issues[$code] ?? ['state' => 'ok', 'message' => 'Passed.'];
            $result = $this->result($state['state'], $severity);
            if ($result === 'BLOCK') {
                $blocked = true;
            }
            $checks[] = [
                'check_code' => $code,
                'result' => $result,
                'severity' => $severity,
                'message' => $state['message'],
            ];
        }
        foreach ($this->artworkExtras($jobId) as $extra) {
            if ($extra['result'] === 'BLOCK') {
                $blocked = true;
            }
            $checks[] = $extra;
        }

        return [
            'found' => true,
            'blocked' => $blocked,
            'policy' => (string) ($policy['code'] ?? 'DEFAULT'),
            'checks' => $checks,
            'materials' => $materialRows,
        ];
    }

    /**
     * @param array{state: string, message: string} $state
     */
    private function result(string $state, string $severity): string
    {
        if ($state === 'na') {
            return 'NOT_APPLICABLE';
        }
        if ($state === 'ok') {
            return 'PASS';
        }
        if ($severity === 'WARNING' || $severity === 'INFORMATIONAL') {
            return 'WARNING';
        }

        return 'BLOCK';
    }

    /**
     * @param array<string, mixed> $job
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $artwork
     * @param list<array<string, mixed>> $requirements
     * @param list<array<string, mixed>> $stages
     * @param list<array<string, mixed>> $fulfilments
     * @param array<string, mixed>|null $snapshot
     * @param list<array<string, mixed>> $materialRows
     * @return array<string, array{state: string, message: string}>
     */
    private function issues(array $job, array $items, array $artwork, array $requirements, array $stages, array $fulfilments, ?array $snapshot, array $materialRows): array
    {
        $out = [];
        $out['JOB_VALID'] = in_array((string) $job['status'], ['CANCELLED'], true)
            ? ['state' => 'issue', 'message' => 'The job is cancelled.']
            : ['state' => 'ok', 'message' => 'The job is open.'];
        $out['CUSTOMER_CONFIRMED'] = (int) $job['customer_id'] > 0
            ? ['state' => 'ok', 'message' => 'Customer is on the job.']
            : ['state' => 'issue', 'message' => 'The job has no customer.'];
        $out['SCOPE_CONFIRMED'] = $items === []
            ? ['state' => 'issue', 'message' => 'The job has no items.']
            : ['state' => 'ok', 'message' => count($items) . ' item(s) on the job.'];
        $badQty = false;
        $missingSize = false;
        foreach ($items as $item) {
            if (Decimal::cmp((string) $item['quantity'], '0') <= 0) {
                $badQty = true;
            }
            if ($item['width_mm'] === null && $item['height_mm'] === null) {
                $missingSize = true;
            }
        }
        $out['QUANTITIES_CONFIRMED'] = $badQty
            ? ['state' => 'issue', 'message' => 'A quantity is zero.']
            : ['state' => 'ok', 'message' => 'Quantities are greater than zero.'];
        $out['DIMENSIONS_CONFIRMED'] = $missingSize
            ? ['state' => 'issue', 'message' => 'A line has no width or height.']
            : ['state' => 'ok', 'message' => 'Dimensions are present.'];
        $requiredArt = (int) $artwork['required_items'];
        if ($requiredArt === 0) {
            $out['ARTWORK_APPROVED'] = ['state' => 'na', 'message' => 'Artwork is not required.'];
            $out['ARTWORK_REVISION'] = ['state' => 'na', 'message' => 'Artwork is not required.'];
        } else {
            $out['ARTWORK_APPROVED'] = (int) $artwork['approved'] < 1
                ? ['state' => 'issue', 'message' => 'Artwork approval is missing.']
                : ['state' => 'ok', 'message' => 'Artwork is approved.'];
            $out['ARTWORK_REVISION'] = $artwork['revision'] === null
                ? ['state' => 'issue', 'message' => 'No artwork revision is identified.']
                : ['state' => 'ok', 'message' => 'Artwork revision R' . (int) $artwork['revision'] . '.'];
        }
        $hasSpec = is_array($snapshot) && ($snapshot['specification_version'] ?? null) !== null;
        $out['SPECIFICATION_CONFIRMED'] = $hasSpec
            ? ['state' => 'ok', 'message' => (string) ($snapshot['specification_code'] ?? 'Specification') . ' v' . (int) $snapshot['specification_version'] . '.']
            : ['state' => 'na', 'message' => 'No specification is linked.'];
        $technical = $this->repo->technicalOpen((int) $job['id']);
        $engineering = is_array($snapshot) && !empty($snapshot['engineering_review']);
        $out['TECHNICAL_REVIEW'] = ($technical > 0 || $engineering)
            ? ['state' => 'issue', 'message' => 'Technical review is outstanding.']
            : ['state' => 'ok', 'message' => 'No outstanding technical review.'];
        $out['BOM_GENERATED'] = $requirements === []
            ? ['state' => 'issue', 'message' => 'No bill of materials is on the job.']
            : ['state' => 'ok', 'message' => count($requirements) . ' material line(s).'];
        $out['MATERIAL_REQUIREMENTS'] = $out['BOM_GENERATED'];
        $short = 0;
        foreach ($materialRows as $row) {
            if (Decimal::cmp((string) ($row['shortage'] ?? '0'), '0') > 0) {
                $short++;
            }
        }
        $out['MATERIAL_AVAILABILITY'] = $short > 0
            ? ['state' => 'issue', 'message' => $short . ' material line(s) are short. Incoming stock is not counted as available.']
            : ['state' => 'ok', 'message' => 'Required material is available or not yet required.'];
        $out['SHORTAGES_IDENTIFIED'] = ['state' => 'ok', 'message' => $short === 0 ? 'No shortage.' : $short . ' shortage(s) listed.'];
        $out['PRODUCTION_ROUTE'] = $stages === []
            ? ['state' => 'issue', 'message' => 'No production route is on the job.']
            : ['state' => 'ok', 'message' => count($stages) . ' stage(s).'];
        $fileIssue = $this->fileIssue((int) $job['id'], $stages);
        $out['PRODUCTION_FILES'] = $fileIssue;
        $out['QC_CHECKLIST'] = $this->repo->qcChecklistCount((int) $job['id']) > 0
            ? ['state' => 'ok', 'message' => 'A QC checklist is available.']
            : ['state' => 'issue', 'message' => 'No QC checklist is attached to the products.'];
        $out['TARGET_DATE'] = ($job['target_date'] ?? null) === null || (string) $job['target_date'] === ''
            ? ['state' => 'issue', 'message' => 'No internal target date.']
            : ['state' => 'ok', 'message' => 'Target date ' . (string) $job['target_date'] . '.'];
        $out['FULFILMENT_METHOD'] = $fulfilments !== [] || (string) ($job['delivery_method'] ?? '') !== ''
            ? ['state' => 'ok', 'message' => $fulfilments === [] ? 'Job delivery method ' . (string) $job['delivery_method'] . '.' : count($fulfilments) . ' fulfilment line(s).']
            : ['state' => 'issue', 'message' => 'No fulfilment method.'];
        $located = trim((string) ($job['site_address'] ?? '')) !== '';
        foreach ($fulfilments as $row) {
            if (trim((string) ($row['address'] ?? '')) !== '') {
                $located = true;
            }
        }
        $out['LOCATION_CONFIRMED'] = $located
            ? ['state' => 'ok', 'message' => 'A destination is recorded.']
            : ['state' => 'issue', 'message' => 'No delivery or installation location.'];
        $out['SPECIAL_INSTRUCTIONS'] = ['state' => 'ok', 'message' => trim((string) ($job['production_notes'] ?? '')) === '' ? 'No special instruction.' : 'Production notes are on the job.'];

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $stages
     * @return array{state: string, message: string}
     */
    private function fileIssue(int $jobId, array $stages): array
    {
        $needed = [];
        foreach ($stages as $stage) {
            $name = strtoupper((string) $stage['stage_name']);
            if (str_contains($name, 'PRINT')) {
                $needed['PRINT'] = true;
            }
            if (str_contains($name, 'CNC') || str_contains($name, 'ROUTER')) {
                $needed['CNC'] = true;
            }
        }
        if ($needed === []) {
            return ['state' => 'na', 'message' => 'No stage requires a production file.'];
        }
        foreach (array_keys($needed) as $category) {
            if ($this->repo->approvedFileCount($jobId, $category) < 1) {
                return ['state' => 'issue', 'message' => 'Missing an approved ' . $category . ' file. Customer artwork approval is not the print file.'];
            }
        }

        return ['state' => 'ok', 'message' => 'Required production files are approved for production.'];
    }

    /**
     * Phase 7 gates apply only when the artwork asks for them.
     * A job with no production-file requirement and no physical sample is unchanged.
     *
     * @return list<array{check_code: string, result: string, severity: string, message: string}>
     */
    private function artworkExtras(int $jobId): array
    {
        $facts = (new ArtworkProofingRepository())->releaseFacts($jobId);
        $physical = $facts['physical_pending']
            ? ['check_code' => 'PHYSICAL_SAMPLE', 'result' => 'BLOCK', 'severity' => 'BLOCKING', 'message' => 'A physical sample is still required.']
            : ['check_code' => 'PHYSICAL_SAMPLE', 'result' => 'NOT_APPLICABLE', 'severity' => 'INFORMATIONAL', 'message' => 'No physical sample is waiting.'];
        $file = $facts['file_missing']
            ? ['check_code' => 'PRODUCTION_FILE_APPROVED', 'result' => 'BLOCK', 'severity' => 'BLOCKING', 'message' => 'Artwork is approved. The production file is not.']
            : ['check_code' => 'PRODUCTION_FILE_APPROVED', 'result' => 'NOT_APPLICABLE', 'severity' => 'INFORMATIONAL', 'message' => 'No extra production-file gate.'];
        $stale = $facts['stale']
            ? ['check_code' => 'PRODUCTION_FILE_CURRENT', 'result' => 'BLOCK', 'severity' => 'BLOCKING', 'message' => 'The released production file was superseded.']
            : ['check_code' => 'PRODUCTION_FILE_CURRENT', 'result' => 'NOT_APPLICABLE', 'severity' => 'INFORMATIONAL', 'message' => 'No superseded production file is on the release.'];

        return [$physical, $file, $stale];
    }
}
