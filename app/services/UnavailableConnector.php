<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Used when a provider is selected but cannot be reached.
 * The core transaction has already finished.
 */
final class UnavailableConnector implements IntegrationConnectorInterface
{
    public function testConnection(): bool
    {
        return false;
    }

    public function push(string $entityType, int $entityId, array $payload): array
    {
        return ['ok' => false, 'external_id' => null, 'message' => 'Accounting provider is unavailable'];
    }

    public function pull(string $entityType): array
    {
        return ['ok' => false, 'rows' => [], 'message' => 'Accounting provider is unavailable'];
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        return ['ok' => false, 'message' => 'Accounting provider is unavailable'];
    }

    public function healthCheck(): array
    {
        return ['status' => 'FAILED', 'message' => 'Accounting provider is unavailable'];
    }
}
