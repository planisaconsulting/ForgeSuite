<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;

/**
 * One shared PDO connection for the request.
 *
 * Prepared statements are required. ATTR_EMULATE_PREPARES is off so MySQL
 * receives real placeholders. Do not reuse the same named placeholder twice
 * in one SQL string; native prepares reject that.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) config('db.host');
        $port = (int) config('db.port', 3306);
        $name = (string) config('db.name');
        $charset = (string) config('db.charset', 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) config('db.user'),
                (string) config('db.pass'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            self::fail($e);
        }

        return self::$pdo;
    }

    public static function fail(PDOException $e): never
    {
        error_log('Database connection failed: ' . $e->getMessage());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed. Check app/config/config.local.php.\n");
            exit(1);
        }

        http_response_code(500);
        $message = $e->getMessage();
        if (!is_file(SF_ROOT . '/app/config/config.local.php')) {
            $reason = 'missing-config';
        } elseif (str_contains($message, '1045') || str_contains($message, 'Access denied')) {
            $reason = 'denied';
        } else {
            $reason = 'unreachable';
        }
        require base_path('app/views/errors/database.php');
        exit;
    }
}
