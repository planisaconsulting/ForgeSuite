<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Missing questions come from rules, not from a model.
 */
final class IntakeRequirementValidator
{
    /**
     * @param array<string, mixed> $intake
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $rules
     * @param array<string, string> $values confirmed or proposed field values
     * @return list<array{field: string, question: string}>
     */
    public function missing(array $intake, array $items, array $rules, array $values, string $language): array
    {
        $missing = [];
        $category = '';
        foreach ($items as $item) {
            $category = (string) ($item['material_category'] ?? $category);
            if (($item['quantity'] ?? null) === null || (string) $item['quantity'] === '') {
                $missing[] = $this->row('quantity', $language, 'Please confirm the quantity.', 'Bevestig asseblief die hoeveelheid.');
            }
        }
        $kind = (string) ($intake['estimator_kind'] ?? '');
        $type = $kind === 'VEHICLE_WRAP' ? 'VEHICLE' : ($kind === 'CHANNEL_LETTER' ? 'ILLUMINATED' : 'SIGNAGE');
        foreach ($rules as $rule) {
            if ((int) ($rule['active'] ?? 1) !== 1) {
                continue;
            }
            if (strtoupper((string) $rule['requirement_type']) !== $type) {
                continue;
            }
            $ruleCategory = trim((string) ($rule['material_category'] ?? ''));
            if ($ruleCategory !== '' && strcasecmp($ruleCategory, $category) !== 0) {
                continue;
            }
            $field = (string) $rule['field_name'];
            if ($this->present($values, $items, $field)) {
                continue;
            }
            $prompt = $language === 'AF' ? (string) $rule['prompt_af'] : (string) $rule['prompt_en'];
            $missing[] = ['field' => $field, 'question' => $prompt];
        }

        return $this->unique($missing);
    }

    /**
     * @param array<string, string> $values
     * @param list<array<string, mixed>> $items
     */
    private function present(array $values, array $items, string $field): bool
    {
        if (trim((string) ($values[$field] ?? '')) !== '') {
            return true;
        }
        foreach ($items as $item) {
            if (trim((string) ($item[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{field: string, question: string}
     */
    private function row(string $field, string $language, string $en, string $af): array
    {
        return ['field' => $field, 'question' => $language === 'AF' ? $af : $en];
    }

    /**
     * @param list<array{field: string, question: string}> $rows
     * @return list<array{field: string, question: string}>
     */
    private function unique(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['field']])) {
                continue;
            }
            $seen[$row['field']] = true;
            $out[] = $row;
        }

        return $out;
    }
}
