<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Stops a request that has no signed-in user.
 * The router calls this for every route marked as requiring auth.
 */
final class AuthMiddleware
{
    public static function handle(string $path): void
    {
        require_login($path);
    }
}
