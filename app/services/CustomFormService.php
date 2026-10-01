<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * A submission stays on the form version that was current when it was saved.
 */
final class CustomFormService
{
    /** @var list<string> */
    public const SOURCES = ['EXISTING', 'CUSTOM', 'HEADING', 'INSTRUCTION', 'CHECKLIST'];

    /** @var list<string> */
    public const PURPOSES = [
        'VEHICLE_INSPECTION', 'SITE_ACCESS', 'SIGN_MAINTENANCE', 'CUSTOMER_HANDOVER',
        'INSTALLATION_SAFETY', 'MACHINE_INSPECTION', 'DELIVERY_INSPECTION', 'QC', 'DISPATCH',
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
    public function save(array $input, int $userId, ?int $id = null): array
    {
        if (!can('custom_forms.manage') || !(new FeatureFlagService($this->platform))->enabled('CUSTOM_FORMS')) {
            return ['errors' => ['_form' => 'Custom forms are not available.'], 'id' => null];
        }
        $name = trim((string) ($input['name'] ?? ''));
        $purpose = strtoupper((string) ($input['purpose'] ?? ''));
        if ($name === '' || !in_array($purpose, self::PURPOSES, true)) {
            return ['errors' => ['_form' => 'Name the form and choose a purpose.'], 'id' => null];
        }
        $fields = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        if ($id === null) {
            $id = $this->platform->insertForm([
                'name' => $name,
                'purpose' => $purpose,
                'entity_type' => strtoupper((string) ($input['entity_type'] ?? 'JOB')),
                'active' => 1,
                'created_by' => $userId,
            ]);
            $version = 1;
        } else {
            $version = $this->platform->bumpForm($id);
            $this->audit->record('custom_form', $id, 'FORM_CHANGED', null, ['version' => $version], $userId);
        }
        $sort = 0;
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $source = strtoupper((string) ($field['field_source'] ?? 'CUSTOM'));
            if (!in_array($source, self::SOURCES, true)) {
                continue;
            }
            $this->platform->insertFormField([
                'form_id' => $id,
                'form_version' => $version,
                'field_source' => $source,
                'field_key' => strtolower((string) ($field['field_key'] ?? 'note')),
                'label' => (string) ($field['label'] ?? 'Note'),
                'field_type' => strtoupper((string) ($field['field_type'] ?? 'TEXT')),
                'required' => (int) ($field['required'] ?? 0) === 1 ? 1 : 0,
                'options_json' => isset($field['options']) ? json_encode($field['options']) : null,
                'sort_order' => $sort++,
            ]);
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $values
     * @return array{errors: array<string, string>, id: int|null, version: int|null}
     */
    public function submit(int $formId, array $values, int $userId, ?string $entityType, ?int $entityId, ?string $signature): array
    {
        $form = $this->platform->form($formId);
        if ($form === null) {
            return ['errors' => ['_form' => 'That form was not found.'], 'id' => null, 'version' => null];
        }
        $version = (int) $form['current_version'];
        $errors = [];
        foreach ($this->platform->formFields($formId, $version) as $field) {
            if ((int) $field['required'] !== 1 || in_array((string) $field['field_source'], ['HEADING', 'INSTRUCTION'], true)) {
                continue;
            }
            $key = (string) $field['field_key'];
            if (trim((string) ($values[$key] ?? '')) === '') {
                $errors[$key] = (string) $field['label'] . ' is required.';
            }
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null, 'version' => $version];
        }
        $id = $this->platform->insertSubmission([
            'form_id' => $formId,
            'form_version' => $version,
            'entity_type' => $entityType !== null ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'submitted_by' => $userId,
            'values_json' => json_encode($values, JSON_THROW_ON_ERROR),
            'signature_name' => blank_to_null($signature),
            'signature_at' => $signature !== null && trim($signature) !== '' ? date('Y-m-d H:i:s') : null,
        ]);

        return ['errors' => [], 'id' => $id, 'version' => $version];
    }

    /**
     * @return array{submission: array<string, mixed>, fields: list<array<string, mixed>>, values: array<string, mixed>}|null
     */
    public function render(int $submissionId): ?array
    {
        $submission = $this->platform->submission($submissionId);
        if ($submission === null) {
            return null;
        }
        $values = json_decode((string) $submission['values_json'], true);

        return [
            'submission' => $submission,
            'fields' => $this->platform->formFields((int) $submission['form_id'], (int) $submission['form_version']),
            'values' => is_array($values) ? $values : [],
        ];
    }

    public function bindChecklist(string $name, string $area, int $formId): int
    {
        return $this->platform->insertChecklist([
            'name' => $name,
            'area' => strtoupper($area),
            'form_id' => $formId,
            'active' => 1,
        ]);
    }
}
