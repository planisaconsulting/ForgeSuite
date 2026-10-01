<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Extracted values are a proposal. Confirming them does not change a product cost
 * or create a purchase order.
 */
final class DocumentExtractionService
{
    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @return array{id: int, proposed: array<string, string>, confidence: float|null}
     */
    public function propose(string $documentType, string $label, string $text, int $userId, ?float $confidence = null): array
    {
        $proposed = $this->labels($text);
        $id = $this->platform->insertExtraction([
            'document_type' => strtoupper($documentType),
            'source_label' => mb_substr($label, 0, 180),
            'original_text' => $text,
            'entity_type' => null,
            'entity_id' => null,
            'status' => 'PROPOSED',
            'proposed_json' => json_encode($proposed, JSON_THROW_ON_ERROR),
            'confidence' => $confidence,
            'created_by' => $userId,
        ]);
        (new ReviewQueueService($this->platform))->add(
            'DOCUMENT_EXTRACTION',
            null,
            $id,
            'Review extracted ' . $documentType,
            'extraction',
            'Proposed data is not applied until someone confirms it.',
            null
        );

        return ['id' => $id, 'proposed' => $proposed, 'confidence' => $confidence];
    }

    /**
     * @param array<string, string>|null $edits
     * @return array<string, string>
     */
    public function review(int $id, string $decision, int $userId, ?array $edits = null): array
    {
        $row = $this->platform->extraction($id);
        if ($row === null) {
            return ['_form' => 'That extraction was not found.'];
        }
        $decision = strtoupper($decision);
        if (!in_array($decision, ['CONFIRMED', 'EDITED', 'REJECTED'], true)) {
            return ['decision' => 'Confirm, edit, or reject the proposal.'];
        }
        $proposed = json_decode((string) ($row['proposed_json'] ?? '{}'), true);
        $proposed = is_array($proposed) ? $proposed : [];
        if ($decision === 'EDITED' && $edits !== null) {
            foreach ($edits as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $proposed[$key] = (string) $value;
                }
            }
        }
        $this->platform->reviewExtraction(
            $id,
            $decision === 'EDITED' ? 'EDITED' : $decision,
            $decision === 'REJECTED' ? null : json_encode($proposed, JSON_THROW_ON_ERROR),
            $userId
        );

        return [];
    }

    /**
     * Labelled lines become fields. Instruction-like lines stay in the original text only.
     *
     * @return array<string, string>
     */
    public function labels(string $text): array
    {
        $map = [
            'supplier' => 'supplier',
            'product' => 'product',
            'material' => 'product',
            'qty' => 'quantity',
            'quantity' => 'quantity',
            'unit cost' => 'unit_cost',
            'price' => 'unit_cost',
            'total' => 'total',
        ];
        $found = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$label, $value] = array_map('trim', explode(':', $line, 2));
            $key = $map[strtolower($label)] ?? null;
            if ($key === null || $value === '') {
                continue;
            }
            $found[$key] = str_replace([',', 'R', 'r'], '', $value);
        }

        return $found;
    }
}
