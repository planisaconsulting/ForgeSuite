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
        'payment_prefix' => 'Payment prefix',
        'credit_note_prefix' => 'Credit note prefix',
        'bank_name' => 'Bank name',
        'account_name' => 'Account name',
        'account_number' => 'Account number',
        'branch_code' => 'Branch code',
        'account_type' => 'Account type',
        'job_prefix' => 'Job prefix',
        'po_prefix' => 'Purchase order prefix',
        'grn_prefix' => 'Goods receipt prefix',
        'roll_prefix' => 'Roll code prefix',
        'sheet_prefix' => 'Sheet code prefix',
        'offcut_prefix' => 'Offcut code prefix',
        'batch_prefix' => 'Batch code prefix',
        'default_costing_method' => 'Default costing method (LAST_COST or WEIGHTED_AVERAGE_COST)',
        'offcut_valuation' => 'Offcut valuation (FULL_COST, REDUCED_COST, or ZERO_COST)',
        'offcut_value_percent' => 'Offcut value percent when reduced',
        'default_quote_validity_days' => 'Default quote validity (days)',
        'default_quote_terms' => 'Default quotation terms',
        'default_labour_hourly_cost' => 'Default internal labour cost per hour',
        'timezone' => 'Timezone',
        'quote_expiry_warning_days' => 'Quote expiry warning (days)',
        'invoice_due_soon_days' => 'Invoice due-soon warning (days)',
        'slow_stock_days' => 'Slow stock with no consumption (days)',
        'large_balance_amount' => 'Large outstanding balance amount',
        'backup_keep_daily' => 'Backup copies to keep, daily',
        'backup_keep_weekly' => 'Backup copies to keep, weekly',
        'backup_keep_monthly' => 'Backup copies to keep, monthly',
        'survey_prefix' => 'Site survey prefix',
        'travel_rate_per_km' => 'Travel rate per kilometre',
        'quote_acceptance_statement' => 'Quote acceptance statement',
        'artwork_approval_statement' => 'Artwork approval statement',
        'portal_link_hours' => 'Portal link lifetime (hours)',
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
            if ($key === 'currency_code' || $key === 'default_costing_method' || $key === 'offcut_valuation') {
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
        foreach (['quote_prefix', 'opportunity_prefix', 'invoice_prefix', 'job_prefix', 'po_prefix', 'grn_prefix', 'roll_prefix', 'sheet_prefix', 'offcut_prefix', 'batch_prefix', 'payment_prefix', 'credit_note_prefix', 'survey_prefix'] as $prefix) {
            $value = trim((string) ($input[$prefix] ?? ''));
            if (!preg_match('/^[A-Za-z0-9]{1,12}$/', $value)) {
                $errors[$prefix] = 'Use 1 to 12 letters or numbers.';
            }
        }
        $costing = strtoupper(trim((string) ($input['default_costing_method'] ?? 'LAST_COST')));
        if (!in_array($costing, ['LAST_COST', 'WEIGHTED_AVERAGE_COST'], true)) {
            $errors['default_costing_method'] = 'Use LAST_COST or WEIGHTED_AVERAGE_COST.';
        }
        $offcut = strtoupper(trim((string) ($input['offcut_valuation'] ?? 'REDUCED_COST')));
        if (!in_array($offcut, ['FULL_COST', 'REDUCED_COST', 'ZERO_COST'], true)) {
            $errors['offcut_valuation'] = 'Use FULL_COST, REDUCED_COST, or ZERO_COST.';
        }
        $offcutPercent = str_replace(',', '.', trim((string) ($input['offcut_value_percent'] ?? '50')));
        if ($offcutPercent === '' || !is_numeric($offcutPercent) || (float) $offcutPercent < 0 || (float) $offcutPercent > 100) {
            $errors['offcut_value_percent'] = 'Offcut value percent must be between 0 and 100.';
        }
        foreach ([
            'quote_expiry_warning_days' => [1, 30],
            'invoice_due_soon_days' => [1, 30],
            'slow_stock_days' => [30, 730],
            'backup_keep_daily' => [1, 30],
            'backup_keep_weekly' => [1, 52],
            'backup_keep_monthly' => [1, 24],
        ] as $key => [$min, $max]) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '' || !ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
                $errors[$key] = 'Enter a whole number from ' . $min . ' to ' . $max . '.';
            }
        }
        $travel = str_replace(',', '.', trim((string) ($input['travel_rate_per_km'] ?? '0')));
        if ($travel === '' || !is_numeric($travel) || (float) $travel < 0) {
            $errors['travel_rate_per_km'] = 'Travel rate must be zero or greater.';
        }
        $hours = trim((string) ($input['portal_link_hours'] ?? ''));
        if ($hours === '' || !ctype_digit($hours) || (int) $hours < 1 || (int) $hours > 168) {
            $errors['portal_link_hours'] = 'Portal link lifetime must be a whole number of hours from 1 to 168.';
        }
        foreach (['quote_acceptance_statement', 'artwork_approval_statement'] as $statement) {
            $text = trim((string) ($input[$statement] ?? ''));
            if ($text === '' || strlen($text) > 1000) {
                $errors[$statement] = 'Enter a statement of up to 1000 characters.';
            }
        }
        $balance = str_replace(',', '.', trim((string) ($input['large_balance_amount'] ?? '')));
        if ($balance === '' || !is_numeric($balance) || (float) $balance < 0) {
            $errors['large_balance_amount'] = 'Enter an amount of zero or more.';
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
