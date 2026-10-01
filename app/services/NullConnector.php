<?php

declare(strict_types=1);

namespace App\Services;

/**
 * No provider is configured. Calls succeed as a no-op so the ERP keeps working.
 */
final class NullConnector implements IntegrationConnectorInterface
{
    public function testConnection(): bool
    {
        return false;
    }

    public function push(string $entityType, int $entityId, array $payload): array
    {
        return ['ok' => true, 'external_id' => null, 'message' => 'No provider is configured.'];
    }

    public function pull(string $entityType): array
    {
        return ['ok' => true, 'rows' => [], 'message' => 'No provider is configured.'];
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        return ['ok' => false, 'message' => 'No provider is configured.'];
    }

    public function healthCheck(): array
    {
        return ['status' => 'UNCONFIGURED', 'message' => 'No provider is configured.'];
    }
}
