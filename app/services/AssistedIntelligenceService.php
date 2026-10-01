<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Assistance is optional. Output is a draft. It is never applied as a command.
 */
final class AssistedIntelligenceService
{
    public const UNAVAILABLE = 'ASSISTANCE CURRENTLY UNAVAILABLE';

    /** @var list<string> */
    public const TASKS = [
        'communication_summary',
        'job_summary',
        'followup_email',
        'whatsapp',
        'quote_description',
        'job_instructions',
        'document_extract',
        'categorise',
        'site_survey',
        'production_issue',
        'feedback',
        'internal_search',
    ];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @return array{status: string, text: string, suggestion_id: int|null}
     */
    public function draft(string $feature, string $data, int $userId, ?string $entityType = null, ?int $entityId = null, ?string $permission = null): array
    {
        $permission ??= match ($feature) {
            'document_extract' => 'ai.document_extract',
            'followup_email', 'whatsapp', 'quote_description' => 'ai.communication_draft',
            'internal_search' => 'ai.use',
            default => 'ai.summary',
        };
        if ($feature === 'internal_search' && $this->deniedFinanceQuestion($data)) {
            return $this->log($userId, $feature, $entityType, $entityId, 'DENIED', 'Permission withheld sensitive figures.', null);
        }
        if (!can('ai.use') || !can($permission)) {
            return $this->log($userId, $feature, $entityType, $entityId, 'DENIED', 'You cannot use this assistance.', null);
        }
        if (!(new FeatureFlagService($this->platform))->enabled('AI_ASSISTANCE') || SettingsService::get('ai_enabled', '0') !== '1') {
            return $this->log($userId, $feature, $entityType, $entityId, 'UNAVAILABLE', self::UNAVAILABLE, null);
        }
        $limit = (int) SettingsService::get('ai_monthly_request_limit', '0');
        if ($limit > 0 && $this->platform->interactionsThisMonth($userId) >= $limit) {
            return $this->log($userId, $feature, $entityType, $entityId, 'UNAVAILABLE', self::UNAVAILABLE, null);
        }
        $max = max(200, (int) SettingsService::get('ai_max_input_chars', '4000'));
        $data = $this->minimise($data, $max);
        $providerName = strtolower(trim((string) SettingsService::get('ai_provider', '')));
        $prompt = $this->platform->activePrompt($feature === 'quote_description' ? 'quote_description' : ($feature === 'document_extract' ? 'document_extract' : 'summary'));
        if ($providerName === '' || $providerName === 'none' || $providerName === 'unavailable') {
            return $this->log($userId, $feature, $entityType, $entityId, 'UNAVAILABLE', self::UNAVAILABLE, $prompt['version_number'] ?? null);
        }
        if ($providerName !== 'scripted') {
            return $this->log($userId, $feature, $entityType, $entityId, 'UNAVAILABLE', self::UNAVAILABLE, $prompt['version_number'] ?? null);
        }
        try {
            $result = (new ScriptedIntelligenceProvider())->complete($feature, $data);
        } catch (\Throwable) {
            return $this->log($userId, $feature, $entityType, $entityId, 'UNAVAILABLE', self::UNAVAILABLE, $prompt['version_number'] ?? null);
        }
        $logged = $this->log(
            $userId,
            $feature,
            $entityType,
            $entityId,
            'DRAFT',
            $result['text'],
            isset($prompt['version_number']) ? (int) $prompt['version_number'] : null,
            $result['tokens'],
            $result['cost'],
            $data
        );
        $suggestionId = $this->platform->insertSuggestion([
            'interaction_id' => $logged['suggestion_id'],
            'feature' => $feature,
            'entity_type' => $entityType !== null ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'suggestion_type' => 'DRAFT',
            'content' => mb_substr($result['text'], 0, 2000),
            'status' => 'DRAFT',
        ]);
        $logged['suggestion_id'] = $suggestionId;

        return $logged;
    }

    /**
     * @return array{status: string, text: string, rows: list<string>}
     */
    public function search(string $question, int $userId): array
    {
        if ($this->deniedFinanceQuestion($question) || !can('ai.use')) {
            ScriptedIntelligenceProvider::$lastInput = '';

            return ['status' => 'DENIED', 'text' => 'That information is outside your access.', 'rows' => []];
        }
        $question = strtolower($question);
        $rows = [];
        if (str_contains($question, 'artwork') && can('jobs.view')) {
            $rows = $this->lines("SELECT job_number, status FROM jobs WHERE status = 'AWAITING_CUSTOMER_APPROVAL' ORDER BY id DESC LIMIT 20");
        } elseif (str_contains($question, 'material') && can('inventory.view')) {
            $rows = ['Material shortages are listed on the material planning screen.'];
        } elseif (can('customers.view')) {
            $rows = ['Ask for a customer name to summarise open quotations and jobs you can already open.'];
        }

        return ['status' => 'OK', 'text' => 'Answered from records you can open.', 'rows' => $rows];
    }

    public function accept(int $suggestionId, int $userId): void
    {
        (new AuditService())->record('ai_suggestion', $suggestionId, 'AI_SUGGESTION_ACCEPTED', null, [], $userId);
    }

    public function reject(int $suggestionId, int $userId): void
    {
        (new AuditService())->record('ai_suggestion', $suggestionId, 'AI_SUGGESTION_REJECTED', null, [], $userId);
    }

    private function deniedFinanceQuestion(string $question): bool
    {
        $lower = strtolower($question);
        $sensitive = str_contains($lower, 'profit') || str_contains($lower, 'margin') || str_contains($lower, 'cost');

        return $sensitive && !can('reports.profitability');
    }

    private function minimise(string $data, int $max): string
    {
        $data = preg_replace('/(password|secret|api[_ ]?key)\s*[:=]\s*\S+/i', '[withheld]', $data) ?? $data;

        return SafeValue::summary($data, $max);
    }

    /**
     * @return array{status: string, text: string, suggestion_id: int|null}
     */
    private function log(
        int $userId,
        string $feature,
        ?string $entityType,
        ?int $entityId,
        string $status,
        string $text,
        ?int $promptVersion,
        ?int $tokens = null,
        ?float $cost = null,
        string $input = ''
    ): array {
        $id = $this->platform->insertInteraction([
            'user_id' => $userId,
            'feature' => $feature,
            'entity_type' => $entityType !== null ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'provider' => (string) SettingsService::get('ai_provider', ''),
            'model' => blank_to_null(SettingsService::get('ai_model', '')),
            'prompt_version' => $promptVersion,
            'input_summary' => SafeValue::summary($input === '' ? $text : $input),
            'output_summary' => SafeValue::summary($text),
            'status' => $status,
            'tokens_input' => $tokens,
            'tokens_output' => $tokens,
            'estimated_cost' => $cost,
        ]);

        return ['status' => $status, 'text' => $text, 'suggestion_id' => $status === 'DRAFT' ? $id : null];
    }

    /**
     * @return list<string>
     */
    private function lines(string $sql): array
    {
        $statement = \App\Helpers\Database::connection()->query($sql);
        $lines = [];
        $rows = $statement === false ? [] : $statement->fetchAll();
        foreach ($rows as $row) {
            if (is_array($row)) {
                $lines[] = implode(' ', array_map('strval', $row));
            }
        }

        return $lines;
    }
}
