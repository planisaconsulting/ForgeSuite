<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForecastRepository;

/**
 * CSV import. Rows are validated and shown before anything is written.
 * A company-name match is a warning. It is not merged.
 */
final class ImportService
{
    public function __construct(
        private readonly ForecastRepository $planning = new ForecastRepository(),
        private readonly CustomerService $customers = new CustomerService()
    ) {
    }

    /**
     * @return array{rows: list<array<string, mixed>>, valid: int, invalid: int}
     */
    public function previewCustomers(string $csv): array
    {
        $parsed = $this->parse($csv);
        $rows = [];
        $valid = 0;
        $invalid = 0;
        foreach ($parsed as $index => $row) {
            $errors = [];
            $company = trim((string) ($row['company_name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($company === '') {
                $errors[] = 'Company name is required.';
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Email is not valid.';
            }
            $warnings = [];
            if ($email !== '' && $this->planning->customerByEmail($email) !== null) {
                $errors[] = 'A customer with this email already exists.';
            }
            if ($company !== '' && $this->planning->customersByName($company) !== []) {
                $warnings[] = 'A customer with the same company name exists. It was not merged.';
            }
            $ok = $errors === [];
            if ($ok) {
                $valid++;
            } else {
                $invalid++;
            }
            $rows[] = [
                'line' => $index + 2,
                'company_name' => $company,
                'email' => $email,
                'phone' => trim((string) ($row['phone'] ?? '')),
                'ok' => $ok,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        return ['rows' => $rows, 'valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * @param list<array<string, mixed>> $previewRows
     * @return array{imported: int, skipped: int, id: int}
     */
    public function commitCustomers(array $previewRows, string $filename, int $userId): array
    {
        if (!can('imports.perform')) {
            return ['imported' => 0, 'skipped' => count($previewRows), 'id' => 0];
        }
        $imported = 0;
        $failed = 0;
        foreach ($previewRows as $row) {
            if (empty($row['ok'])) {
                $failed++;
                continue;
            }
            $created = $this->customers->create([
                'customer_type' => 'BUSINESS',
                'company_name' => $row['company_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
            ], $userId);
            if ($created['id'] === null) {
                $failed++;
                continue;
            }
            $imported++;
        }
        $id = $this->planning->insertImport([
            'import_type' => 'CUSTOMERS',
            'filename' => mb_substr($filename, 0, 255),
            'status' => 'IMPORTED',
            'rows_total' => count($previewRows),
            'rows_success' => $imported,
            'rows_failed' => $failed,
            'created_by' => $userId,
            'completed_at' => date('Y-m-d H:i:s'),
            'error_file_path' => null,
        ]);

        return ['imported' => $imported, 'skipped' => $failed, 'id' => $id];
    }

    /**
     * @return list<array<string, string>>
     */
    public function parse(string $csv): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        if ($lines === [] || $lines === ['']) {
            return [];
        }
        $header = str_getcsv(array_shift($lines));
        $header = array_map(static fn (string $column): string => strtolower(trim($column)), $header);
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $row = [];
            foreach ($header as $index => $name) {
                $row[$name] = (string) ($values[$index] ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
