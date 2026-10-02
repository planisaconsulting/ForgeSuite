<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ProductionControlRepository;
use App\Services\FulfilmentService;
use App\Services\ProductionChangeImpactService;
use App\Services\ProductionPlanningService;
use App\Services\ProductionReleaseService;
use App\Services\ReleaseReadinessService;

/**
 * Release, queue, and the workshop board.
 */
final class ProductionControlController
{
    public function queue(): void
    {
        $repo = new ProductionControlRepository();
        $filters = [
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'priority' => strtoupper(trim((string) ($_GET['priority'] ?? ''))),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
        ];
        View::render('production/queue', [
            'title' => 'Production queue',
            'activeNav' => 'production-queue',
            'rows' => $repo->queue($filters, 50, max(0, (int) ($_GET['offset'] ?? 0))),
            'upcoming' => $repo->upcomingCount(),
            'filters' => $filters,
        ]);
    }

    public function today(): void
    {
        $repo = new ProductionControlRepository();
        View::render('production/today', [
            'title' => 'Today',
            'activeNav' => 'production-today',
            'rows' => $repo->queue([], 80, 0),
            'watch' => $repo->watchdogCounts(),
            'layout' => 'app',
        ]);
    }

    public function release(string $jobId): void
    {
        $id = (int) $jobId;
        $repo = new ProductionControlRepository();
        $job = $repo->job($id);
        if ($job === null) {
            abort_not_found('That job was not found.');
        }
        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $items = [];
            if (trim((string) ($_POST['item_id'] ?? '')) !== '') {
                $items[] = ['job_item_id' => (int) $_POST['item_id'], 'quantity' => (string) $_POST['item_quantity']];
            }
            $result = (new ProductionReleaseService())->release($id, (int) auth_user()['id'], [
                'items' => $items,
                'idempotency_key' => trim((string) ($_POST['idempotency_key'] ?? '')),
                'new_version' => !empty($_POST['new_version']),
                'notes' => (string) ($_POST['notes'] ?? ''),
                'reserve' => trim((string) ($_POST['reserve_product_id'] ?? '')) === '' ? [] : [[
                    'product_id' => (int) $_POST['reserve_product_id'],
                    'quantity' => (string) $_POST['reserve_quantity'],
                    'stock_location_id' => (int) ($_POST['reserve_location_id'] ?? 0),
                ]],
            ]);
            if ($result['errors'] !== []) {
                flash('error', (string) reset($result['errors']));
            } else {
                flash('success', 'Release ' . (string) ($result['release']['release_number'] ?? '') . ' recorded.');
            }
        }
        View::render('production/release', [
            'title' => 'Release to production',
            'activeNav' => 'jobs',
            'job' => $job,
            'readiness' => (new ReleaseReadinessService())->evaluate($id),
            'current' => $repo->currentRelease($id),
            'items' => $repo->items($id),
            'plan' => ($job['target_date'] ?? null) !== null
                ? (new ProductionPlanningService())->latestStart((string) $job['target_date'], ['production' => '1', 'qc' => '0.5', 'pack' => '1'])
                : null,
            'showCosts' => can('production.view_costs') || can('costing.view'),
            'result' => $result,
        ]);
    }

    public function pack(string $id): void
    {
        $release = (new ProductionControlRepository())->release((int) $id);
        if ($release === null) {
            abort_not_found('That release was not found.');
        }
        $snapshot = json_decode((string) ($release['snapshot_json'] ?? ''), true);
        View::render('production/pack', [
            'title' => (string) $release['release_number'],
            'activeNav' => 'jobs',
            'release' => $release,
            'snapshot' => is_array($snapshot) ? $snapshot : [],
            'showCosts' => can('production.view_costs') || can('costing.view'),
        ]);
    }

    public function scan(string $number): void
    {
        View::render('production/scan', [
            'title' => 'Release scan',
            'activeNav' => 'production-queue',
            'scan' => (new ProductionReleaseService())->scan($number),
        ]);
    }

    public function change(string $jobId): void
    {
        $impact = (new ProductionChangeImpactService())->record(
            (int) $jobId,
            (string) ($_POST['field'] ?? ''),
            (string) ($_POST['source'] ?? 'PRODUCTION'),
            (string) ($_POST['reason'] ?? ''),
            (string) ($_POST['change'] ?? ''),
            (int) auth_user()['id']
        );
        flash($impact['errors'] === [] ? 'success' : 'error', $impact['errors'] === [] ? 'Impact: ' . $impact['impact'] : (string) reset($impact['errors']));
        redirect('/production/jobs/' . (int) $jobId . '/release');
    }

    public function fulfil(string $id): void
    {
        $errors = (new FulfilmentService())->fulfil((int) $id, (string) ($_POST['quantity'] ?? '0'), (int) auth_user()['id'], blank_to_null($_POST['collected_by'] ?? null));
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Fulfilment recorded.' : (string) reset($errors));
        redirect('/production/queue');
    }
}
