<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Version;

/**
 * Production guards that are not business features.
 *
 * HTTPS is required only when the configured environment is production and
 * the request is not a local address. Maintenance mode lets an already
 * signed-in administrator through so the switch can be turned off.
 */
final class RuntimeService
{
    public static function enforceHttps(): void
    {
        if (PHP_SAPI === 'cli' || (string) config('app.env', 'local') !== 'production') {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($https || $forwarded === 'https') {
            return;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost')) {
            return;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: https://' . $host . $uri, true, 308);
        exit;
    }

    public static function enforceMaintenance(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        try {
            if (SettingsService::get('maintenance_mode', '0') !== '1') {
                return;
            }
        } catch (\Throwable) {
            return;
        }
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (in_array($path, ['/login', '/health', '/logout'], true)) {
            return;
        }
        $user = function_exists('auth_user') ? auth_user() : null;
        if (is_array($user) && (string) ($user['role_code'] ?? '') === 'ADMIN') {
            return;
        }
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 300');
        }
        require base_path('app/views/errors/maintenance.php');
        exit;
    }

    /**
     * Public status. No versions, paths, or credentials.
     *
     * @return array{status: string}
     */
    public static function publicHealth(): array
    {
        $database = false;
        try {
            Database::connection()->query('SELECT 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }
        $storage = is_writable(base_path('storage'));

        return ['status' => ($database && $storage) ? 'ok' : 'degraded'];
    }

    public static function releaseLabel(): string
    {
        return Version::NAME . ' v' . Version::NUMBER;
    }

    /** Short release label for the sidebar footer (e.g. v1.1). */
    public static function sidebarReleaseLabel(): string
    {
        $parts = explode('.', Version::NUMBER);
        if (count($parts) >= 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            return 'v' . $parts[0] . '.' . $parts[1];
        }

        return 'v' . Version::NUMBER;
    }
}
