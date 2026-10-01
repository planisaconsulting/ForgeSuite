<?php

declare(strict_types=1);

/**
 * One API call. Prints STATUS and the JSON body.
 *
 *   php tests/phase12_api.php <token> <path> <id>
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$_SERVER['HTTP_AUTHORIZATION'] = (string) ($argv[1] ?? '');
$_SERVER['REQUEST_URI'] = (string) ($argv[2] ?? '/api/v1/customers/1');
$_SERVER['REQUEST_METHOD'] = 'GET';

register_shutdown_function(static function (): void {
    fwrite(STDERR, 'STATUS ' . http_response_code() . "\n");
});

(new App\Controllers\ApiV1Controller())->customer((string) ($argv[3] ?? '0'));
