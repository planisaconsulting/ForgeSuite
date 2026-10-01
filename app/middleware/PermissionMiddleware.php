<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Stops a signed-in user whose role does not include the route permission.
 */
final class PermissionMiddleware
{
    public static function handle(string $permission): void
    {
        if (!can($permission)) {
            deny_access('Your role cannot open that page.');
        }
    }
}
