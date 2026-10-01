<?php

declare(strict_types=1);

/**
 * Creates the Sign-Forge tables and starter data.
 *
 * The empty database must already exist unless this database user is allowed
 * to create it. Shared hosting usually creates the database in the panel.
 *
 *   php database/install.php
 *   php database/install.php --force
 *
 * --force drops the Sign-Forge tables and loads them again. It deletes data.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$name = (string) config('db.name');
if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, "The database name may only contain letters, numbers, and underscores.\n");
    exit(1);
}

$mysqli = new mysqli(
    (string) config('db.host'),
    (string) config('db.user'),
    (string) config('db.pass'),
    '',
    (int) config('db.port', 3306)
);

if ($mysqli->connect_error) {
    fwrite(STDERR, 'Could not connect to MySQL: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}

$mysqli->set_charset('utf8mb4');

$create = sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $name
);

if (!$mysqli->query($create)) {
    fwrite(STDOUT, "Could not create the database ({$mysqli->error}). Using `{$name}` if it already exists." . PHP_EOL);
}

if (!$mysqli->select_db($name)) {
    fwrite(STDERR, "Database `{$name}` is not available. Create it, grant this user access, then run the installer again." . PHP_EOL);
    exit(1);
}

$force = in_array('--force', $argv, true);
$check = $mysqli->query("SHOW TABLES LIKE 'users'");
if ($check instanceof mysqli_result && $check->num_rows > 0 && !$force) {
    fwrite(STDERR, "Tables already exist. Run: php database/install.php --force" . PHP_EOL);
    fwrite(STDERR, "That drops the Sign-Forge tables and deletes their data." . PHP_EOL);
    exit(1);
}

/**
 * Runs a SQL file that contains more than one statement.
 * The schema and seed files do not use stored procedures, so splitting on
 * statements with multi_query is safe.
 */
$import = static function (mysqli $mysqli, string $file): void {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Cannot read ' . $file);
    }

    if (!$mysqli->multi_query($sql)) {
        throw new RuntimeException($mysqli->error);
    }

    do {
        $result = $mysqli->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        if ($mysqli->errno) {
            throw new RuntimeException($mysqli->error);
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->errno) {
        throw new RuntimeException($mysqli->error);
    }
};

try {
    $import($mysqli, base_path('database/schema.sql'));
    $import($mysqli, base_path('database/seed.sql'));
} catch (Throwable $e) {
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Sign-Forge database is ready." . PHP_EOL);
fwrite(STDOUT, "Sign in as admin@signforge.local and set a new password." . PHP_EOL);
