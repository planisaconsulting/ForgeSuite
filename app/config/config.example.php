<?php

declare(strict_types=1);

/**
 * Sample configuration.
 *
 * Copy this file to config.local.php and fill in the database password.
 * config.local.php is not committed. On shared hosting you can instead set
 * the SF_* environment variables listed in app/bootstrap.php.
 *
 * app.base_path stays empty when the site is served from the domain root
 * (document root = the public folder). Set it to something like /signforge
 * only when the whole public folder is mounted in a subdirectory.
 */

return [
    'app' => [
        'name' => 'Sign-Forge Management System',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://quotes.example.co.za',
        'base_path' => '',
        'timezone' => 'Africa/Johannesburg',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'signforge',
        'user' => 'signforge',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
