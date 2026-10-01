<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Field settings are read one key at a time.
 * They are not part of SettingsService::FIELDS, so a general settings save does not clear them.
 */
final class FieldSettings
{
    /** @var array<string, string> */
    public const DEFAULTS = [
        'offline_enabled' => '1',
        'offline_session_hours' => '24',
        'field_pack_max_age_hours' => '72',
        'image_compression_quality' => '70',
        'offline_storage_warning_mb' => '300',
        'gps_capture_policy' => 'OPTIONAL',
        'photo_required_on_complete' => '0',
        'signature_required_on_complete' => '1',
        'auto_sync' => '1',
        'push_enabled' => '0',
        'field_pack_cleanup_days' => '14',
        'measurement_sanity_mm' => '20000',
        'image_max_upload_bytes' => '1500000',
        'image_keep_original' => '0',
        'quiet_hours_start' => '20:00',
        'quiet_hours_end' => '07:00',
        'urgent_push_during_quiet' => '0',
        'clock_skew_hours' => '12',
        'installation_safety_ack' => '1',
        'schema_version' => '14',
    ];

    public static function get(string $key): string
    {
        return (string) SettingsService::get($key, self::DEFAULTS[$key] ?? '');
    }

    public static function enabled(): bool
    {
        return self::get('offline_enabled') === '1'
            && (new FeatureFlagService())->enabled('OFFLINE_FIELD_MODE');
    }
}
