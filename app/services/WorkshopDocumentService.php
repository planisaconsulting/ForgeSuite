<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\WorkshopRepository;

/**
 * Renders operational documents from live records and keeps each generated
 * copy. A signed customer document is never overwritten.
 */
final class WorkshopDocumentService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly DocumentTemplateEngine $templates = new DocumentTemplateEngine(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{html: string, document_id: int, outdated: bool}
     */
    public function jobCard(int $jobId, int $userId, bool $showCost = false): array
    {
        if (!can('documents.internal.view') && !can('jobs.view') && !can('workshop.view')) {
            return ['html' => '', 'document_id' => 0, 'outdated' => false];
        }
        if ($showCost && !can('costing.view')) {
            $showCost = false;
        }
        $this->workshop->supersedeOpen('JOB_CARD', 'job', $jobId, 'Replaced by a newer job card');
        $data = $this->cardData($jobId, $userId, $showCost);
        $html = \App\Helpers\View::capture('jobs/card', $data);
        $path = $this->store('job-card-' . $jobId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.html', $html);
        $template = $this->workshop->activeDocumentTemplate('JOB_CARD');
        $id = $this->workshop->insertDocument([
            'document_type' => 'JOB_CARD',
            'entity_type' => 'job',
            'entity_id' => $jobId,
            'document_number' => null,
            'template_id' => $template['id'] ?? null,
            'template_version' => (int) ($template['template_version'] ?? 1),
            'file_path' => $path,
            'status' => 'CURRENT',
            'immutable' => 0,
            'generated_by' => $userId,
        ]);

        return ['html' => $html, 'document_id' => $id, 'outdated' => false];
    }

    public function completionCertificate(int $jobId, int $userId, ?int $signatureId): int
    {
        $job = (new JobRepository())->find($jobId);
        if ($job === null) {
            return 0;
        }
        $number = (new NumberingService())->completion();
        $signature = $signatureId !== null ? $this->workshop->signature($signatureId) : null;
        $html = \App\Helpers\View::capture('documents/completion', [
            'job' => $job,
            'items' => $this->ops->items($jobId),
            'snags' => $this->workshop->snags(['job_id' => $jobId]),
            'company' => $this->company(),
            'number' => $number,
            'generated_at' => date('Y-m-d H:i:s'),
            'signature' => $signature,
            'statement' => (string) SettingsService::get('completion_acceptance_statement', ''),
        ]);
        $path = $this->store('completion-' . $number . '.html', $html);
        $template = $this->workshop->activeDocumentTemplate('COMPLETION_CERTIFICATE');
        $id = $this->workshop->insertDocument([
            'document_type' => 'COMPLETION_CERTIFICATE',
            'entity_type' => 'job',
            'entity_id' => $jobId,
            'document_number' => $number,
            'template_id' => $template['id'] ?? null,
            'template_version' => (int) ($template['template_version'] ?? 1),
            'file_path' => $path,
            'status' => 'CURRENT',
            'immutable' => $signatureId !== null ? 1 : 0,
            'generated_by' => $userId,
        ]);

        return $id;
    }

    public function supersedeJobCards(int $jobId, string $reason): void
    {
        $count = $this->workshop->supersedeOpen('JOB_CARD', 'job', $jobId, $reason);
        if ($count > 0) {
            $this->audit->record('job', $jobId, 'JOB_CARD_OUTDATED', null, ['reason' => $reason], (int) (auth_user()['id'] ?? 0));
        }
    }

    /**
     * @param array<string, mixed> $dispatch
     * @param list<array<string, mixed>> $items
     */
    public function deliveryNote(array $dispatch, array $items, int $userId, bool $collection = false): int
    {
        $existing = $this->workshop->currentDocument($collection ? 'COLLECTION_NOTE' : 'DELIVERY_NOTE', 'dispatch', (int) $dispatch['id']);
        if ($existing !== null && (int) $existing['immutable'] === 1) {
            return (int) $existing['id'];
        }
        $type = $collection ? 'COLLECTION_NOTE' : 'DELIVERY_NOTE';
        $number = (string) $dispatch['dispatch_number'];
        $html = \App\Helpers\View::capture('documents/delivery_note', [
            'dispatch' => $dispatch,
            'items' => $items,
            'company' => $this->company(),
            'collection' => $collection,
            'generated_at' => date('Y-m-d H:i:s'),
            'statement' => (string) SettingsService::get('pod_acceptance_statement', 'I confirm that the listed goods were received.'),
        ]);
        $path = $this->store(strtolower($type) . '-' . $number . '.html', $html);
        $template = $this->workshop->activeDocumentTemplate($type);

        return $this->workshop->insertDocument([
            'document_type' => $type,
            'entity_type' => 'dispatch',
            'entity_id' => (int) $dispatch['id'],
            'document_number' => $number,
            'template_id' => $template['id'] ?? null,
            'template_version' => (int) ($template['template_version'] ?? 1),
            'file_path' => $path,
            'status' => 'CURRENT',
            'immutable' => 0,
            'generated_by' => $userId,
        ]);
    }

    public function seal(int $documentId): void
    {
        $this->workshop->markImmutable($documentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function cardData(int $jobId, int $userId, bool $showCost): array
    {
        $job = (new JobRepository())->find($jobId);
        if ($job === null) {
            return [];
        }
        if ($showCost && !can('costing.view')) {
            $showCost = false;
        }
        $issued = $this->tracking->issue('JOB', $jobId, (string) $job['job_number'], $userId);
        $prior = $this->workshop->documentsFor('job', $jobId);
        $updated = false;
        foreach ($prior as $row) {
            if ((string) $row['document_type'] === 'JOB_CARD' && (string) $row['status'] === 'SUPERSEDED') {
                $updated = true;
            }
        }
        $artworks = $this->ops->artworks($jobId);
        $approved = null;
        foreach ($artworks as $artwork) {
            if ((string) $artwork['status'] === 'APPROVED' || (int) ($artwork['customer_approved'] ?? 0) === 1) {
                $approved = $artwork;
            }
        }

        return [
            'job' => $job,
            'items' => $this->ops->items($jobId),
            'requirements' => $this->ops->requirements($jobId),
            'artworks' => $artworks,
            'approved' => $approved,
            'stages' => $this->ops->stages($jobId),
            'company' => (string) ($this->company()['name'] ?? ''),
            'company_details' => $this->company(),
            'qr_url' => $issued['url'],
            'generated_at' => date('Y-m-d H:i:s'),
            'updated' => $updated,
            'show_cost' => $showCost,
            'costing' => $showCost ? (new JobCostingService())->report($jobId) : null,
            'pieces' => $this->workshop->productionItems($jobId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function company(): array
    {
        return [
            'name' => (string) SettingsService::get('company_name', ''),
            'phone' => (string) SettingsService::get('telephone', ''),
            'email' => (string) SettingsService::get('email', ''),
            'address' => (string) SettingsService::get('address', ''),
        ];
    }

    /**
     * Safe preview of a stored template. Unknown placeholders are dropped.
     *
     * @param array<string, scalar|null> $vars
     */
    public function renderTemplate(string $layout, array $vars): string
    {
        return $this->templates->render($layout, $vars);
    }

    private function store(string $filename, string $html): string
    {
        $dir = base_path('storage/documents');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document.html';
        file_put_contents($dir . '/' . $safe, $html);

        return 'storage/documents/' . $safe;
    }
}
