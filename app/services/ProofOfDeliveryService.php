<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\WorkshopCodes;
use App\Helpers\Database;
use App\Repositories\WorkshopRepository;

/**
 * One proof of delivery per dispatch. A second save returns the signed copy.
 */
final class ProofOfDeliveryService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly SignatureService $signatures = new SignatureService(),
        private readonly WorkshopDocumentService $documents = new WorkshopDocumentService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, document_id: int|null}
     */
    public function capture(int $dispatchId, array $input, int $userId): array
    {
        if (!can('delivery.signoff')) {
            return ['errors' => ['_form' => 'You cannot capture proof of delivery.'], 'id' => null, 'document_id' => null];
        }
        $existing = $this->workshop->podByDispatch($dispatchId);
        if ($existing !== null) {
            $doc = $this->workshop->currentDocument('DELIVERY_NOTE', 'dispatch', $dispatchId)
                ?? $this->workshop->currentDocument('COLLECTION_NOTE', 'dispatch', $dispatchId);

            return ['errors' => [], 'id' => (int) $existing['id'], 'document_id' => $doc === null ? null : (int) $doc['id']];
        }
        $dispatch = $this->workshop->dispatch($dispatchId);
        if ($dispatch === null) {
            return ['errors' => ['_form' => 'That dispatch was not found.'], 'id' => null, 'document_id' => null];
        }
        $name = trim((string) ($input['recipient_name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['recipient_name' => 'Who received the goods?'], 'id' => null, 'document_id' => null];
        }
        $podId = 0;
        $docId = 0;
        try {
            Database::transaction(function () use ($dispatch, $input, $userId, $name, &$podId, &$docId): void {
                if ($this->workshop->podByDispatch((int) $dispatch['id']) !== null) {
                    $podId = (int) $this->workshop->podByDispatch((int) $dispatch['id'])['id'];

                    return;
                }
                $signature = $this->signatures->capture('DISPATCH', (int) $dispatch['id'], [
                    'signer_name' => $name,
                    'signer_contact' => $input['recipient_contact'] ?? null,
                    'statement' => SettingsService::get('pod_acceptance_statement', 'I confirm that the listed goods were received.'),
                    'signature_png' => $input['signature_png'] ?? '',
                    'client_signed_at' => $input['client_signed_at'] ?? null,
                ], $userId);
                if ($signature['errors'] !== []) {
                    throw new StockRejected($signature['errors']);
                }
                $gps = SettingsService::get('gps_capture_enabled', '0') === '1';
                $podId = $this->workshop->insertPod([
                    'dispatch_id' => (int) $dispatch['id'],
                    'recipient_name' => $name,
                    'recipient_contact' => blank_to_null($input['recipient_contact'] ?? null),
                    'delivery_datetime' => date('Y-m-d H:i:s'),
                    'signature_file_id' => $signature['id'],
                    'photo_file_id' => ((int) ($input['photo_file_id'] ?? 0)) > 0 ? (int) $input['photo_file_id'] : null,
                    'gps_latitude' => $gps ? $this->coord($input['gps_latitude'] ?? null) : null,
                    'gps_longitude' => $gps ? $this->coord($input['gps_longitude'] ?? null) : null,
                    'notes' => blank_to_null($input['notes'] ?? null),
                    'created_by' => $userId,
                ]);
                $items = $this->workshop->dispatchItems((int) $dispatch['id']);
                $collection = (string) $dispatch['dispatch_type'] === 'COLLECTION';
                $docId = $this->documents->deliveryNote($dispatch, $items, $userId, $collection);
                $this->documents->seal($docId);
                $this->workshop->updateDispatch((int) $dispatch['id'], [
                    'status' => WorkshopCodes::DISPATCH_DELIVERED,
                    'dispatched_at' => $dispatch['dispatched_at'] ?? date('Y-m-d H:i:s'),
                    'notes' => $dispatch['notes'],
                ]);
                $this->audit->record('dispatch', (int) $dispatch['id'], 'DELIVERED', null, [
                    'proof_id' => $podId,
                    'recipient_name' => $name,
                ], $userId);
                (new AutomationService())->fire('DELIVERED', 'dispatch', (int) $dispatch['id'], $userId);
                (new WorkshopNotifier())->send('DELIVERY_COMPLETE', 'Delivery complete', (string) $dispatch['dispatch_number'], 'job', (int) $dispatch['job_id'], 'ACCOUNTS');
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null, 'document_id' => null];
        }

        return ['errors' => [], 'id' => $podId, 'document_id' => $docId];
    }

    private function coord(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        return $text;
    }
}
