<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Shortcuts honour the same permissions as the pages they open.
 */
final class QuickActionService
{
    /**
     * @return list<array{label: string, href: string}>
     */
    public function actions(string $query = ''): array
    {
        $catalog = [
            ['New quote', '/quotes/new', 'quotes.create'],
            ['New customer', '/customers/new', 'customers.manage'],
            ['New expense', '/expenses', 'expenses.create'],
            ['New site survey', '/surveys', 'site_surveys.edit'],
            ['New service request', '/service', 'service_requests.create'],
            ['Search', '/search', 'dashboard.view'],
            ['Scan item', '/workshop/scan', 'workshop.scan'],
            ['New job', '/jobs', 'jobs.view'],
            ['Log payment', '/payments/new', 'payments.record'],
            ['Approvals', '/approvals', 'approvals.view'],
            ['Review queue', '/reviews', 'review_queue.view'],
        ];
        $query = strtolower(trim($query));
        $actions = [];
        foreach ($catalog as [$label, $href, $permission]) {
            if (!can($permission)) {
                continue;
            }
            if ($query !== '' && !str_contains(strtolower($label), $query) && !str_contains(strtolower($href), $query)) {
                continue;
            }
            $actions[] = ['label' => $label, 'href' => $href];
        }

        return $actions;
    }
}
