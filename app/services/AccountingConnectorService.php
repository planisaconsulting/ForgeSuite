<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Sign-Forge stays the source for jobs and quotations.
 * An external ledger may own accounting status. This class does not post journals
 * and does not overwrite an internal invoice when the external copy differs.
 */
final class AccountingConnectorService implements IntegrationConnectorInterface
{
    /** @var list<string> */
    public const MODES = ['DISABLED', 'EXPORT_ONLY', 'MANUAL_SYNC', 'AUTOMATIC_SYNC'];

    /** @var list<string> */
    public const MAPS = ['CUSTOMER', 'INVOICE', 'CREDIT_NOTE', 'PAYMENT', 'TAX_CODE', 'ACCOUNT'];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    public function connector(): IntegrationConnectorInterface
    {
        $provider = strtolower(trim((string) SettingsService::get('accounting_provider', '')));
        if ($provider === '' || $provider === 'none') {
            return new NullConnector();
        }
        if ($provider === 'unavailable') {
            return new UnavailableConnector();
        }

        return new NullConnector();
    }

    public function mode(): string
    {
        $mode = strtoupper((string) SettingsService::get('accounting_sync_mode', 'DISABLED'));

        return in_array($mode, self::MODES, true) ? $mode : 'DISABLED';
    }

    public function automaticAllowed(): bool
    {
        if ($this->mode() !== 'AUTOMATIC_SYNC') {
            return false;
        }
        if (!(new FeatureFlagService($this->platform))->enabled('ACCOUNTING_SYNC')) {
            return false;
        }
        $row = $this->platform->connector('accounting');

        return $row !== null && $row['tested_at'] !== null && (string) $row['health_status'] === 'HEALTHY';
    }

    public function testConnection(): bool
    {
        $ok = $this->connector()->testConnection();
        $this->platform->upsertConnector([
            'connector_key' => 'accounting',
            'connector_type' => 'ACCOUNTING',
            'provider' => (string) SettingsService::get('accounting_provider', ''),
            'active' => 1,
            'configuration_json' => json_encode(['mode' => $this->mode()]),
            'tested_at' => $ok ? date('Y-m-d H:i:s') : null,
            'health_status' => $ok ? 'HEALTHY' : 'FAILED',
            'last_success_at' => $ok ? date('Y-m-d H:i:s') : null,
            'last_error' => $ok ? null : 'Connection test failed.',
        ]);

        return $ok;
    }

    public function push(string $entityType, int $entityId, array $payload): array
    {
        if ($this->mode() === 'DISABLED') {
            return ['ok' => true, 'external_id' => null, 'message' => 'Accounting sync is disabled.'];
        }
        try {
            return $this->connector()->push($entityType, $entityId, $payload);
        } catch (\Throwable $e) {
            $this->queue($entityType, $entityId, $e->getMessage());

            return ['ok' => false, 'external_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function pull(string $entityType): array
    {
        return $this->connector()->pull($entityType);
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        return $this->connector()->handleWebhook($payload, $signature);
    }

    public function healthCheck(): array
    {
        return $this->connector()->healthCheck();
    }

    /**
     * External change is queued for a person. The internal invoice is not overwritten.
     *
     * @param array<string, mixed> $external
     */
    public function noteConflict(int $invoiceId, array $external): int
    {
        $this->queue('INVOICE', $invoiceId, 'SYNC CONFLICT');

        return (new ReviewQueueService($this->platform))->add(
            'SYNC_CONFLICT',
            'INVOICE',
            $invoiceId,
            'Review the external invoice change',
            'accounting',
            'The external copy differs. The internal invoice was left as it is.',
            null
        );
    }

    public function map(string $type, string $local, string $external, string $description): void
    {
        if (!in_array($type, self::MAPS, true)) {
            return;
        }
        $this->platform->upsertCodeMap([
            'map_type' => $type,
            'local_code' => $local,
            'external_code' => $external,
            'description' => $description,
        ]);
    }

    private function queue(string $entityType, int $entityId, string $message): void
    {
        $this->platform->insertIssue([
            'provider' => 'accounting',
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
            'failure' => mb_substr($message, 0, 255),
            'attempts' => 1,
            'max_attempts' => 3,
            'safe_retry' => 0,
            'idempotency_key' => 'accounting:' . strtoupper($entityType) . ':' . $entityId,
            'status' => 'OPEN',
            'next_retry_at' => null,
        ]);
    }
}
