<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds a tracking link only from a courier template that starts with https.
 * A person cannot store an arbitrary address as the customer tracking link.
 */
final class TrackingUrl
{
    public static function build(?string $template, string $tracking, string $waybill): ?string
    {
        $template = trim((string) $template);
        if ($template === '' || !preg_match('#^https://#i', $template)) {
            return null;
        }
        if (preg_match('/\s|javascript:|data:|\\\\/i', $template)) {
            return null;
        }
        if (preg_match('/\{(?!tracking\}|waybill\})/', $template)) {
            return null;
        }
        $url = str_replace(
            ['{tracking}', '{waybill}'],
            [rawurlencode($tracking), rawurlencode($waybill)],
            $template
        );
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/i', $host)) {
            return null;
        }

        return $url;
    }

    public static function mapLink(string $address): ?string
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }
        $template = (string) SettingsService::get('map_url_template', 'https://www.google.com/maps/search/?api=1&query={query}');

        return self::build(str_replace('{query}', '{tracking}', $template), $address, '');
    }
}
