<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Counts and short lists for the home screen.
 * Quotes, jobs, invoices, and stock are not queried because those tables
 * do not exist yet. The dashboard says so in plain text instead of showing 0.
 */
final class DashboardRepository extends Repository
{
    public function count(string $table, bool $activeOnly = true): int
    {
        $allowed = ['customers', 'products', 'suppliers', 'pricing_levels'];
        if (!in_array($table, $allowed, true)) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) AS n FROM ' . $table;
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $row = $this->one($sql);

        return (int) ($row['n'] ?? 0);
    }
}
