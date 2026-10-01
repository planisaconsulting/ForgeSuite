<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PortalRepository;

/**
 * Counts attempts in a time window. Used by portal login, magic links, and approvals.
 */
final class RateLimiter
{
    public function __construct(private readonly PortalRepository $portal = new PortalRepository())
    {
    }

    public function allow(string $bucket, int $max, int $windowSeconds): bool
    {
        $now = time();
        $row = $this->portal->rate($bucket);
        if ($row === null || strtotime($row['window_start']) < $now - $windowSeconds) {
            $this->portal->saveRate($bucket, 1, date('Y-m-d H:i:s', $now));

            return true;
        }
        if ($row['attempts'] >= $max) {
            return false;
        }
        $this->portal->saveRate($bucket, $row['attempts'] + 1, $row['window_start']);

        return true;
    }
}
