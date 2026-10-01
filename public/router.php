<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in development server only.
 *
 * Apache on Xneelo uses public/.htaccess and does not load this file.
 *
 *   php -S 127.0.0.1:8741 -t public public/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
