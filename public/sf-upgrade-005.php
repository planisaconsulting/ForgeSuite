<?php

declare(strict_types=1);

/**
 * One-shot Phase 5 migration. Deletes itself after a successful run.
 * It does not drop existing tables and it does not change passwords.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$host = (string) config('db.host');
$user = (string) config('db.user');
$pass = (string) config('db.pass');
$name = (string) config('db.name');
$port = (int) config('db.port', 3306);
$sqlPath = dirname(__DIR__) . '/database/migrations/005_finance.sql';
$sql = file_get_contents($sqlPath);
if ($sql === false) {
    http_response_code(500);
    echo 'Migration file missing.';
    exit;
}

$mysqli = new mysqli($host, $user, $pass, $name, $port);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}
$mysqli->set_charset('utf8mb4');
if (!$mysqli->multi_query($sql)) {
    http_response_code(500);
    echo 'Migration failed to start.';
    exit;
}
do {
    $result = $mysqli->store_result();
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    if ($mysqli->errno) {
        http_response_code(500);
        echo 'Migration stopped.';
        exit;
    }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno) {
    http_response_code(500);
    echo 'Migration stopped.';
    exit;
}

$check = $mysqli->query("SHOW TABLES LIKE 'invoices'");
if (!$check instanceof mysqli_result || $check->num_rows < 1) {
    http_response_code(500);
    echo 'Invoices table was not created.';
    exit;
}
$check->free();
$mysqli->close();

@unlink(__FILE__);
echo 'Phase 5 finance migration applied.';
