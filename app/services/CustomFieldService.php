<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\PlatformRepository;

/**
 * Custom fields are validated on the server. They are not executable.
 */
final class CustomFieldService
{
    /** @var list<string> */
    public const TYPES = [
        'TEXT', 'TEXTAREA', 'NUMBER', 'DECIMAL', 'DATE', 'DATETIME', 'BOOLEAN',
        'SELECT', 'MULTISELECT', 'EMAIL', 'PHONE', 'URL',
    ];

    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function define(array $input, int $userId): array
    {
        if (!can('custom_fields.manage')) {
            return ['errors' => ['_form' => 'You cannot add custom fields.'], 'id' => null];
        }
        $entity = strtoupper(trim((string) ($input['entity_type'] ?? '')));
        $key = strtolower(trim((string) ($input['field_key'] ?? '')));
        $type = strtoupper(trim((string) ($input['field_type'] ?? '')));
        $errors = [];
        if (!in_array($entity, WorkflowFactReader::ENTITIES, true)) {
            $errors['entity_type'] = 'Choose a supported record.';
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,40}$/', $key)) {
            $errors['field_key'] = 'Use a short field name.';
        }
        if (!in_array($type, self::TYPES, true)) {
            $errors['field_type'] = 'Choose a field type.';
        }
        if (trim((string) ($input['label'] ?? '')) === '') {
            $errors['label'] = 'Enter a label.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $id = $this->platform->insertField([
            'entity_type' => $entity,
            'field_key' => $key,
            'label' => trim((string) $input['label']),
            'field_type' => $type,
            'required' => posted_flag($input, 'required', 0),
            'options_json' => $this->options($input['options'] ?? null),
            'validation_json' => $this->options($input['validation'] ?? null),
            'default_value' => blank_to_null($input['default_value'] ?? null),
            'expose_documents' => posted_flag($input, 'expose_documents', 0),
            'active' => 1,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
        $this->audit->record('custom_field', $id, 'CUSTOM_FIELD_CREATED', null, ['field_key' => $key], $userId);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    public function validate(string $entityType, array $values): array
    {
        $errors = [];
        foreach ($this->platform->fields(strtoupper($entityType)) as $field) {
            $key = (string) $field['field_key'];
            $raw = $values[$key] ?? null;
            $text = is_array($raw) ? implode(',', array_map('strval', $raw)) : trim((string) ($raw ?? ''));
            if ((int) $field['required'] === 1 && $text === '') {
                $errors[$key] = (string) $field['label'] . ' is required.';
                continue;
            }
            if ($text === '') {
                continue;
            }
            $rules = json_decode((string) ($field['validation_json'] ?? '{}'), true);
            $rules = is_array($rules) ? $rules : [];
            $type = (string) $field['field_type'];
            if (in_array($type, ['NUMBER', 'DECIMAL'], true) && !Decimal::isNumeric($text)) {
                $errors[$key] = (string) $field['label'] . ' must be a number.';
            }
            if (isset($rules['min']) && Decimal::isNumeric($text) && Decimal::isNumeric((string) $rules['min']) && Decimal::cmp($text, (string) $rules['min']) < 0) {
                $errors[$key] = (string) $field['label'] . ' is below the minimum.';
            }
            if (isset($rules['max']) && Decimal::isNumeric($text) && Decimal::isNumeric((string) $rules['max']) && Decimal::cmp($text, (string) $rules['max']) > 0) {
                $errors[$key] = (string) $field['label'] . ' is above the maximum.';
            }
            if (isset($rules['max_length']) && mb_strlen($text) > (int) $rules['max_length']) {
                $errors[$key] = (string) $field['label'] . ' is too long.';
            }
            if ($type === 'EMAIL' && filter_var($text, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$key] = 'Enter a valid email.';
            }
            if ($type === 'URL' && filter_var($text, FILTER_VALIDATE_URL) === false) {
                $errors[$key] = 'Enter a valid web address.';
            }
            if (in_array($type, ['DATE', 'DATETIME'], true)) {
                $stamp = strtotime($text);
                if ($stamp === false) {
                    $errors[$key] = 'Enter a valid date.';
                } elseif (isset($rules['min_date'], $rules['max_date'])) {
                    if ($text < (string) $rules['min_date'] || $text > (string) $rules['max_date']) {
                        $errors[$key] = 'That date is outside the allowed range.';
                    }
                }
            }
            if (in_array($type, ['SELECT', 'MULTISELECT'], true)) {
                $options = json_decode((string) ($field['options_json'] ?? '[]'), true);
                $allowed = is_array($options) ? array_map('strval', $options) : [];
                $chosen = $type === 'MULTISELECT' ? array_map('trim', explode(',', $text)) : [$text];
                foreach ($chosen as $item) {
                    if ($item !== '' && !in_array($item, $allowed, true)) {
                        $errors[$key] = 'Choose one of the listed options.';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    public function save(string $entityType, int $entityId, array $values, int $userId): array
    {
        $entityType = strtoupper($entityType);
        $errors = $this->validate($entityType, $values);
        if ($errors !== []) {
            return $errors;
        }
        foreach ($this->platform->fields($entityType) as $field) {
            $key = (string) $field['field_key'];
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $raw = $values[$key];
            $text = is_array($raw) ? implode(',', array_map('strval', $raw)) : trim((string) $raw);
            $type = (string) $field['field_type'];
            $this->platform->upsertFieldValue([
                'field_definition_id' => (int) $field['id'],
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'value_text' => in_array($type, ['NUMBER', 'DECIMAL', 'DATE', 'DATETIME', 'MULTISELECT'], true) ? null : ($text === '' ? null : mb_substr($text, 0, 255)),
                'value_number' => in_array($type, ['NUMBER', 'DECIMAL'], true) && $text !== '' ? $text : null,
                'value_date' => in_array($type, ['DATE', 'DATETIME'], true) && $text !== '' ? str_replace('T', ' ', $text) : null,
                'value_json' => $type === 'MULTISELECT' ? json_encode(array_map('trim', explode(',', $text))) : null,
                'updated_by' => $userId,
            ]);
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forDocuments(string $entityType, int $entityId): array
    {
        return $this->platform->fieldValues(strtoupper($entityType), $entityId, true);
    }

    private function options(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }
        if (!is_array($value)) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
