<?php

declare(strict_types=1);

/**
 * Direct URL check for cash visibility.
 *
 *   php tests/phase12_security.php <userId>
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$_SESSION['user_id'] = (int) ($argv[1] ?? 0);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/planning/cash';

$router = new App\Helpers\Router();
require base_path('app/routes.php');
$router->dispatch();
