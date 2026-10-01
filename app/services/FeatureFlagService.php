<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Flags turn a feature off without removing its code.
 */
final class FeatureFlagService
{
    /** @var list<string> */
    public const KEYS = [
        'AI_ASSISTANCE',
        'PAYMENT_LINKS',
        'ACCOUNTING_SYNC',
        'CUSTOM_FORMS',
        'ADVANCED_WORKFLOWS',
        'OFFLINE_FIELD_MODE',
    ];

    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function enabled(string $key): bool
    {
        $flag = $this->platform->flag($key);
        if ($flag === null) {
            return in_array($key, ['ADVANCED_WORKFLOWS', 'CUSTOM_FORMS'], true);
        }

        return (int) $flag['enabled'] === 1;
    }

    public function set(string $key, bool $enabled, int $userId): string
    {
        if (!can('feature_flags.manage')) {
            return 'You cannot change feature flags.';
        }
        if (!in_array($key, self::KEYS, true)) {
            return 'That feature is not configurable.';
        }
        $this->platform->saveFlag($key, $enabled ? 1 : 0, null, $userId);
        $this->audit->record('feature_flag', null, 'FEATURE_FLAG_CHANGED', null, [
            'feature_key' => $key,
            'enabled' => $enabled ? 1 : 0,
        ], $userId);

        return '';
    }
}
