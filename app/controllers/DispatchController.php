<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\WorkshopRepository;
use App\Services\DispatchService;
use App\Services\PackageService;
use App\Services\ProofOfDeliveryService;
use App\Services\SnagService;

final class DispatchController
{
    public function index(): void
    {
        View::render('dispatch/index', [
            'title' => 'Dispatch',
            'activeNav' => 'dispatch',
            'rows' => (new WorkshopRepository())->dispatches([
                'q' => trim((string) ($_GET['q'] ?? '')),
            ]),
        ]);
    }

    public function show(string $id): void
    {
        $row = (new WorkshopRepository())->dispatch((int) $id);
        if ($row === null) {
            abort_not_found('That dispatch was not found.');
        }
        $service = new DispatchService();
        View::render('dispatch/show', [
            'title' => (string) $row['dispatch_number'],
            'activeNav' => 'dispatch',
            'dispatch' => $row,
            'items' => (new WorkshopRepository())->dispatchItems((int) $row['id']),
            'progress' => $service->progress((int) $row['id']),
            'pod' => (new WorkshopRepository())->podByDispatch((int) $row['id']),
            'statement' => (string) \App\Services\SettingsService::get('pod_acceptance_statement', 'I confirm that the listed goods were received.'),
        ]);
    }

    public function store(): void
    {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $result = (new DispatchService())->create($jobId, $_POST, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', (string) reset($result['errors']));
            redirect('/dispatch');
        }
        flash('success', 'Dispatch opened.');
        redirect('/dispatch/' . $result['id']);
    }

    public function pack(string $id): void
    {
        $row = (new WorkshopRepository())->dispatch((int) $id);
        if ($row === null) {
            abort_not_found('That dispatch was not found.');
        }
        $ids = [];
        foreach ((new WorkshopRepository())->dispatchItems((int) $row['id']) as $item) {
            if ((string) $item['status'] === 'EXPECTED' && (int) ($item['production_item_id'] ?? 0) > 0) {
                $ids[] = (int) $item['production_item_id'];
            }
        }
        $packed = (new PackageService())->create((int) $row['job_id'], 'Dispatch ' . (string) $row['dispatch_number'], $ids, (int) auth_user()['id']);
        if ($packed['id'] === null) {
            flash('error', (string) reset($packed['errors']));
            redirect('/dispatch/' . $id);
        }
        flash('success', 'Package ' . $packed['code'] . ' is ready to label.');
        redirect('/labels/preview?type=PACKAGE&id=' . $packed['id']);
    }

    public function scan(string $id): void
    {
        $result = (new DispatchService())->scan((int) $id, (string) ($_POST['code'] ?? ''), (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
        } elseif ($result['duplicate']) {
            flash('error', 'That item is already loaded.');
        } else {
            flash('success', 'Loaded.');
        }
        redirect('/dispatch/' . $id);
    }

    public function complete(string $id): void
    {
        $errors = (new DispatchService())->markDispatched((int) $id, (int) auth_user()['id'], (string) ($_POST['override'] ?? ''));
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Dispatched.' : (string) reset($errors));
        redirect('/dispatch/' . $id);
    }

    public function pod(string $id): void
    {
        $result = (new ProofOfDeliveryService())->capture((int) $id, $_POST, (int) auth_user()['id']);
        flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Proof of delivery stored.' : (string) reset($result['errors']));
        redirect('/dispatch/' . $id);
    }

    public function snags(): void
    {
        View::render('snags/index', [
            'title' => 'Snags',
            'activeNav' => 'snags',
            'rows' => (new WorkshopRepository())->snags([
                'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            ]),
        ]);
    }

    public function storeSnag(): void
    {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $errors = (new SnagService())->create($jobId, $_POST, (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Snag recorded.' : (string) reset($errors));
        redirect('/snags');
    }

    public function updateSnag(string $id): void
    {
        $errors = (new SnagService())->update((int) $id, (string) ($_POST['status'] ?? ''), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Snag updated.' : (string) reset($errors));
        redirect('/snags');
    }
}
