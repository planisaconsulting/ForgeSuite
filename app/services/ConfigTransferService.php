<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;
use App\Repositories\SettingRepository;
use App\Repositories\WorkflowRepository;

/**
 * Export leaves secrets out. Import previews structured rows and refuses code.
 */
final class ConfigTransferService
{
    /** @var list<string> */
    private const SECRET_KEYS = [
        'password', 'smtp_password', 'smtp_pass', 'payment_webhook_secret', 'api_secret',
        'secret', 'token', 'accounting_secret',
    ];

    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly WorkflowRepository $workflows = new WorkflowRepository(),
        private readonly SettingRepository $settings = new SettingRepository()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $settings = [];
        foreach ($this->settings->all() as $key => $value) {
            if ($this->secretKey((string) $key)) {
                continue;
            }
            $settings[(string) $key] = $value;
        }
        $workflows = [];
        foreach ($this->workflows->all() as $workflow) {
            $id = (int) $workflow['id'];
            $actions = [];
            foreach ($this->workflows->actions($id) as $action) {
                $config = json_decode((string) ($action['configuration_json'] ?? '{}'), true);
                $clean = [];
                foreach (is_array($config) ? $config : [] as $key => $item) {
                    if (is_string($key) && !$this->secretKey($key)) {
                        $clean[$key] = $item;
                    }
                }
                $action['configuration_json'] = $clean;
                $actions[] = $action;
            }
            $workflows[] = [
                'definition' => $workflow,
                'conditions' => $this->workflows->conditions($id),
                'actions' => $actions,
            ];
        }

        return [
            'format' => 'signforge-config',
            'version' => '13.0',
            'settings' => $settings,
            'workflows' => $workflows,
            'approval_policies' => $this->platform->policies(),
            'feature_flags' => $this->platform->flags(),
            'business_rules' => $this->platform->allRules(),
            'custom_fields' => $this->fields(),
            'custom_forms' => $this->platform->forms(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, errors: list<string>, summary: array<string, int>}
     */
    public function preview(array $payload): array
    {
        $errors = [];
        if (($payload['format'] ?? '') !== 'signforge-config' || ($payload['version'] ?? '') !== '13.0') {
            $errors[] = 'The file is not a Sign-Forge 13.0 configuration export.';
        }
        $encoded = json_encode($payload);
        if (is_string($encoded) && SafeValue::executable($encoded)) {
            $errors[] = 'The file contains content that cannot be imported.';
        }
        $summary = [
            'settings' => is_array($payload['settings'] ?? null) ? count($payload['settings']) : 0,
            'workflows' => is_array($payload['workflows'] ?? null) ? count($payload['workflows']) : 0,
            'flags' => is_array($payload['feature_flags'] ?? null) ? count($payload['feature_flags']) : 0,
        ];

        return ['ok' => $errors === [], 'errors' => $errors, 'summary' => $summary];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, errors: list<string>}
     */
    public function apply(array $payload, int $userId): array
    {
        if (!can('configuration.manage')) {
            return ['ok' => false, 'errors' => ['You cannot import configuration.']];
        }
        $preview = $this->preview($payload);
        if (!$preview['ok']) {
            return ['ok' => false, 'errors' => $preview['errors']];
        }
        foreach (is_array($payload['feature_flags'] ?? null) ? $payload['feature_flags'] : [] as $flag) {
            if (!is_array($flag) || !in_array((string) ($flag['feature_key'] ?? ''), FeatureFlagService::KEYS, true)) {
                continue;
            }
            $this->platform->saveFlag((string) $flag['feature_key'], (int) ($flag['enabled'] ?? 0) === 1 ? 1 : 0, null, $userId);
        }
        foreach (is_array($payload['business_rules'] ?? null) ? $payload['business_rules'] : [] as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            (new BusinessRuleService($this->platform))->put(
                (string) ($rule['rule_key'] ?? ''),
                (string) ($rule['scope_type'] ?? ''),
                (int) ($rule['scope_id'] ?? 0),
                (string) ($rule['value_text'] ?? ''),
                $userId
            );
        }

        return ['ok' => true, 'errors' => []];
    }

    public function containsSecret(string $json): bool
    {
        $lower = strtolower($json);
        foreach (self::SECRET_KEYS as $key) {
            if (str_contains($lower, $key)) {
                return true;
            }
        }

        return false;
    }

    private function secretKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (['password', 'secret', 'token', 'smtp'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fields(): array
    {
        $rows = [];
        foreach (WorkflowFactReader::ENTITIES as $entity) {
            foreach ($this->platform->fields($entity, false) as $field) {
                $rows[] = $field;
            }
        }

        return $rows;
    }
}
