<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ProductionControlRepository;

/**
 * Authorises production. It does not start it and it does not consume stock.
 * Accepted quote and released production stay separate.
 */
final class ProductionReleaseService
{
    public function __construct(
        private readonly ProductionControlRepository $repo = new ProductionControlRepository(),
        private readonly ReleaseReadinessService $readiness = new ReleaseReadinessService(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly StockMovementService $stock = new StockMovementService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, release: array<string, mixed>|null, checks: list<array<string, string>>}
     */
    public function release(int $jobId, int $userId, array $input = []): array
    {
        if (!can('production.release.approve')) {
            return ['errors' => ['_form' => 'You cannot release a job to production.'], 'id' => null, 'release' => null, 'checks' => []];
        }
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        try {
            return Database::transaction(function () use ($jobId, $userId, $input, $key): array {
                $job = $this->repo->lockJob($jobId);
                if ($job === null) {
                    return ['errors' => ['_form' => 'That job was not found.'], 'id' => null, 'release' => null, 'checks' => []];
                }
                if ($key !== '') {
                    $existing = $this->repo->releaseByKey($key);
                    if ($existing !== null) {
                        return ['errors' => [], 'id' => (int) $existing['id'], 'release' => $existing, 'checks' => $this->repo->checks((int) $existing['id'])];
                    }
                }
                if (empty($input['new_version'])) {
                    $current = $this->repo->currentRelease($jobId);
                    if ($current !== null) {
                        return ['errors' => [], 'id' => (int) $current['id'], 'release' => $current, 'checks' => $this->repo->checks((int) $current['id'])];
                    }
                }
                $previous = $this->repo->currentRelease($jobId);
                $evaluated = $this->readiness->evaluate($jobId);
                $overrides = $this->acceptedOverrides($evaluated['checks'], (array) ($input['overrides'] ?? []));
                if ($overrides['errors'] !== []) {
                    return ['errors' => $overrides['errors'], 'id' => null, 'release' => null, 'checks' => $evaluated['checks']];
                }
                $stillBlocked = $this->stillBlocked($evaluated['checks'], $overrides['codes']);
                $version = $this->repo->nextVersion($jobId);
                $status = $stillBlocked ? 'BLOCKED' : 'RELEASED';
                $now = date('Y-m-d H:i:s');
                $id = $this->repo->insertRelease([
                    'release_number' => $this->numbers->productionRelease(),
                    'job_id' => $jobId,
                    'release_version' => $version,
                    'status' => $status,
                    'requested_by' => $userId,
                    'requested_at' => $now,
                    'released_by' => $status === 'RELEASED' ? $userId : null,
                    'released_at' => $status === 'RELEASED' ? $now : null,
                    'release_notes' => blank_to_null($input['notes'] ?? null),
                    'override_reason' => $overrides['reason'],
                    'snapshot_json' => json_encode($this->snapshot($job, $evaluated), JSON_THROW_ON_ERROR),
                    'idempotency_key' => $key !== '' ? $key : null,
                ]);
                foreach ($evaluated['checks'] as $check) {
                    $this->repo->insertCheck([
                        'release_id' => $id,
                        'check_code' => $check['check_code'],
                        'result' => $check['result'],
                        'severity' => $check['severity'],
                        'message' => mb_substr($check['message'], 0, 255),
                    ]);
                }
                foreach ($overrides['rows'] as $row) {
                    $this->repo->insertOverride([
                        'release_id' => $id,
                        'check_code' => $row['check_code'],
                        'original_result' => 'BLOCK',
                        'reason' => $row['reason'],
                        'created_by' => $userId,
                    ]);
                }
                if ($stillBlocked) {
                    $this->repo->preparation($jobId, 'RELEASE_BLOCKED');
                    $this->audit->record('production_release', $id, 'PRODUCTION_RELEASE_BLOCKED', null, ['job_id' => $jobId], $userId);
                    BusinessEventDispatcher::emit('PRODUCTION_RELEASE_BLOCKED', 'PRODUCTION_RELEASE', $id, $userId, ['job_id' => $jobId]);

                    return ['errors' => ['_form' => $this->blockMessage($evaluated['checks'], $overrides['codes'])], 'id' => $id, 'release' => $this->repo->release($id), 'checks' => $evaluated['checks']];
                }
                $lines = $this->lines($jobId, (array) ($input['items'] ?? []));
                if ($lines['errors'] !== []) {
                    throw new \RuntimeException($lines['errors']['_form']);
                }
                foreach ($lines['items'] as $line) {
                    $this->repo->insertReleaseItem([
                        'release_id' => $id,
                        'job_item_id' => $line['id'],
                        'quantity' => $line['quantity'],
                        'status' => 'RELEASED',
                    ]);
                    $total = Decimal::add($this->repo->releasedQuantity((int) $line['id']), '0', 4);
                    $item = $this->repo->item((int) $line['id']);
                    $status = Decimal::cmp($total, (string) $item['quantity']) >= 0 ? 'RELEASED' : 'PARTIALLY_RELEASED';
                    $this->repo->updateItemRelease((int) $line['id'], $status, $total);
                }
                foreach ([$previous, $this->repo->reviewRelease($jobId)] as $older) {
                    if ($older !== null && (int) $older['id'] !== $id) {
                        $this->repo->setReleaseStatus((int) $older['id'], 'SUPERSEDED', $id);
                        $this->repo->supersedePacks((int) $older['id']);
                        BusinessEventDispatcher::emit('PRODUCTION_RELEASE_SUPERSEDED', 'PRODUCTION_RELEASE', (int) $older['id'], $userId, ['replacement' => $id]);
                    }
                }
                $this->repo->preparation($jobId, 'RELEASED');
                $this->repo->markPack($id, 0);
                $this->reserve((array) ($input['reserve'] ?? []), $jobId, $id, $userId);
                $this->audit->record('production_release', $id, 'PRODUCTION_RELEASED', null, ['version' => $version], $userId);
                BusinessEventDispatcher::emit('PRODUCTION_RELEASED', 'PRODUCTION_RELEASE', $id, $userId, ['version' => $version]);

                return ['errors' => [], 'id' => $id, 'release' => $this->repo->release($id), 'checks' => $evaluated['checks']];
            });
        } catch (\RuntimeException $e) {
            return ['errors' => ['_form' => $e->getMessage()], 'id' => null, 'release' => null, 'checks' => []];
        }
    }

    /**
     * @param list<int> $jobIds
     * @return array{ready: list<int>, warnings: list<int>, blocked: list<int>, released: list<int>}
     */
    public function bulk(array $jobIds, int $userId, bool $includeWarnings = false): array
    {
        $ready = [];
        $warnings = [];
        $blocked = [];
        $released = [];
        foreach ($jobIds as $jobId) {
            $evaluated = $this->readiness->evaluate((int) $jobId);
            $hasBlock = false;
            $hasWarn = false;
            foreach ($evaluated['checks'] as $check) {
                if ($check['result'] === 'BLOCK') {
                    $hasBlock = true;
                }
                if ($check['result'] === 'WARNING') {
                    $hasWarn = true;
                }
            }
            if ($hasBlock || !$evaluated['found']) {
                $blocked[] = (int) $jobId;
                continue;
            }
            if ($hasWarn) {
                $warnings[] = (int) $jobId;
                if (!$includeWarnings) {
                    continue;
                }
            } else {
                $ready[] = (int) $jobId;
            }
            $result = $this->release((int) $jobId, $userId, []);
            if ($result['errors'] === [] && $result['release'] !== null && (string) $result['release']['status'] === 'RELEASED') {
                $released[] = (int) $jobId;
            }
        }

        return ['ready' => $ready, 'warnings' => $warnings, 'blocked' => $blocked, 'released' => $released];
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(string $number): array
    {
        $release = $this->repo->releaseByNumber($number);
        if ($release === null) {
            return ['found' => false];
        }
        $current = $this->repo->currentRelease((int) $release['job_id']);
        $superseded = (string) $release['status'] === 'SUPERSEDED';

        return [
            'found' => true,
            'release' => $release,
            'superseded' => $superseded,
            'warning' => $superseded ? 'SUPERSEDED RELEASE — DO NOT PRODUCE.' : null,
            'current' => $current,
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $evaluated
     * @return array<string, mixed>
     */
    private function snapshot(array $job, array $evaluated): array
    {
        $technical = json_decode((string) ($job['technical_snapshot_json'] ?? ''), true);

        return [
            'job_number' => (string) $job['job_number'],
            'job_status' => (string) $job['status'],
            'preparation_status' => (string) $job['preparation_status'],
            'target_date' => $job['target_date'],
            'customer_promised_date' => $job['customer_promised_date'],
            'delivery_method' => (string) $job['delivery_method'],
            'items' => $this->repo->items((int) $job['id']),
            'requirements' => $this->repo->requirements((int) $job['id']),
            'stages' => $this->repo->stages((int) $job['id']),
            'files' => $this->repo->files((int) $job['id']),
            'fulfilment' => $this->repo->fulfilments((int) $job['id']),
            'artwork' => $this->repo->artworkFacts((int) $job['id']),
            'technical' => is_array($technical) ? $technical : null,
            'materials' => $evaluated['materials'],
            'checks' => $evaluated['checks'],
            'generated_at' => date('c'),
        ];
    }

    /**
     * @param list<array<string, string>> $checks
     * @param list<array<string, mixed>> $requested
     * @return array{errors: array<string, string>, codes: list<string>, rows: list<array{check_code: string, reason: string}>, reason: string|null}
     */
    private function acceptedOverrides(array $checks, array $requested): array
    {
        $byCode = [];
        foreach ($checks as $check) {
            $byCode[$check['check_code']] = $check;
        }
        $codes = [];
        $rows = [];
        $reason = null;
        foreach ($requested as $row) {
            $code = strtoupper((string) ($row['check'] ?? ''));
            $why = trim((string) ($row['reason'] ?? ''));
            $check = $byCode[$code] ?? null;
            if ($check === null || $check['result'] !== 'BLOCK') {
                continue;
            }
            if ($check['severity'] === 'BLOCK_NO_OVERRIDE') {
                return ['errors' => ['_form' => $check['message'] . ' This check cannot be overridden.'], 'codes' => [], 'rows' => [], 'reason' => null];
            }
            if ($why === '' || !can('production.release.override')) {
                return ['errors' => ['_form' => 'An override needs permission and a reason. The check stays blocked.'], 'codes' => [], 'rows' => [], 'reason' => null];
            }
            $codes[] = $code;
            $rows[] = ['check_code' => $code, 'reason' => $why];
            $reason = $why;
        }

        return ['errors' => [], 'codes' => $codes, 'rows' => $rows, 'reason' => $reason];
    }

    /**
     * @param list<array<string, string>> $checks
     * @param list<string> $overridden
     */
    private function stillBlocked(array $checks, array $overridden): bool
    {
        foreach ($checks as $check) {
            if ($check['result'] === 'BLOCK' && !in_array($check['check_code'], $overridden, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, string>> $checks
     * @param list<string> $overridden
     */
    private function blockMessage(array $checks, array $overridden): string
    {
        foreach ($checks as $check) {
            if ($check['result'] === 'BLOCK' && !in_array($check['check_code'], $overridden, true)) {
                return $check['message'];
            }
        }

        return 'Release is blocked.';
    }

    /**
     * @param list<array<string, mixed>> $requested
     * @return array{errors: array<string, string>, items: list<array{id: int, quantity: string}>}
     */
    private function lines(int $jobId, array $requested): array
    {
        $items = $this->repo->items($jobId);
        $chosen = [];
        if ($requested === []) {
            foreach ($items as $item) {
                $already = $this->repo->releasedQuantity((int) $item['id']);
                $left = Decimal::sub((string) $item['quantity'], $already, 4);
                if (Decimal::cmp($left, '0') > 0) {
                    $chosen[] = ['id' => (int) $item['id'], 'quantity' => $left];
                }
            }
        } else {
            foreach ($requested as $row) {
                $item = $this->repo->item((int) ($row['job_item_id'] ?? 0));
                if ($item === null || (int) $item['job_id'] !== $jobId) {
                    return ['errors' => ['_form' => 'That item is not on this job.'], 'items' => []];
                }
                $qty = (string) ($row['quantity'] ?? '0');
                if (!Decimal::isNumeric($qty) || Decimal::cmp($qty, '0') <= 0) {
                    return ['errors' => ['_form' => 'Release quantity must be greater than zero.'], 'items' => []];
                }
                $already = $this->repo->releasedQuantity((int) $item['id']);
                $next = Decimal::add($already, $qty, 4);
                if (Decimal::cmp($next, (string) $item['quantity']) > 0) {
                    return ['errors' => ['_form' => 'That release is more than the authorised quantity.'], 'items' => []];
                }
                $chosen[] = ['id' => (int) $item['id'], 'quantity' => Decimal::round($qty, 4)];
            }
        }
        if ($chosen === []) {
            return ['errors' => ['_form' => 'Nothing is left to release.'], 'items' => []];
        }

        return ['errors' => [], 'items' => $chosen];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function reserve(array $lines, int $jobId, int $releaseId, int $userId): void
    {
        if ($this->repo->reservationsForRelease($releaseId) > 0) {
            return;
        }
        foreach ($lines as $line) {
            $reserved = $this->stock->reserve([
                'job_id' => $jobId,
                'product_id' => (int) ($line['product_id'] ?? 0),
                'quantity' => (string) ($line['quantity'] ?? '0'),
                'stock_location_id' => (int) ($line['stock_location_id'] ?? 0),
                'notes' => 'Production release ' . $releaseId,
            ], $userId);
            if (($reserved['id'] ?? null) !== null) {
                $this->repo->linkReservation((int) $reserved['id'], $releaseId);
            }
        }
    }
}
