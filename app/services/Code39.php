<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Code 39 bars for a package label. The text code remains the value the workshop scanner reads.
 */
final class Code39
{
    /** @var array<string, string> */
    private const MAP = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
    ];

    public static function svg(string $value): string
    {
        $text = '*' . strtoupper(preg_replace('/[^0-9A-Z\-. ]/', '', $value) ?? '') . '*';
        $x = 0;
        $rects = '';
        $narrow = 2;
        $wide = 5;
        foreach (str_split($text) as $char) {
            $pattern = self::MAP[$char] ?? self::MAP['-'];
            foreach (str_split($pattern) as $i => $bar) {
                $width = $bar === 'w' ? $wide : $narrow;
                if ($i % 2 === 0) {
                    $rects .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="48" fill="#111"/>';
                }
                $x += $width;
            }
            $x += $narrow;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $x . '" height="48" viewBox="0 0 ' . $x . ' 48" role="img" aria-label="' . htmlspecialchars($value, ENT_QUOTES) . '">' . $rects . '</svg>';
    }
}
