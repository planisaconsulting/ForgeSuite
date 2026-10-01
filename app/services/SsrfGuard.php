<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Outbound webhook addresses must be public HTTPS unless an administrator
 * has explicitly allowed private destinations.
 */
final class SsrfGuard
{
    public static function allows(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, 'test://')) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return self::privateOverride();
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (self::privateOverride()) {
            return true;
        }
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        return $public !== false;
    }

    private static function privateOverride(): bool
    {
        return SettingsService::get('webhook_allow_private_destinations', '0') === '1';
    }
}
