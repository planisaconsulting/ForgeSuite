<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Widgets are a fixed list. A saved layout cannot contain a query.
 */
final class DashboardLayoutService
{
    /** @var list<string> */
    public const WIDGETS = [
        'my_follow_ups',
        'jobs_due_today',
        'quotes_awaiting',
        'production_blocked',
        'debtors',
        'stock_shortages',
        'approvals',
    ];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @param list<string> $widgets
     * @return list<string>
     */
    public function save(?int $roleId, ?int $userId, array $widgets): array
    {
        $clean = [];
        foreach ($widgets as $widget) {
            if (in_array($widget, self::WIDGETS, true) && !in_array($widget, $clean, true)) {
                $clean[] = $widget;
            }
        }
        if ($clean === []) {
            $clean = ['approvals', 'my_follow_ups'];
        }
        $this->platform->saveLayout($roleId, $userId, $clean);

        return $clean;
    }

    /**
     * @return list<string>
     */
    public function forUser(?int $roleId, ?int $userId): array
    {
        $saved = $this->platform->layoutFor($roleId, $userId);
        $clean = array_values(array_filter($saved, static fn (string $widget): bool => in_array($widget, self::WIDGETS, true)));

        return $clean === [] ? ['approvals', 'my_follow_ups', 'jobs_due_today'] : $clean;
    }
}
