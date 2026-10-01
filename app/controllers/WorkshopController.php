<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\WorkshopRepository;
use App\Services\DispatchService;
use App\Services\KioskService;
use App\Services\LabelService;
use App\Services\MaterialScanService;
use App\Services\ProductionItemService;
use App\Services\ProofOfDeliveryService;
use App\Services\QualityCheckService;
use App\Services\SettingsService;
use App\Services\SignatureService;
use App\Services\SnagService;
use App\Services\TrackingCodeService;
use App\Services\WorkshopActionService;
use App\Services\WorkshopDocumentService;

/**
 * Workshop floor, scanning, and the screens a phone or tablet uses on the job.
 */
final class WorkshopController
{
    public function floor(): void
    {
        $filters = [
            'priority' => strtoupper(trim((string) ($_GET['priority'] ?? ''))),
            'due' => trim((string) ($_GET['due'] ?? '')),
        ];
        $repo = new WorkshopRepository();
        View::render('workshop/floor', [
            'title' => 'Workshop floor',
            'activeNav' => 'workshop-floor',
            'rows' => $repo->floor($filters),
            'counts' => $repo->counts(),
            'filters' => $filters,
            'poll' => (int) SettingsService::get('workshop_poll_seconds', '45'),
        ]);
    }

    public function counts(): void
    {
        json_response((new WorkshopRepository())->counts());
    }

    public function queue(): void
    {
        View::render('workshop/queue', [
            'title' => 'Production queue',
            'activeNav' => 'workshop-queue',
            'rows' => (new WorkshopRepository())->floor([]),
        ]);
    }

    public function qc(): void
    {
        View::render('workshop/qc', [
            'title' => 'Quality checks',
            'activeNav' => 'workshop-qc',
            'checks' => (new WorkshopRepository())->checklist(null),
        ]);
    }

    public function reprints(): void
    {
        View::render('workshop/reprints', [
            'title' => 'Reprints',
            'activeNav' => 'workshop-reprints',
            'reasons' => (new WorkshopRepository())->reprintReasons(),
        ]);
    }

    public function scanForm(): void
    {
        View::render('workshop/scan', [
            'title' => 'Scan',
            'activeNav' => 'workshop-scan',
            'scripts' => ['assets/js/scan.js'],
        ]);
    }

    public function scanSubmit(): void
    {
        $code = trim((string) ($_POST['code'] ?? ''));
        $resolved = (new TrackingCodeService())->resolve($code);
        if ($resolved !== null) {
            redirect('/scan/' . $code);
        }
        $found = (new TrackingCodeService())->locate($code);
        if ($found === null) {
            flash('error', 'That code was not recognised.');
            redirect('/workshop/scan');
        }
        $this->showEntity((string) $found['entity_type'], (int) $found['entity_id'], null);
    }

