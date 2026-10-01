<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Staff cost visibility and portal ownership. A portal user is not a staff user.
 */
final class AssetAccess
{
    public static function canSeeCosts(): bool
    {
        return can('assets.view_costs') || can('service.view_costs');
    }

    /**
     * @param array<string, mixed> $asset
     */
    public static function portalOwns(int $customerId, array $asset): bool
    {
        return $customerId > 0 && (int) $asset['customer_id'] === $customerId;
    }
}
