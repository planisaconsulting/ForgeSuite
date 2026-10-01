<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Code 128 (set B) as SVG. The bars encode the tracking code only.
 */
final class Code128
{
    /** @var list<string> */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public function svg(string $text, int $height = 48): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        if ($text === '') {
            $text = '-';
        }
        $codes = [104];
        $sum = 104;
        $i = 1;
        $length = strlen($text);
        for ($n = 0; $n < $length; $n++) {
            $value = ord($text[$n]) - 32;
            $codes[] = $value;
            $sum += $value * $i;
            $i++;
        }
        $codes[] = $sum % 103;
        $codes[] = 106;
        $bits = '';
        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code] ?? self::PATTERNS[0];
            $bar = true;
            $chars = str_split($pattern);
            foreach ($chars as $width) {
                $bits .= str_repeat($bar ? '1' : '0', (int) $width);
                $bar = !$bar;
            }
        }
        $x = 10;
        $rects = '';
        $bitLength = strlen($bits);
        for ($n = 0; $n < $bitLength; $n++) {
            if ($bits[$n] === '1') {
                $rects .= '<rect x="' . $x . '" y="0" width="1" height="' . $height . '" fill="#111"/>';
            }
            $x++;
        }
        $width = $x + 10;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . ($height + 16) . '" role="img" aria-label="'
            . htmlspecialchars($text, ENT_QUOTES) . '"><rect width="100%" height="100%" fill="#fff"/>' . $rects
            . '<text x="' . (int) ($width / 2) . '" y="' . ($height + 12) . '" text-anchor="middle" font-size="10" font-family="DejaVu Sans, sans-serif" fill="#111">'
            . htmlspecialchars($text, ENT_QUOTES) . '</text></svg>';
    }
}
