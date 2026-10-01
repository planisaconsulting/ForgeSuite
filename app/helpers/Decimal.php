<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Decimal strings for money and measurements.
 *
 * Nothing in here uses float. bcmath is used when the host has it. Otherwise
 * the same operations run through IntMath. Tests exercise both paths.
 *
 * Internal working scale is 8 decimal places. Round to cents or to a quantity
 * only at the point you display or store a finished figure.
 */
final class Decimal
{
    public const CALC_SCALE = 8;

    private static ?bool $bcmath = null;

    public static function useBcmath(?bool $enabled): void
    {
        self::$bcmath = $enabled;
    }

    public static function isBcmath(): bool
    {
        if (self::$bcmath === null) {
            self::$bcmath = extension_loaded('bcmath');
        }

        return self::$bcmath;
    }

    public static function isNumeric(string $value): bool
    {
        return preg_match('/^[+-]?(?:\d+)(?:\.\d+)?$/', trim($value)) === 1;
    }

    public static function add(string $a, string $b, int $scale = self::CALC_SCALE): string
    {
        return self::finish(self::apply('add', $a, $b), $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::CALC_SCALE): string
    {
        return self::finish(self::apply('sub', $a, $b), $scale);
    }

    public static function mul(string $a, string $b, int $scale = self::CALC_SCALE): string
    {
        return self::finish(self::apply('mul', $a, $b), $scale);
    }

    public static function div(string $a, string $b, int $scale = self::CALC_SCALE): string
    {
        self::assertNumeric($a);
        self::assertNumeric($b);
        if (self::cmp(self::canonical($b), '0') === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        if (self::isBcmath()) {
            $raw = bcdiv(self::canonical($a), self::canonical($b), $scale + 2);

            return self::round($raw, $scale);
        }

        $work = self::CALC_SCALE + 2;
        $left = self::toScaled($a, $work);
        $right = self::toScaled($b, $work);
        $divisor = self::pow10($work);
        $numerator = IntMath::mul($left, $divisor);
        [$quot, $rem] = IntMath::div($numerator, $right);
        $quot = self::roundAway($quot, $rem, $divisor, $numerator);

        return self::round(self::fromScaled($quot, $work, $work), $scale);
    }

    /** -1, 0, or 1. Comparison is exact at the working scale, not a float. */
    public static function cmp(string $a, string $b): int
    {
        self::assertNumeric($a);
        self::assertNumeric($b);
        if (self::isBcmath()) {
            return bccomp(self::canonical($a), self::canonical($b), self::CALC_SCALE + 2);
        }

        return IntMath::cmp(self::toScaled($a, self::CALC_SCALE + 2), self::toScaled($b, self::CALC_SCALE + 2));
    }

    /** Half away from zero. 1.005 to two places is 1.01. 1.004 is 1.00. */
    public static function round(string $value, int $scale): string
    {
        self::assertNumeric($value);
        if ($scale < 0) {
            throw new InvalidArgumentException('Scale cannot be negative.');
        }

        if (self::isBcmath()) {
            $negative = str_starts_with(self::canonical($value), '-');
            $abs = ltrim(self::canonical($value), '-');
            $bump = $scale === 0 ? '0.5' : '0.' . str_repeat('0', $scale) . '5';
            $adjusted = bcadd($abs, $bump, $scale + 1);
            $rounded = bcadd($adjusted, '0', $scale);

            return self::pad(self::withSign($negative, $rounded), $scale);
        }

        $scaled = self::toScaled($value, $scale);

        return self::pad(self::fromScaled($scaled, $scale, $scale), $scale);
    }

    /** Drop extra digits toward zero. Used for "how many whole sheets fit". */
    public static function truncate(string $value, int $scale): string
    {
        self::assertNumeric($value);
        $canonical = self::canonical($value);
        $negative = str_starts_with($canonical, '-');
        $abs = ltrim($canonical, '-');
        [$whole, $fraction] = array_pad(explode('.', $abs, 2), 2, '');
        $fraction = substr($fraction, 0, $scale);
        $out = $scale === 0 ? $whole : $whole . '.' . str_pad($fraction, $scale, '0');

        return self::withSign($negative, $out);
    }

    public static function ceil(string $value): string
    {
        $towardZero = self::truncate($value, 0);
        if (self::cmp($value, $towardZero) === 0) {
            return $towardZero;
        }
        if (self::cmp($value, '0') > 0) {
            return self::add($towardZero, '1', 0);
        }

        return $towardZero;
    }

    /** Positive integer division, toward zero. Sheet grids use this. */
    public static function floorDiv(string $a, string $b): string
    {
        if (self::cmp($a, '0') < 0 || self::cmp($b, '0') <= 0) {
            throw new InvalidArgumentException('floorDiv expects a positive divisor and a non-negative dividend.');
        }

        if (self::isBcmath()) {
            return self::truncate(bcdiv(self::canonical($a), self::canonical($b), 0), 0);
        }

        $work = self::CALC_SCALE;
        [$quot] = IntMath::div(self::toScaled($a, $work), self::toScaled($b, $work));

        return self::fromScaled($quot, 0, 0);
    }

    public static function money(string $value): string
    {
        return self::round($value, 2);
    }

    public static function qty(string $value): string
    {
        return self::round($value, 4);
    }

    private static function apply(string $op, string $a, string $b): string
    {
        self::assertNumeric($a);
        self::assertNumeric($b);
        if (self::isBcmath()) {
            $left = self::canonical($a);
            $right = self::canonical($b);
            $raw = match ($op) {
                'add' => bcadd($left, $right, self::CALC_SCALE + 2),
                'sub' => bcsub($left, $right, self::CALC_SCALE + 2),
                'mul' => bcmul($left, $right, self::CALC_SCALE + 2),
                default => throw new InvalidArgumentException('Unknown operation.'),
            };

            return $raw;
        }

        $left = self::toScaled($a, self::CALC_SCALE + 2);
        $right = self::toScaled($b, self::CALC_SCALE + 2);
        $scale = self::CALC_SCALE + 2;
        $raw = match ($op) {
            'add' => IntMath::add($left, $right),
            'sub' => IntMath::sub($left, $right),
            'mul' => self::mulScaled($left, $right, $scale),
            default => throw new InvalidArgumentException('Unknown operation.'),
        };

        return self::fromScaled($raw, $scale, $scale);
    }

    private static function mulScaled(string $left, string $right, int $scale): string
    {
        $product = IntMath::mul($left, $right);
        $divisor = self::pow10($scale);
        [$quot, $rem] = IntMath::div($product, $divisor);

        return self::roundAway($quot, $rem, $divisor, $product);
    }

    /**
     * Half away from zero on an integer division. $dividend is only used to
     * see the sign when the quotient is still zero.
     */
    private static function roundAway(string $quot, string $rem, string $divisor, string $dividend): string
    {
        $remAbs = ltrim($rem, '+-');
        if ($remAbs === '' || $remAbs === '0') {
            return $quot;
        }
        $twice = IntMath::mul($remAbs, '2');
        if (IntMath::cmpAbs($twice, ltrim($divisor, '+-')) < 0) {
            return $quot;
        }
        $negative = str_starts_with($dividend, '-');

        return IntMath::add($quot, $negative ? '-1' : '1');
    }

    private static function finish(string $raw, int $scale): string
    {
        return self::round($raw, $scale);
    }

    /** Value × 10^scale, rounded half away from zero, as an integer string. */
    private static function toScaled(string $value, int $scale): string
    {
        $canonical = self::canonical($value);
        $negative = str_starts_with($canonical, '-');
        $abs = ltrim($canonical, '-');
        [$whole, $fraction] = array_pad(explode('.', $abs, 2), 2, '');
        $whole = ltrim($whole, '0');
        $digits = ($whole === '' ? '' : $whole) . $fraction;
        $have = strlen($fraction);
        if ($have > $scale) {
            $keep = strlen($digits) - ($have - $scale);
            $kept = $keep > 0 ? substr($digits, 0, $keep) : '0';
            $next = $digits[$keep] ?? '0';
            if ($next >= '5') {
                $kept = IntMath::addAbs($kept === '' ? '0' : $kept, '1');
            }
            $digits = $kept;
        } else {
            $digits .= str_repeat('0', $scale - $have);
        }
        $digits = ltrim($digits, '0') ?: '0';

        return self::withSign($negative, $digits);
    }

    private static function fromScaled(string $scaled, int $fromScale, int $toScale): string
    {
        [$negative, $digits] = IntMath::norm($scaled);
        if ($toScale > $fromScale) {
            $digits .= str_repeat('0', $toScale - $fromScale);
            $fromScale = $toScale;
        }
        if ($toScale < $fromScale) {
            $drop = $fromScale - $toScale;
            if (strlen($digits) <= $drop) {
                $dropped = str_pad($digits, $drop, '0', STR_PAD_LEFT);
                $kept = '0';
            } else {
                $kept = substr($digits, 0, -$drop);
                $dropped = substr($digits, -$drop);
            }
            if ($dropped !== '' && $dropped[0] >= '5') {
                $kept = IntMath::addAbs($kept, '1');
            }
            $digits = $kept;
        }

        return self::pad(self::withSign($negative, self::placePoint($digits, $toScale)), $toScale);
    }

    private static function placePoint(string $digits, int $scale): string
    {
        $digits = ltrim($digits, '0') ?: '0';
        if ($scale === 0) {
            return $digits;
        }
        if (strlen($digits) <= $scale) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        }

        return substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
    }

    private static function pad(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $abs = ltrim($value, '-');
        if (!str_contains($abs, '.')) {
            $abs .= $scale > 0 ? '.' . str_repeat('0', $scale) : '';
        } else {
            [$whole, $fraction] = explode('.', $abs, 2);
            $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
            $abs = $scale === 0 ? $whole : $whole . '.' . $fraction;
        }
        if ($abs === '') {
            $abs = '0';
        }

        return self::withSign($negative && $abs !== '0' && !preg_match('/^0(?:\.0+)?$/', $abs), $abs);
    }

    private static function withSign(bool $negative, string $digits): string
    {
        if (!$negative || $digits === '0' || preg_match('/^0(?:\.0+)?$/', $digits) === 1) {
            return $digits;
        }
        if (str_starts_with($digits, '-')) {
            return $digits;
        }

        return '-' . $digits;
    }

    private static function canonical(string $value): string
    {
        self::assertNumeric($value);
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, null);
        $whole = ltrim((string) $whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        $out = $fraction === null || $fraction === '' ? $whole : $whole . '.' . $fraction;

        return self::withSign($negative, $out);
    }

    private static function pow10(int $scale): string
    {
        return '1' . str_repeat('0', $scale);
    }

    private static function assertNumeric(string $value): void
    {
        if (!self::isNumeric($value)) {
            throw new InvalidArgumentException('Not a decimal number.');
        }
    }
}
