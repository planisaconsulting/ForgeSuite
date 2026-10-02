<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Reads untrusted SVG path data for manufacturing estimates.
 *
 * Supported commands are M L H V C Q Z, in upper or lower case.
 * Arcs and smooth shorthand are refused rather than guessed.
 * Axis-aligned straight segments are exact. Other straight segments use a
 * square root. Curves are flattened to 16 segments, which is an estimate of
 * length and area, not a certified curve length.
 * User units are treated as millimetres. Script and event handlers are removed
 * and are never returned in the preview.
 */
final class SvgGeometry
{
    public const VERSION = '2';

    /**
     * @return array<string, mixed>
     */
    public static function analyse(string $svg): array
    {
        $limit = (int) SettingsService::get('geometry_max_bytes', '262144');
        if (strlen($svg) > max(1024, $limit)) {
            return self::stopped('This geometry file is larger than the configured limit.');
        }
        $warnings = [];
        if (preg_match('/<script|javascript:|\bon[a-z]+\s*=/i', $svg) === 1) {
            $warnings[] = 'Script or event handlers were removed. The file is treated as untrusted.';
        }
        $clean = self::strip($svg);
        if (preg_match('/<script|javascript:|\bon[a-z]+\s*=/i', $clean) === 1) {
            return self::stopped('MANUAL GEOMETRY REVIEW REQUIRED. The file still contained active content after cleaning.');
        }
        preg_match_all('/<path\b[^>]*\bd\s*=\s*(["\'])(.*?)\1/is', $clean, $found);
        $paths = $found[2] ?? [];
        $maxPaths = (int) SettingsService::get('geometry_max_paths', '200');
        if (count($paths) > max(1, $maxPaths)) {
            return self::stopped('This file has more paths than the configured limit.');
        }
        if ($paths === []) {
            return self::stopped('MANUAL GEOMETRY REVIEW REQUIRED. No path data was found. A raster image is not geometry.');
        }

        $hash = hash('sha256', self::VERSION . '|' . implode("\n", $paths));
        $cached = (new \App\Repositories\SignageRepository())->geometryCache($hash);
        if ($cached !== null) {
            $cached['warnings'] = array_values(array_unique(array_merge($warnings, $cached['warnings'] ?? [])));
            $cached['cache'] = 'HIT';

            return $cached;
        }

        $seen = [];
        $shapes = [];
        $closedArea = '0';
        $closedPerimeter = '0';
        $openCount = 0;
        $duplicateCount = 0;
        $minX = null;
        $minY = null;
        $maxX = null;
        $maxY = null;
        $preview = [];

        foreach ($paths as $d) {
            $key = preg_replace('/\s+/', ' ', trim($d)) ?? trim($d);
            if (isset($seen[$key])) {
                $duplicateCount++;
                $warnings[] = 'DUPLICATE PATH DETECTED. The repeated path was not added again.';
                continue;
            }
            $seen[$key] = true;
            try {
                $shape = self::path($d);
            } catch (\RuntimeException $e) {
                return self::stopped($e->getMessage());
            }
            if ($shape['open']) {
                $openCount++;
                $warnings[] = 'OPEN VECTOR PATH DETECTED.';
            } else {
                $closedArea = Decimal::add($closedArea, $shape['area_mm2'], Decimal::CALC_SCALE);
                $closedPerimeter = Decimal::add($closedPerimeter, $shape['perimeter_mm'], Decimal::CALC_SCALE);
            }
            foreach ([$shape['min_x'], $shape['max_x']] as $x) {
                $minX = $minX === null || Decimal::cmp($x, $minX) < 0 ? $x : $minX;
                $maxX = $maxX === null || Decimal::cmp($x, $maxX) > 0 ? $x : $maxX;
            }
            foreach ([$shape['min_y'], $shape['max_y']] as $y) {
                $minY = $minY === null || Decimal::cmp($y, $minY) < 0 ? $y : $minY;
                $maxY = $maxY === null || Decimal::cmp($y, $maxY) > 0 ? $y : $maxY;
            }
            $shapes[] = $shape;
            $preview[] = $shape['points'];
        }

        $result = [
            'ok' => true,
            'stopped' => null,
            'cache' => 'MISS',
            'version' => self::VERSION,
            'warnings' => array_values(array_unique($warnings)),
            'open_paths' => $openCount,
            'duplicate_paths' => $duplicateCount,
            'area_mm2' => Decimal::round($closedArea, 4),
            'perimeter_mm' => Decimal::round($closedPerimeter, 4),
            'area_m2' => Decimal::round(Decimal::div($closedArea, '1000000', Decimal::CALC_SCALE), 6),
            'perimeter_m' => Decimal::round(Decimal::div($closedPerimeter, '1000', Decimal::CALC_SCALE), 6),
            'bbox' => [
                'min_x' => $minX ?? '0',
                'min_y' => $minY ?? '0',
                'max_x' => $maxX ?? '0',
                'max_y' => $maxY ?? '0',
            ],
            'shapes' => count($shapes),
            'preview_svg' => self::preview($preview, $minX ?? '0', $minY ?? '0', $maxX ?? '1', $maxY ?? '1'),
            'sanitized' => true,
        ];
        (new \App\Repositories\SignageRepository())->storeGeometryCache($hash, $result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stopped(string $message): array
    {
        return [
            'ok' => false,
            'stopped' => $message,
            'cache' => 'MISS',
            'warnings' => [$message],
            'open_paths' => 0,
            'duplicate_paths' => 0,
            'area_mm2' => null,
            'perimeter_mm' => null,
            'area_m2' => null,
            'perimeter_m' => null,
            'preview_svg' => '',
            'shapes' => 0,
        ];
    }

    private static function strip(string $svg): string
    {
        $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/\s(on[a-z]+)\s*=\s*(["\']).*?\2/i', '', $svg) ?? $svg;
        $svg = preg_replace('/javascript\s*:/i', '', $svg) ?? $svg;

        return $svg;
    }

    /**
     * @return array{open: bool, area_mm2: string, perimeter_mm: string, min_x: string, min_y: string, max_x: string, max_y: string, points: list<array{0: string, 1: string}>}
     */
    private static function path(string $d): array
    {
        $tokens = self::tokens($d);
        $i = 0;
        $cx = '0';
        $cy = '0';
        $sx = '0';
        $sy = '0';
        $command = '';
        $points = [];
        $length = '0';
        $started = false;
        $closed = false;
        $count = count($tokens);

        $add = static function (string $x, string $y) use (&$points, &$length, &$cx, &$cy): void {
            if ($points !== []) {
                $last = $points[count($points) - 1];
                $length = Decimal::add($length, self::segment($last[0], $last[1], $x, $y), Decimal::CALC_SCALE);
            }
            $points[] = [$x, $y];
            $cx = $x;
            $cy = $y;
        };

        while ($i < $count) {
            $token = $tokens[$i];
            if (preg_match('/^[A-Za-z]$/', $token) === 1) {
                $command = $token;
                $i++;
            } elseif ($command === '') {
                throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. The path could not be read.');
            }
            $upper = strtoupper($command);
            if ($upper === 'A' || $upper === 'S' || $upper === 'T') {
                throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. Arcs and shorthand curves are not calculated.');
            }
            $relative = $command !== $upper;
            if ($upper === 'Z') {
                if ($points !== []) {
                    $length = Decimal::add($length, self::segment($cx, $cy, $sx, $sy), Decimal::CALC_SCALE);
                    $points[] = [$sx, $sy];
                    $cx = $sx;
                    $cy = $sy;
                }
                $closed = true;
                $command = '';
                continue;
            }
            if ($upper === 'M' || $upper === 'L') {
                if ($i + 1 >= $count) {
                    throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A line is missing coordinates.');
                }
                [$x, $y] = self::pair($tokens[$i], $tokens[$i + 1], $cx, $cy, $relative);
                $i += 2;
                if ($upper === 'M') {
                    $sx = $x;
                    $sy = $y;
                    $points[] = [$x, $y];
                    $cx = $x;
                    $cy = $y;
                    $started = true;
                    $command = $relative ? 'l' : 'L';
                } else {
                    $add($x, $y);
                }
                continue;
            }
            if ($upper === 'H') {
                if ($i >= $count) {
                    throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A horizontal line is incomplete.');
                }
                $x = $relative ? Decimal::add($cx, $tokens[$i], Decimal::CALC_SCALE) : $tokens[$i];
                $i++;
                $add($x, $cy);
                continue;
            }
            if ($upper === 'V') {
                if ($i >= $count) {
                    throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A vertical line is incomplete.');
                }
                $y = $relative ? Decimal::add($cy, $tokens[$i], Decimal::CALC_SCALE) : $tokens[$i];
                $i++;
                $add($cx, $y);
                continue;
            }
            if ($upper === 'C') {
                if ($i + 5 >= $count) {
                    throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A curve is incomplete.');
                }
                $p1 = self::pair($tokens[$i], $tokens[$i + 1], $cx, $cy, $relative);
                $p2 = self::pair($tokens[$i + 2], $tokens[$i + 3], $cx, $cy, $relative);
                $p3 = self::pair($tokens[$i + 4], $tokens[$i + 5], $cx, $cy, $relative);
                $i += 6;
                foreach (self::cubic([$cx, $cy], $p1, $p2, $p3) as $point) {
                    $add($point[0], $point[1]);
                }
                continue;
            }
            if ($upper === 'Q') {
                if ($i + 3 >= $count) {
                    throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A curve is incomplete.');
                }
                $p1 = self::pair($tokens[$i], $tokens[$i + 1], $cx, $cy, $relative);
                $p2 = self::pair($tokens[$i + 2], $tokens[$i + 3], $cx, $cy, $relative);
                $i += 4;
                foreach (self::quadratic([$cx, $cy], $p1, $p2) as $point) {
                    $add($point[0], $point[1]);
                }
                continue;
            }
            throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A path command is not supported.');
        }
        if (!$started || $points === []) {
            throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. The path has no points.');
        }
        $open = !$closed;
        $end = $points[count($points) - 1];
        if (!$closed && Decimal::cmp(self::segment($end[0], $end[1], $sx, $sy), '0.05') <= 0) {
            $open = false;
        }
        $area = $open ? '0' : self::shoelace($points);
        $minX = $points[0][0];
        $minY = $points[0][1];
        $maxX = $minX;
        $maxY = $minY;
        foreach ($points as $point) {
            if (Decimal::cmp($point[0], $minX) < 0) {
                $minX = $point[0];
            }
            if (Decimal::cmp($point[0], $maxX) > 0) {
                $maxX = $point[0];
            }
            if (Decimal::cmp($point[1], $minY) < 0) {
                $minY = $point[1];
            }
            if (Decimal::cmp($point[1], $maxY) > 0) {
                $maxY = $point[1];
            }
        }

        return [
            'open' => $open,
            'area_mm2' => Decimal::round($area, 4),
            'perimeter_mm' => Decimal::round($length, 4),
            'min_x' => $minX,
            'min_y' => $minY,
            'max_x' => $maxX,
            'max_y' => $maxY,
            'points' => $points,
        ];
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $d): array
    {
        $tokens = [];
        $len = strlen($d);
        $i = 0;
        while ($i < $len) {
            $c = $d[$i];
            if (ctype_space($c) || $c === ',') {
                $i++;
                continue;
            }
            if (ctype_alpha($c)) {
                $tokens[] = $c;
                $i++;
                continue;
            }
            if ($c === '-' || $c === '+' || $c === '.' || ctype_digit($c)) {
                $start = $i;
                if ($c === '-' || $c === '+') {
                    $i++;
                }
                $dot = false;
                $digits = false;
                while ($i < $len) {
                    $ch = $d[$i];
                    if (ctype_digit($ch)) {
                        $digits = true;
                        $i++;
                        continue;
                    }
                    if ($ch === '.' && !$dot) {
                        $dot = true;
                        $i++;
                        continue;
                    }
                    break;
                }
                if (!$digits && !$dot) {
                    throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. The path could not be read.');
                }
                $tokens[] = substr($d, $start, $i - $start);
                continue;
            }
            throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. The path could not be read.');
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function pair(string $x, string $y, string $cx, string $cy, bool $relative): array
    {
        if (!Decimal::isNumeric($x) || !Decimal::isNumeric($y)) {
            throw new \RuntimeException('MANUAL GEOMETRY REVIEW REQUIRED. A coordinate is not a number.');
        }
        if ($relative) {
            return [Decimal::add($cx, $x, Decimal::CALC_SCALE), Decimal::add($cy, $y, Decimal::CALC_SCALE)];
        }

        return [$x, $y];
    }

    /**
     * @param array{0: string, 1: string} $p0
     * @param array{0: string, 1: string} $p1
     * @param array{0: string, 1: string} $p2
     * @param array{0: string, 1: string} $p3
     * @return list<array{0: string, 1: string}>
     */
    private static function cubic(array $p0, array $p1, array $p2, array $p3): array
    {
        $points = [];
        for ($step = 1; $step <= 16; $step++) {
            $t = Decimal::div((string) $step, '16', Decimal::CALC_SCALE);
            $u = Decimal::sub('1', $t, Decimal::CALC_SCALE);
            $b0 = Decimal::mul(Decimal::mul($u, $u, Decimal::CALC_SCALE), $u, Decimal::CALC_SCALE);
            $b1 = Decimal::mul(Decimal::mul(Decimal::mul('3', $u, Decimal::CALC_SCALE), $u, Decimal::CALC_SCALE), $t, Decimal::CALC_SCALE);
            $b2 = Decimal::mul(Decimal::mul(Decimal::mul('3', $u, Decimal::CALC_SCALE), $t, Decimal::CALC_SCALE), $t, Decimal::CALC_SCALE);
            $b3 = Decimal::mul(Decimal::mul($t, $t, Decimal::CALC_SCALE), $t, Decimal::CALC_SCALE);
            $x = Decimal::add(
                Decimal::add(Decimal::mul($b0, $p0[0], Decimal::CALC_SCALE), Decimal::mul($b1, $p1[0], Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::add(Decimal::mul($b2, $p2[0], Decimal::CALC_SCALE), Decimal::mul($b3, $p3[0], Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            );
            $y = Decimal::add(
                Decimal::add(Decimal::mul($b0, $p0[1], Decimal::CALC_SCALE), Decimal::mul($b1, $p1[1], Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::add(Decimal::mul($b2, $p2[1], Decimal::CALC_SCALE), Decimal::mul($b3, $p3[1], Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            );
            $points[] = [$x, $y];
        }

        return $points;
    }

    /**
     * @param array{0: string, 1: string} $p0
     * @param array{0: string, 1: string} $p1
     * @param array{0: string, 1: string} $p2
     * @return list<array{0: string, 1: string}>
     */
    private static function quadratic(array $p0, array $p1, array $p2): array
    {
        $points = [];
        for ($step = 1; $step <= 16; $step++) {
            $t = Decimal::div((string) $step, '16', Decimal::CALC_SCALE);
            $u = Decimal::sub('1', $t, Decimal::CALC_SCALE);
            $b0 = Decimal::mul($u, $u, Decimal::CALC_SCALE);
            $b1 = Decimal::mul(Decimal::mul('2', $u, Decimal::CALC_SCALE), $t, Decimal::CALC_SCALE);
            $b2 = Decimal::mul($t, $t, Decimal::CALC_SCALE);
            $x = Decimal::add(
                Decimal::add(Decimal::mul($b0, $p0[0], Decimal::CALC_SCALE), Decimal::mul($b1, $p1[0], Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::mul($b2, $p2[0], Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            );
            $y = Decimal::add(
                Decimal::add(Decimal::mul($b0, $p0[1], Decimal::CALC_SCALE), Decimal::mul($b1, $p1[1], Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::mul($b2, $p2[1], Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            );
            $points[] = [$x, $y];
        }

        return $points;
    }

    private static function segment(string $x1, string $y1, string $x2, string $y2): string
    {
        $dx = Decimal::sub($x2, $x1, Decimal::CALC_SCALE);
        $dy = Decimal::sub($y2, $y1, Decimal::CALC_SCALE);
        if (Decimal::cmp($dx, '0') === 0) {
            return Decimal::cmp($dy, '0') < 0 ? Decimal::mul($dy, '-1', Decimal::CALC_SCALE) : $dy;
        }
        if (Decimal::cmp($dy, '0') === 0) {
            return Decimal::cmp($dx, '0') < 0 ? Decimal::mul($dx, '-1', Decimal::CALC_SCALE) : $dx;
        }
        $sum = Decimal::add(Decimal::mul($dx, $dx, Decimal::CALC_SCALE), Decimal::mul($dy, $dy, Decimal::CALC_SCALE), Decimal::CALC_SCALE);

        return self::sqrt($sum);
    }

    private static function sqrt(string $value): string
    {
        if (Decimal::cmp($value, '0') <= 0) {
            return '0';
        }
        $guess = Decimal::div($value, '2', Decimal::CALC_SCALE);
        if (Decimal::cmp($guess, '0') === 0) {
            $guess = '1';
        }
        for ($n = 0; $n < 12; $n++) {
            $guess = Decimal::div(Decimal::add($guess, Decimal::div($value, $guess, Decimal::CALC_SCALE), Decimal::CALC_SCALE), '2', Decimal::CALC_SCALE);
        }

        return $guess;
    }

    /**
     * @param list<array{0: string, 1: string}> $points
     */
    private static function shoelace(array $points): string
    {
        $sum = '0';
        $n = count($points);
        for ($i = 0; $i < $n; $i++) {
            $next = $points[($i + 1) % $n];
            $sum = Decimal::add($sum, Decimal::sub(
                Decimal::mul($points[$i][0], $next[1], Decimal::CALC_SCALE),
                Decimal::mul($next[0], $points[$i][1], Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            ), Decimal::CALC_SCALE);
        }
        if (Decimal::cmp($sum, '0') < 0) {
            $sum = Decimal::mul($sum, '-1', Decimal::CALC_SCALE);
        }

        return Decimal::div($sum, '2', Decimal::CALC_SCALE);
    }

    /**
     * @param list<list<array{0: string, 1: string}>> $shapes
     */
    private static function preview(array $shapes, string $minX, string $minY, string $maxX, string $maxY): string
    {
        $width = Decimal::sub($maxX, $minX, Decimal::CALC_SCALE);
        $height = Decimal::sub($maxY, $minY, Decimal::CALC_SCALE);
        if (Decimal::cmp($width, '1') < 0) {
            $width = '1';
        }
        if (Decimal::cmp($height, '1') < 0) {
            $height = '1';
        }
        $lines = [];
        foreach ($shapes as $shape) {
            $pairs = [];
            foreach ($shape as $point) {
                $pairs[] = Decimal::round($point[0], 2) . ',' . Decimal::round($point[1], 2);
            }
            $lines[] = '<polyline points="' . implode(' ', $pairs) . '" fill="none" stroke="currentColor"/>';
        }
        $label = htmlspecialchars(EngineeringLimits::SCHEMATIC, ENT_QUOTES, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . Decimal::round($minX, 2) . ' ' . Decimal::round($minY, 2) . ' ' . Decimal::round($width, 2) . ' ' . Decimal::round($height, 2) . '">'
            . '<title>' . $label . '</title>' . implode('', $lines) . '</svg>';
    }
}
