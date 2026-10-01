<?php

declare(strict_types=1);

/**
 * Direct estimate URL check.
 *
 *   php tests/phase9_http.php <userId>
 */

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/estimates';
$_POST = [];

require dirname(__DIR__) . '/app/bootstrap.php';

$_SESSION['user_id'] = (int) ($argv[1] ?? 0);
$router = new App\Helpers\Router();
require dirname(__DIR__) . '/app/routes.php';
$router->dispatch();
