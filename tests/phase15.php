<?php

declare(strict_types=1);

/**
 * Release gates for Sign-Forge ERP v1.0.0.
 *
 *   php tests/phase15.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\PlatformRepository;
use App\Services\RuntimeService;
use App\Version;

$failures = 0;
$eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
    if ($expected === $actual) {
        fwrite(STDOUT, "ok   {$label}\n");
        return;
    }
    fwrite(STDERR, "FAIL {$label}\n");
    $failures++;
};

$eq('release name', 'Sign-Forge ERP', Version::NAME);
$eq('release number', '1.0.0', Version::NUMBER);
$eq('login index', 1, (new PlatformRepository())->countIndex('idx_login_events_ip_created'));

$schema = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$eq('schema avoids float columns', false, (bool) preg_match('/\b(FLOAT|DOUBLE)\b/i', preg_replace('/--.*Do not use FLOAT\./', '', $schema)));
$eq('money uses decimal', true, str_contains($schema, 'DECIMAL(14,2)'));

$bootstrap = (string) file_get_contents(dirname(__DIR__) . '/app/bootstrap.php');
$eq('csp header', true, str_contains($bootstrap, 'Content-Security-Policy'));
$eq('httponly session', true, str_contains($bootstrap, "'httponly' => true"));
$eq('error reference', true, str_contains($bootstrap, 'ERR-'));
$errorPage = (string) file_get_contents(dirname(__DIR__) . '/app/views/errors/500.php');
$eq('error page hides sql by default', true, str_contains($errorPage, 'sf_error_id') && str_contains($errorPage, '$debug'));

$numbering = (string) file_get_contents(dirname(__DIR__) . '/app/repositories/NumberSequenceRepository.php');
$eq('numbers lock the counter', true, str_contains($numbering, 'FOR UPDATE'));

$auth = (string) file_get_contents(dirname(__DIR__) . '/app/services/AuthService.php');
$eq('password verify', true, str_contains($auth, 'password_verify'));
$eq('ip lock', true, str_contains($auth, 'ipLocked'));

$health = RuntimeService::publicHealth();
$eq('public health ok', 'ok', $health['status'] ?? null);
$eq('public health hides version', false, array_key_exists('php', $health) || array_key_exists('password', $health));

$started = microtime(true);
Database::connection()->query('SELECT id, company_name FROM customers ORDER BY id DESC LIMIT 25');
Database::connection()->query('SELECT id, quote_number, status, total FROM quotes ORDER BY id DESC LIMIT 25');
Database::connection()->query('SELECT id, job_number, status FROM jobs ORDER BY id DESC LIMIT 25');
$eq('list queries stay under two seconds', true, (microtime(true) - $started) < 2);

if ($failures > 0) {
    fwrite(STDERR, "phase15 {$failures} failed\n");
    exit(1);
}
fwrite(STDOUT, "phase15 ok\n");
