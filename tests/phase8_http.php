<?php

declare(strict_types=1);

/**
 * Direct schedule URL check for one user.
 *
 *   php tests/phase8_http.php <userId>
 */

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/schedule/entries';
$_POST = [];

require dirname(__DIR__) . '/app/bootstrap.php';

$_SESSION['user_id'] = (int) ($argv[1] ?? 0);
$router = new App\Helpers\Router();
require dirname(__DIR__) . '/app/routes.php';
$router->dispatch();
