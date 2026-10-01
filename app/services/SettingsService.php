<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\SettingRepository;

/**
 * Company and application settings.
 *
 * Read these instead of typing "R", "15", or "Africa/Johannesburg" into a
 * screen. The config file still holds the database password and a fallback
 * timezone used before MySQL answers.
 */
final class SettingsService
{
    /** @var array<string, string> */
    public const FIELDS = [
        'company_name' => 'Company name',
        'trading_name' => 'Trading name',
        'company_registration' => 'Registration number',
        'vat_number' => 'VAT number',
        'address' => 'Address',
        'telephone' => 'Telephone',
        'email' => 'Email',
        'website' => 'Website',
        'currency_code' => 'Currency code',
        'currency_symbol' => 'Currency symbol',
        'default_vat_percent' => 'VAT rate (%)',
        'quote_prefix' => 'Quote prefix',
        'opportunity_prefix' => 'Opportunity prefix',
        'invoice_prefix' => 'Invoice prefix',
        'job_prefix' => 'Job prefix',
        'default_quote_validity_days' => 'Default quote validity (days)',
        'default_quote_terms' => 'Default quotation terms',
        'default_labour_hourly_cost' => 'Default internal labour cost per hour',
        'timezone' => 'Timezone',
    ];

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public function __construct(
        private readonly SettingRepository $settings = new SettingRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::cached();
        if (!array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') {
            return $default;
        }

        return $all[$key];
    }

    public static function symbol(): string
    {
        return self::get('currency_symbol', 'R') ?? 'R';
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return self::cached();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(array $input): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        $old = [];
        $new = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($key === 'currency_code') {
                $value = strtoupper($value);
            }
            $old[$key] = self::get($key, '');
            $new[$key] = $value;
        }

        Database::transaction(function () use ($new, $old): void {
            foreach ($new as $key => $value) {
                $this->settings->put($key, $value);
            }
            self::forget();
            $this->audit->record('settings', null, 'updated', $old, $new);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['company_name'] ?? ''));
        if ($name === '') {
            $errors['company_name'] = 'Company name is required.';
        }
        $symbol = trim((string) ($input['currency_symbol'] ?? ''));
        if ($symbol === '') {
            $errors['currency_symbol'] = 'Currency symbol is required.';
        }
        $code = strtoupper(trim((string) ($input['currency_code'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            $errors['currency_code'] = 'Currency code must be three letters, such as ZAR.';
        }
        $vat = str_replace(',', '.', trim((string) ($input['default_vat_percent'] ?? '')));
        if ($vat === '' || !is_numeric($vat) || (float) $vat < 0 || (float) $vat > 100) {
            $errors['default_vat_percent'] = 'VAT rate must be between 0 and 100.';
        }
        $days = trim((string) ($input['default_quote_validity_days'] ?? ''));
        if ($days === '' || !ctype_digit($days) || (int) $days < 1 || (int) $days > 365) {
            $errors['default_quote_validity_days'] = 'Quote validity must be a whole number of days from 1 to 365.';
        }
        $tz = trim((string) ($input['timezone'] ?? ''));
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Choose a timezone from the PHP list, such as Africa/Johannesburg.';
        }
        foreach (['quote_prefix', 'opportunity_prefix', 'invoice_prefix', 'job_prefix'] as $prefix) {
            $value = trim((string) ($input[$prefix] ?? ''));
            if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $value)) {
                $errors[$prefix] = 'Use 1 to 12 letters or numbers.';
            }
        }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address or leave it blank.';
        }

        return $errors;
    }

    /**
     * @return array<string, string|null>
     */
    private static function cached(): array
    {
        if (self::$cache === null) {
            self::$cache = (new SettingRepository())->all();
        }

        return self::$cache;
    }
}
