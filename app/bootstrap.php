<?php

declare(strict_types=1);

/**
 * Starts one web request or one command-line script.
 *
 * What it does: loads configuration, the class autoloader, error logging,
 * the timezone, and the session.
 * Why it exists: every entry point (public/index.php, database/install.php,
 * tests) should boot the same way.
 * What it does not do: it does not open the database. That happens on the
 * first query, so the sign-in page can still render if MySQL is down.
 */

define('SF_ROOT', dirname(__DIR__));

require SF_ROOT . '/app/helpers/functions.php';

$GLOBALS['sf_config'] = load_config();

date_default_timezone_set((string) config('app.timezone', 'Africa/Johannesburg'));

ini_set('log_errors', '1');
ini_set('error_log', SF_ROOT . '/storage/logs/app.log');

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $className = array_pop($parts);
    $directory = strtolower(implode('/', $parts));
    $path = SF_ROOT . '/app/' . ($directory !== '' ? $directory . '/' : '') . $className . '.php';

    if (is_file($path)) {
        require $path;
    }
});

set_exception_handler(static function (Throwable $e): void {
    error_log($e->__toString());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    $debug = (bool) config('app.debug');
    require SF_ROOT . '/app/views/errors/500.php';
    exit;
});

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('SFSESSID');
    // Keep the session file long enough for "Remember me". The browser cookie
    // still expires when the browser closes unless that switch was used.
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => url('/') === '/' ? '/' : url('/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
    refresh_remember_me();
}
