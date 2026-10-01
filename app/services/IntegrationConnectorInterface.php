<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Providers sit behind this boundary. Domain services do not call a vendor SDK directly.
 */
interface IntegrationConnectorInterface
{
    public function testConnection(): bool;

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, external_id: string|null, message: string}
     */
    public function push(string $entityType, int $entityId, array $payload): array;

    /**
     * @return array{ok: bool, rows: list<array<string, mixed>>, message: string}
     */
    public function pull(string $entityType): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, message: string}
     */
    public function handleWebhook(array $payload, string $signature): array;

    /**
     * @return array{status: string, message: string}
     */
    public function healthCheck(): array;
}
