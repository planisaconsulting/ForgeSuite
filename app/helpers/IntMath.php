<?php

declare(strict_types=1);

namespace App\Helpers;

use DivisionByZeroError;
use InvalidArgumentException;

/**
 * Integer arithmetic on decimal strings.
 *
 * PHP integers overflow past 19 digits. Currency and area math can need more
 * once a value is scaled to eight decimal places, so this class never casts
 * to int or float.
 */
final class IntMath
{
    /**
     * @return array{0: bool, 1: string} Negative flag, and digits without a sign.
     */
    public static function norm(string $n): array
    {
        $n = trim($n);
        if ($n === '' || !preg_match('/^[+-]?\d+$/', $n)) {
            throw new InvalidArgumentException('Not an integer string.');
        }

        $neg = str_starts_with($n, '-');
        $digits = ltrim($n, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return [false, '0'];
        }

        return [$neg, $digits];
    }

    public static function cmp(string $a, string $b): int
    {
        [$an, $ad] = self::norm($a);
        [$bn, $bd] = self::norm($b);
        if ($ad === '0' && $bd === '0') {
            return 0;
        }
        if ($an !== $bn) {
            return $an ? -1 : 1;
        }
        $cmp = self::cmpAbs($ad, $bd);

        return $an ? -$cmp : $cmp;
    }

    public static function cmpAbs(string $a, string $b): int
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        if (strlen($a) !== strlen($b)) {
            return strlen($a) <=> strlen($b);
        }

        return $a <=> $b;
    }

    public static function add(string $a, string $b): string
    {
        [$an, $ad] = self::norm($a);
        [$bn, $bd] = self::norm($b);

        if ($an === $bn) {
            $sum = self::addAbs($ad, $bd);

            return self::signed($an, $sum);
        }

        if (self::cmpAbs($ad, $bd) >= 0) {
            return self::signed($an, self::subAbs($ad, $bd));
        }

        return self::signed($bn, self::subAbs($bd, $ad));
    }

    public static function sub(string $a, string $b): string
    {
        [$bn, $bd] = self::norm($b);
        if ($bd === '0') {
            return self::add($a, '0');
        }

        return self::add($a, ($bn ? '' : '-') . $bd);
    }

    public static function addAbs(string $a, string $b): string
    {
        $a = strrev(ltrim($a, '0') ?: '0');
        $b = strrev(ltrim($b, '0') ?: '0');
        $len = max(strlen($a), strlen($b));
        $carry = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $digit = $carry + (int) ($a[$i] ?? '0') + (int) ($b[$i] ?? '0');
            $out .= (string) ($digit % 10);
            $carry = intdiv($digit, 10);
        }
        if ($carry > 0) {
            $out .= (string) $carry;
        }

        return strrev($out);
    }

    /** $a must be greater than or equal to $b. Both are unsigned. */
    public static function subAbs(string $a, string $b): string
    {
        $a = strrev(ltrim($a, '0') ?: '0');
        $b = strrev(ltrim($b, '0') ?: '0');
        $borrow = 0;
        $out = '';
        $len = strlen($a);
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $a[$i] - $borrow - (int) ($b[$i] ?? '0');
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out .= (string) $digit;
        }

        return ltrim(strrev($out), '0') ?: '0';
    }

    public static function mul(string $a, string $b): string
    {
        [$an, $ad] = self::norm($a);
        [$bn, $bd] = self::norm($b);
        if ($ad === '0' || $bd === '0') {
            return '0';
        }

        $n = strlen($ad);
        $m = strlen($bd);
        $result = array_fill(0, $n + $m, 0);
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $result[$i + $j + 1] += (int) $ad[$i] * (int) $bd[$j];
            }
        }
        for ($k = $n + $m - 1; $k > 0; $k--) {
            $result[$k - 1] += intdiv($result[$k], 10);
            $result[$k] %= 10;
        }

        $digits = ltrim(implode('', $result), '0');

        return self::signed($an !== $bn, $digits === '' ? '0' : $digits);
    }

    /**
     * Integer division toward zero.
     *
     * @return array{0: string, 1: string} Quotient and remainder. Remainder
     *         keeps the sign of the dividend, matching PHP intdiv remainder
     *         only in that the quotient is toward zero.
     */
    public static function div(string $a, string $b): array
    {
        [$an, $ad] = self::norm($a);
        [$bn, $bd] = self::norm($b);
        if ($bd === '0') {
            throw new DivisionByZeroError('Division by zero.');
        }
        if ($ad === '0') {
            return ['0', '0'];
        }

        [$quot, $rem] = self::divAbs($ad, $bd);

        return [
            self::signed($an !== $bn, $quot),
            self::signed($an, $rem),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function divAbs(string $a, string $b): array
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        if ($b === '0') {
            throw new DivisionByZeroError('Division by zero.');
        }
        if (self::cmpAbs($a, $b) < 0) {
            return ['0', $a];
        }

        $quot = '';
        $rem = '0';
        $length = strlen($a);
        for ($i = 0; $i < $length; $i++) {
            $rem = ltrim($rem, '0') . $a[$i];
            $rem = ltrim($rem, '0') ?: '0';
            $count = 0;
            while (self::cmpAbs($rem, $b) >= 0) {
                $rem = self::subAbs($rem, $b);
                $count++;
            }
            $quot .= (string) $count;
        }

        return [ltrim($quot, '0') ?: '0', $rem];
    }

    private static function signed(bool $negative, string $digits): string
    {
        $digits = ltrim($digits, '0') ?: '0';
        if ($digits === '0' || !$negative) {
            return $digits;
        }

        return '-' . $digits;
    }
}
