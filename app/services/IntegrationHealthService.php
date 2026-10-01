<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * A short status for each connector. It does not call a provider on every page view
 * unless a test has already been stored.
 */
final class IntegrationHealthService
{
    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    public function dashboard(): array
    {
        $connectors = [];
        foreach ($this->platform->connectors() as $row) {
            $connectors[(string) $row['connector_key']] = $row;
        }
        $items = [
            $this->card('Email', $connectors['email'] ?? null, SettingsService::get('smtp_host', '') !== ''),
            $this->card('WhatsApp', $connectors['whatsapp'] ?? null, false),
            $this->card('Accounting', $connectors['accounting'] ?? null, SettingsService::get('accounting_provider', '') !== ''),
            $this->card('Payments', $connectors['payments'] ?? null, SettingsService::get('payment_provider', '') !== ''),
            $this->card('AI', $connectors['ai'] ?? null, SettingsService::get('ai_provider', '') !== ''),
            $this->card('Webhooks', $connectors['webhooks'] ?? null, true),
        ];

        return $items;
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{name: string, status: string, detail: string}
     */
    private function card(string $name, ?array $row, bool $configured): array
    {
        if ($row === null) {
            return [
                'name' => $name,
                'status' => $configured ? 'WARNING' : 'UNCONFIGURED',
                'detail' => $configured ? 'Configured, not tested' : 'Not configured',
            ];
        }

        return [
            'name' => $name,
            'status' => (string) $row['health_status'],
            'detail' => (string) ($row['last_error'] ?? ($row['last_success_at'] ?? 'No successful operation yet')),
        ];
    }
}
