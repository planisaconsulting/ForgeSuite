<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Shared unit conversion and trace rows for signage estimators.
 * Dimensions are stored in millimetres. Area is square metres. Length is metres. Power is watts.
 */
final class SignMath
{
    /**
     * @return array{label: string, method: string, detail: string, result: string}
     */
    public static function trace(string $label, string $method, string $detail, string $result): array
    {
        return [
            'label' => $label,
            'method' => $method,
            'detail' => $detail,
            'result' => $result,
        ];
    }

    public static function yes(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    public static function toMm(string $value, string $unit): string
    {
        $unit = strtolower(trim($unit));
        $text = str_replace(',', '.', trim($value));
        if ($text === '' || !Decimal::isNumeric($text)) {
            throw new \InvalidArgumentException('A dimension must be a number.');
        }
        if (Decimal::cmp($text, '0') < 0) {
            throw new \InvalidArgumentException('A dimension cannot be negative.');
        }
        if ($unit === 'mm') {
            return $text;
        }
        if ($unit === 'cm') {
            return Decimal::mul($text, '10', Decimal::CALC_SCALE);
        }
        if ($unit === 'm') {
            return Decimal::mul($text, '1000', Decimal::CALC_SCALE);
        }

        throw new \InvalidArgumentException('Use mm, cm, or m.');
    }

    public static function areaM2(string $widthMm, string $heightMm): string
    {
        return Decimal::div(Decimal::mul($widthMm, $heightMm, Decimal::CALC_SCALE), '1000000', Decimal::CALC_SCALE);
    }

    public static function rectPerimeterMm(string $widthMm, string $heightMm): string
    {
        return Decimal::mul(Decimal::add($widthMm, $heightMm, Decimal::CALC_SCALE), '2', Decimal::CALC_SCALE);
    }

    public static function mmToM(string $mm): string
    {
        return Decimal::div($mm, '1000', Decimal::CALC_SCALE);
    }

    public static function ceilWhole(string $value): string
    {
        $bits = explode('.', Decimal::round($value, 4), 2);
        $whole = $bits[0] === '' || $bits[0] === '-' ? '0' : $bits[0];
        if (Decimal::cmp($value, $whole) > 0) {
            return Decimal::add($whole, '1', 0);
        }

        return $whole;
    }

    public static function quantity(string $value): string
    {
        $text = str_replace(',', '.', trim($value));
        if ($text === '' || !Decimal::isNumeric($text) || Decimal::cmp($text, '0') <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    public static function blank(string $type): array
    {
        return [
            'estimator_type' => $type,
            'geometry' => [],
            'materials' => [],
            'components' => [],
            'labour' => [],
            'machines' => [],
            'yield' => [],
            'warnings' => [],
            'reviews' => ['engineering' => false, 'electrical' => false, 'technical' => false],
            'trace' => [],
            'route' => [],
            'bom_source' => 'ESTIMATOR',
            'schematic' => '',
            'roll_options' => [],
            'outside' => false,
            'weight_kg' => null,
            'installation' => [],
            'blocked' => null,
            'customer_text' => '',
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    public static function formulaVariables(array $source): array
    {
        $allowed = ['W', 'H', 'D', 'Q', 'AREA', 'PERIMETER', 'FACE_AREA', 'RETURN_DEPTH', 'VECTOR_AREA', 'VECTOR_PERIMETER', 'SIDES', 'MODULE_WATTS'];
        $out = [];
        foreach ($allowed as $name) {
            $raw = $source[$name] ?? $source[strtolower($name)] ?? '0';
            $text = str_replace(',', '.', trim((string) $raw));
            $out[$name] = Decimal::isNumeric($text) ? $text : '0';
        }

        return $out;
    }
}
