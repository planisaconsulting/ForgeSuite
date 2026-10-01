<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;

/**
 * Key/value company settings.
 *
 * VAT, the quote prefix, and the currency symbol are read from here.
 * Do not hard-code them in controllers. The settings screen (next build)
 * will update these rows.
 */
final class Setting
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stmt = Database::connection()->prepare(
            'SELECT setting_key, setting_value FROM settings'
        );
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['setting_key']] = $row['setting_value'] === null
                ? null
                : (string) $row['setting_value'];
        }

        self::$cache = $map;

        return $map;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        if (!array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') {
            return $default;
        }

        return $all[$key];
    }
}
