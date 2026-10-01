<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Accepts a JPEG or PNG the phone already compressed.
 * This host may not have GD, so the server does not invent a second encoding.
 * Files over the configured limit are refused. The original is kept only when image_keep_original is on.
 */
final class FieldImage
{
    /**
     * @return array{error: string}|array{binary: string, mime: string, ext: string}
     */
    public function prepare(string $raw): array
    {
        $text = trim($raw);
        if (str_starts_with($text, 'data:image/png;base64,')) {
            $text = substr($text, strlen('data:image/png;base64,'));
        } elseif (str_starts_with($text, 'data:image/jpeg;base64,')) {
            $text = substr($text, strlen('data:image/jpeg;base64,'));
        }
        $binary = base64_decode($text, true);
        if (!is_string($binary) || $binary === '') {
            return ['error' => 'INVALID'];
        }
        $max = (int) FieldSettings::get('image_max_upload_bytes');
        if ($max < 1) {
            $max = 1500000;
        }
        if (strlen($binary) > $max) {
            return ['error' => 'PHOTO_TOO_LARGE'];
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return ['binary' => $binary, 'mime' => 'image/png', 'ext' => 'png'];
        }
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return ['binary' => $binary, 'mime' => 'image/jpeg', 'ext' => 'jpg'];
        }

        return ['error' => 'INVALID'];
    }

    public function store(string $binary, string $ext): string
    {
        $dir = base_path('storage/field-photos');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'fp-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpg' ? 'jpg' : 'png');
        file_put_contents($dir . '/' . $name, $binary);

        return 'storage/field-photos/' . $name;
    }
}
