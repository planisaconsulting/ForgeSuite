<?php

declare(strict_types=1);

namespace App\Services;

/**
 * QR symbol for an opaque asset token. Byte mode, error correction L, versions 1 to 5.
 * The symbol never contains a database id.
 */
final class QrSvg
{
    /** @var list<int>|null */
    private static ?array $exp = null;

    /** @var list<int>|null */
    private static ?array $log = null;

    public static function svg(string $payload, int $scale = 4): string
    {
        $matrix = self::matrix($payload);
        $size = count($matrix);
        $quiet = 4;
        $dim = ($size + $quiet * 2) * $scale;
        $rects = '';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$matrix[$y][$x]) {
                    continue;
                }
                $rects .= '<rect x="' . (($x + $quiet) * $scale) . '" y="' . (($y + $quiet) * $scale) . '" width="' . $scale . '" height="' . $scale . '"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" role="img"><rect width="100%" height="100%" fill="#fff"/>' . $rects . '</svg>';
    }

    /**
     * @return list<list<bool>>
     */
    public static function matrix(string $payload): array
    {
        $bytes = array_values(unpack('C*', $payload) ?: []);
        $version = self::versionFor(count($bytes));
        $size = 17 + $version * 4;
        $function = [];
        $modules = [];
        for ($y = 0; $y < $size; $y++) {
            $function[$y] = array_fill(0, $size, false);
            $modules[$y] = array_fill(0, $size, false);
        }
        self::finder($modules, $function, $size, 0, 0);
        self::finder($modules, $function, $size, $size - 7, 0);
        self::finder($modules, $function, $size, 0, $size - 7);
        for ($i = 0; $i < $size; $i++) {
            if (!$function[6][$i]) {
                $function[6][$i] = true;
                $modules[6][$i] = ($i % 2) === 0;
            }
            if (!$function[$i][6]) {
                $function[$i][6] = true;
                $modules[$i][6] = ($i % 2) === 0;
            }
        }
        $centers = [2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30]];
        foreach ($centers[$version] ?? [] as $cy) {
            foreach ($centers[$version] as $cx) {
                if ($function[$cy][$cx]) {
                    continue;
                }
                self::alignment($modules, $function, $cx, $cy);
            }
        }
        $function[$size - 8][8] = true;
        $modules[$size - 8][8] = true;
        $data = self::dataCodewords($bytes, $version);
        $eccLen = [1 => 7, 2 => 10, 3 => 15, 4 => 20, 5 => 26][$version];
        $ecc = self::remainder($data, $eccLen);
        $bits = '';
        foreach (array_merge($data, $ecc) as $word) {
            $bits .= str_pad(decbin($word), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7][$version]);
        $bitIndex = 0;
        $bitLength = strlen($bits);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if ($function[$y][$x]) {
                        continue;
                    }
                    $dark = $bitIndex < $bitLength && $bits[$bitIndex] === '1';
                    $bitIndex++;
                    if ((($y + $x) % 2) === 0) {
                        $dark = !$dark;
                    }
                    $modules[$y][$x] = $dark;
                    $function[$y][$x] = true;
                }
            }
        }
        self::format($modules, $size, 0);

        return $modules;
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private static function finder(array &$modules, array &$function, int $size, int $x, int $y): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $yy < 0 || $xx >= $size || $yy >= $size) {
                    continue;
                }
                $function[$yy][$xx] = true;
                $on = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                    && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $modules[$yy][$xx] = $on;
            }
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private static function alignment(array &$modules, array &$function, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $function[$cy + $dy][$cx + $dx] = true;
                $modules[$cy + $dy][$cx + $dx] = max(abs($dx), abs($dy)) !== 1;
            }
        }
    }

    /**
     * @param list<int> $bytes
     * @return list<int>
     */
    private static function dataCodewords(array $bytes, int $version): array
    {
        $capacity = [1 => 19, 2 => 34, 3 => 55, 4 => 80, 5 => 108][$version];
        $bits = '0100' . str_pad(decbin(count($bytes)), 8, '0', STR_PAD_LEFT);
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $capacityBits = $capacity * 8;
        $bits .= str_repeat('0', min(4, max(0, $capacityBits - strlen($bits))));
        while ((strlen($bits) % 8) !== 0) {
            $bits .= '0';
        }
        $pads = ['11101100', '00010001'];
        $pad = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= $pads[$pad % 2];
            $pad++;
        }
        $words = [];
        for ($i = 0; $i < $capacity; $i++) {
            $words[] = bindec(substr($bits, $i * 8, 8));
        }

        return $words;
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private static function remainder(array $data, int $eccLen): array
    {
        $gen = self::generator($eccLen);
        $result = array_merge($data, array_fill(0, $eccLen, 0));
        $dataLen = count($data);
        for ($i = 0; $i < $dataLen; $i++) {
            $factor = $result[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($gen as $j => $coef) {
                $result[$i + $j] ^= self::mul($coef, $factor);
            }
        }

        return array_slice($result, $dataLen);
    }

    /**
     * @return list<int>
     */
    private static function generator(int $degree): array
    {
        static $cache = [];
        if (isset($cache[$degree])) {
            return $cache[$degree];
        }
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $a = self::expValue($i);
            $out = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $k => $coef) {
                $out[$k] ^= $coef;
                $out[$k + 1] ^= self::mul($coef, $a);
            }
            $poly = $out;
        }
        $cache[$degree] = $poly;

        return $poly;
    }

    private static function versionFor(int $length): int
    {
        foreach ([1 => 17, 2 => 32, 3 => 53, 4 => 78, 5 => 106] as $version => $max) {
            if ($length <= $max) {
                return $version;
            }
        }

        throw new \InvalidArgumentException('That asset token is too long for a label.');
    }

    /**
     * @param list<list<bool>> $modules
     */
    private static function format(array &$modules, int $size, int $mask): void
    {
        $data = (1 << 3) | $mask;
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if ((($rem >> $i) & 1) !== 0) {
                $rem ^= 0x537 << ($i - 10);
            }
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $flags = [];
        for ($i = 14; $i >= 0; $i--) {
            $flags[] = (($bits >> $i) & 1) === 1;
        }
        $positionsA = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]];
        foreach ($positionsA as $i => [$x, $y]) {
            $modules[$y][$x] = $flags[$i];
        }
        for ($i = 0; $i < 7; $i++) {
            $modules[8][$size - 1 - $i] = $flags[$i];
        }
        for ($i = 0; $i < 8; $i++) {
            $modules[$size - 8 + $i][8] = $flags[7 + $i];
        }
    }

    private static function tables(): void
    {
        if (self::$exp !== null) {
            return;
        }
        $exp = [];
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function expValue(int $index): int
    {
        self::tables();

        return self::$exp[$index] ?? 0;
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        self::tables();

        return self::$exp[(self::$log[$a] ?? 0) + (self::$log[$b] ?? 0)] ?? 0;
    }
}
