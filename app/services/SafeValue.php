<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Structured configuration only. Nothing here is executed.
 */
final class SafeValue
{
    /**
     * @return array<string, mixed>
     */
    public static function object(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                continue;
            }
            if (is_array($item)) {
                continue;
            }
            if (is_string($item) && self::executable($item)) {
                continue;
            }
            if (is_scalar($item) || $item === null) {
                $out[$key] = $item;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $allowed
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function only(array $allowed, array $config): array
    {
        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $config)) {
                $out[$key] = $config[$key];
            }
        }

        return $out;
    }

    public static function executable(string $value): bool
    {
        $lower = strtolower($value);

        return str_contains($lower, '<?php')
            || str_contains($lower, '<?=')
            || str_contains($lower, 'eval(')
            || str_contains($lower, 'shell_exec')
            || str_contains($lower, 'passthru')
            || str_contains($lower, 'drop table')
            || str_contains($lower, 'delete from')
            || str_contains($lower, 'insert into');
    }

    public static function summary(string $value, int $limit = 500): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }
}