    public function openToken(string $token): void
    {
        $resolved = (new TrackingCodeService())->resolve($token);
        if ($resolved === null) {
            http_response_code(404);
            View::render('errors/404', [
                'title' => 'Code not recognised',
                'activeNav' => 'workshop-scan',
                'message' => 'That code is not valid. Scan the current label or type the tracking code.',
            ]);

            return;
        }
        (new TrackingCodeService())->recordScan($resolved, 'VIEWED', (int) auth_user()['id'], substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 80));
        $this->showEntity((string) $resolved['entity_type'], (int) $resolved['entity_id'], $resolved);
    }

    public function action(): void
    {
        $id = (int) ($_POST['production_item_id'] ?? 0);
        $action = strtoupper(trim((string) ($_POST['action'] ?? '')));
        $userId = (int) auth_user()['id'];
        $key = trim((string) ($_POST['idempotency_key'] ?? ''));
        $service = new WorkshopActionService();
        $errors = match ($action) {
            'START' => $service->start($id, $userId, $key),
            'PAUSE' => $service->pause($id, (string) ($_POST['reason'] ?? ''), $userId, $key),
            'BLOCK' => $service->block($id, (string) ($_POST['reason'] ?? ''), $userId, $key),
            'COMPLETE' => $service->complete($id, (string) ($_POST['quantity'] ?? '0'), $userId, $key, (string) ($_POST['notes'] ?? '')),
            'REPRINT' => $service->reprint($id, $_POST, $userId),
            default => ['_form' => 'That action is not available.'],
        };
        $back = '/workshop/items/' . $id;
        if ($errors !== []) {
            flash('error', (string) reset($errors));
        } else {
            flash('success', 'Saved.');
        }
        redirect($back);
    }

    public function item(string $id): void
    {
        $piece = (new WorkshopRepository())->productionItem((int) $id);
        if ($piece === null) {
            abort_not_found('That production item was not found.');
        }
        $job = (new JobRepository())->find((int) $piece['job_id']);
        View::render('workshop/item', [
            'title' => (string) $piece['tracking_code'],
            'activeNav' => 'workshop-floor',
            'piece' => $piece,
            'job' => $job,
            'stages' => (new WorkshopRepository())->stages((int) $piece['job_id'], (int) $piece['job_item_id']),
            'reasons' => (new WorkshopRepository())->reprintReasons(),
            'artworks' => (new OperationsRepository())->artworks((int) $piece['job_id']),
        ]);
    }

    public function material(): void
    {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $result = (new MaterialScanService())->issue($jobId, $_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
        } else {
            flash('success', 'Material issued.');
        }
        redirect('/jobs/' . $jobId . '?tab=materials');
    }

    public function quality(): void
    {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $errors = (new QualityCheckService())->record($jobId, $_POST, (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Quality check saved.' : (string) reset($errors));
        redirect('/workshop/qc');
    }

    public function kiosk(): void
    {
        View::render('workshop/kiosk', [
            'title' => 'Workshop kiosk',
            'activeNav' => 'workshop-kiosk',
        ], 'layouts/auth');
    }

    public function kioskPin(): void
    {
        $result = (new KioskService())->identifyByPin((string) ($_POST['email'] ?? ''), (string) ($_POST['pin'] ?? ''));
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
            redirect('/workshop/kiosk');
        }
        redirect('/workshop');
    }

    public function kioskBadge(): void
    {
        $result = (new KioskService())->identifyByBadge(trim((string) ($_POST['token'] ?? '')));
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
            redirect('/workshop/kiosk');
        }
        redirect('/workshop');
    }

    public function trace(string $id): void
    {
        if (!can('tracking.traceability.view') && !can('jobs.view')) {
            deny_access('You cannot view traceability.');
        }
        $job = (new JobRepository())->find((int) $id);
        if ($job === null) {
            abort_not_found('That job was not found.');
        }
        View::render('workshop/trace', [
            'title' => 'Traceability',
            'activeNav' => 'jobs',
            'job' => $job,
            'events' => (new WorkshopRepository())->jobTrace((int) $job['id']),
        ]);
    }

    public function signoff(string $id): void
    {
        if (!can('installation.signoff')) {
            deny_access('You cannot capture installation sign-off.');
        }
        $job = (new JobRepository())->find((int) $id);
        if ($job === null) {
            abort_not_found('That job was not found.');
        }
        $captured = (new SignatureService())->capture('INSTALLATION', (int) $job['id'], $_POST, (int) auth_user()['id']);
        if ($captured['errors'] !== []) {
            flash('error', (string) reset($captured['errors']));
            redirect('/jobs/' . $job['id']);
        }
        $doc = (new WorkshopDocumentService())->completionCertificate((int) $job['id'], (int) auth_user()['id'], $captured['id']);
        (new \App\Services\AuditService())->record('job', (int) $job['id'], 'INSTALLATION_SIGNED_OFF', null, [
            'signature_id' => $captured['id'],
            'document_id' => $doc,
        ], (int) auth_user()['id']);
        (new \App\Services\AutomationService())->fire('INSTALLATION_SIGNED_OFF', 'job', (int) $job['id'], (int) auth_user()['id']);
        flash('success', 'Installation sign-off stored.');
        redirect('/jobs/' . $job['id']);
    }

    public function reports(): void
    {
        if (!can('reports.operations') && !can('tracking.traceability.view')) {
            deny_access('You cannot view this report.');
        }
        $repo = new WorkshopRepository();
        $yield = $repo->yieldCounts();
        $reprint = $repo->reprintTotals();
        $inspected = max(0, (int) $yield['inspected']);
        $first = $inspected === 0 ? null : round(((int) $yield['first_pass'] / $inspected) * 100, 1);
        $produced = (float) $reprint['produced'];
        $rate = $produced <= 0 ? null : round(((float) $reprint['reprinted'] / $produced) * 100, 1);
        View::render('reports/workshop', [
            'title' => 'Production throughput',
            'activeNav' => 'report-throughput',
            'counts' => $repo->counts(),
            'first_pass' => $first,
            'inspected' => $inspected,
            'reprint_rate' => $rate,
            'reprinted' => $reprint['reprinted'],
            'produced' => $reprint['produced'],
        ]);
    }

    public function templates(): void
    {
        if (!can('documents.templates.manage')) {
            deny_access('You cannot manage document templates.');
        }
        View::render('admin/document_templates', [
            'title' => 'Document templates',
            'activeNav' => 'doc-templates',
            'rows' => (new WorkshopRepository())->documentTemplates(),
            'labels' => (new WorkshopRepository())->labelTemplates(),
        ]);
    }

    public function saveTemplate(): void
    {
        if (!can('documents.templates.manage')) {
            deny_access('You cannot manage document templates.');
        }
        $layout = (string) ($_POST['layout_config'] ?? '');
        if (str_contains($layout, '<?') || preg_match('/\{\{\s*php/i', $layout)) {
            flash('error', 'Templates cannot contain code.');
            redirect('/admin/document-templates');
        }
        (new WorkshopRepository())->saveDocumentTemplate([
            'document_type' => strtoupper(trim((string) ($_POST['document_type'] ?? 'OTHER'))),
            'name' => trim((string) ($_POST['name'] ?? 'Template')),
            'template_version' => 1,
            'layout_config' => $layout,
            'active' => 1,
        ], ((int) ($_POST['id'] ?? 0)) > 0 ? (int) $_POST['id'] : null);
        flash('success', 'Template saved.');
        redirect('/admin/document-templates');
    }

    public function labelPreview(): void
    {
        $type = strtoupper(trim((string) ($_GET['type'] ?? 'ROLL')));
        $id = (int) ($_GET['id'] ?? 0);
        $copies = max(1, (int) ($_GET['copies'] ?? 1));
        $reprint = (string) ($_GET['reprint'] ?? '') === '1';
        $built = (new LabelService())->preview($type, $id, ((int) ($_GET['template'] ?? 0)) ?: null, $copies, (int) auth_user()['id'], $reprint, (string) ($_GET['reason'] ?? ''));
        if (($built['error'] ?? '') !== '') {
            flash('error', (string) $built['error']);
            redirect('/workshop');
        }
        echo $built['html'];
        exit;
    }

    public function documents(): void
    {
        if (!can('documents.internal.view') && !can('jobs.view')) {
            deny_access('You cannot search these documents.');
        }
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'type' => strtoupper(trim((string) ($_GET['type'] ?? ''))),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
        View::render('documents/generated', [
            'title' => 'Workshop documents',
            'activeNav' => 'doc-centre',
            'rows' => (new WorkshopRepository())->searchDocuments($filters),
            'filters' => $filters,
        ]);
    }

    public function download(string $id): void
    {
        $doc = (new WorkshopRepository())->document((int) $id);
        if ($doc === null) {
            abort_not_found('That document was not found.');
        }
        $internal = in_array((string) $doc['document_type'], ['JOB_CARD', 'WORK_ORDER', 'MATERIAL_LABEL', 'JOB_LABEL', 'INVENTORY_LABEL'], true);
        if ($internal && !can('documents.internal.view') && !can('jobs.view')) {
            deny_access('You cannot open that document.');
        }
        if (!$internal && !can('dispatch.view') && !can('jobs.view') && !can('documents.internal.view')) {
            deny_access('You cannot open that document.');
        }
        $path = base_path((string) $doc['file_path']);
        if (!is_file($path)) {
            abort_not_found('That file is no longer on disk.');
        }
        $mime = str_ends_with($path, '.pdf') ? 'application/pdf' : 'text/html; charset=utf-8';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }

    /**
     * @param array<string, mixed>|null $resolved
     */
    private function showEntity(string $type, int $id, ?array $resolved): void
    {
        $repo = new WorkshopRepository();
        $job = null;
        $piece = null;
        $inventory = null;
        $dispatch = null;
        $package = null;
        if ($type === 'JOB') {
            $job = (new JobRepository())->find($id);
        } elseif ($type === 'PRODUCTION_ITEM') {
            $piece = $repo->productionItem($id);
            $job = $piece === null ? null : (new JobRepository())->find((int) $piece['job_id']);
        } elseif ($type === 'DISPATCH') {
            $dispatch = $repo->dispatch($id);
        } elseif ($type === 'PACKAGE') {
            $package = $repo->package($id);
            $job = $package === null ? null : (new JobRepository())->find((int) $package['job_id']);
        } elseif (in_array($type, ['ROLL', 'SHEET', 'OFFCUT', 'INVENTORY_ITEM'], true)) {
            $inventory = (new \App\Repositories\InventoryRepository())->item($id);
        }
        if ($job === null && $piece === null && $inventory === null && $dispatch === null && $package === null) {
            abort_not_found('That code does not match a workshop record.');
        }
        View::render('workshop/entity', [
            'title' => 'Scan',
            'activeNav' => 'workshop-scan',
            'type' => $type,
            'job' => $job,
            'piece' => $piece,
            'inventory' => $inventory,
            'dispatch' => $dispatch,
            'package' => $package,
            'stages' => $job === null ? [] : $repo->stages((int) $job['id'], $piece === null ? null : (int) $piece['job_item_id']),
            'artworks' => $job === null ? [] : (new OperationsRepository())->artworks((int) $job['id']),
            'resolved' => $resolved,
        ]);
    }
}
