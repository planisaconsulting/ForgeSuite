<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Precedence, first match wins:
 * 1. TRANSACTION override supplied for this decision
 * 2. CUSTOMER
 * 3. PRODUCT
 * 4. CATEGORY
 * 5. COMPANY
 * 6. SYSTEM default
 */
final class BusinessRuleService
{
    /** @var list<string> */
    public const PRECEDENCE = ['TRANSACTION', 'CUSTOMER', 'PRODUCT', 'CATEGORY', 'COMPANY', 'SYSTEM'];

    /** @var list<string> */
    public const KEYS = [
        'minimum_quote_value',
        'default_quote_validity_days',
        'maximum_discount_before_approval',
        'minimum_margin_before_approval',
        'deposit_requirement_percent',
        'customer_credit_warning',
        'stock_adjustment_threshold',
        'po_approval_threshold',
    ];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @param array<string, int> $scopes scope type => id
     */
    public function resolve(string $key, array $scopes = [], ?string $transactionValue = null): ?string
    {
        if ($transactionValue !== null && $transactionValue !== '') {
            return $transactionValue;
        }
        $rows = $this->platform->rulesFor($key);
        foreach (self::PRECEDENCE as $scope) {
            if ($scope === 'TRANSACTION' || $scope === 'SYSTEM') {
                continue;
            }
            $scopeId = $scopes[$scope] ?? null;
            if ($scopeId === null) {
                continue;
            }
            foreach ($rows as $row) {
                if ((string) $row['scope_type'] === $scope && (int) $row['scope_id'] === (int) $scopeId) {
                    return (string) $row['value_text'];
                }
            }
        }
        foreach ($rows as $row) {
            if ((string) $row['scope_type'] === 'COMPANY') {
                return (string) $row['value_text'];
            }
        }
        foreach ($rows as $row) {
            if ((string) $row['scope_type'] === 'SYSTEM') {
                return (string) $row['value_text'];
            }
        }

        return null;
    }

    public function put(string $key, string $scope, int $scopeId, string $value, int $userId): void
    {
        if (!in_array($key, self::KEYS, true) || !in_array($scope, self::PRECEDENCE, true) || $scope === 'TRANSACTION') {
            return;
        }
        $this->platform->upsertRule([
            'rule_key' => $key,
            'scope_type' => $scope,
            'scope_id' => $scope === 'SYSTEM' || $scope === 'COMPANY' ? 0 : $scopeId,
            'value_text' => mb_substr($value, 0, 255),
            'updated_by' => $userId,
        ]);
    }
}
