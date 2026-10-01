<?php

declare(strict_types=1);

/**
 * Web front controller.
 *
 * Every page request that is not a real file (css, js, image) arrives here.
 * Bootstrap starts the session and the database helper. routes.php decides
 * which controller runs.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Helpers\Router();
require base_path('app/routes.php');
$router->dispatch();
