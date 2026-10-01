<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Units the pricing engine is allowed to mix.
 *
 * Sign sizes are entered in millimetres. Area math converts to square metres.
 * Linear math converts to metres. A product stores the unit its cost is for,
 * and that unit is chosen from the pricing method so a vinyl cost cannot
 * silently be treated as a per-sheet cost.
 */
final class Units
{
    public const MM = 'mm';
    public const M = 'm';
    public const M2 = 'm2';
    public const UNIT = 'unit';
    public const SHEET = 'sheet';
    public const LITRE = 'litre';
    public const HOUR = 'hour';

    public static function forMethod(string $method): string
    {
        return match ($method) {
            'AREA' => self::M2,
            'LINEAR_METRE' => self::M,
            'UNIT', 'CUSTOM' => self::UNIT,
            'SHEET' => self::SHEET,
            'LITRE' => self::LITRE,
            'HOUR' => self::HOUR,
            default => self::UNIT,
        };
    }

    public static function label(string $unit): string
    {
        return match ($unit) {
            self::MM => 'mm',
            self::M => 'm',
            self::M2 => 'm²',
            self::UNIT => 'unit',
            self::SHEET => 'sheet',
            self::LITRE => 'litre',
            self::HOUR => 'hour',
            default => $unit,
        };
    }
}
