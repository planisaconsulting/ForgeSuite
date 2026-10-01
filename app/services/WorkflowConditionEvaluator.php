<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Conditions use a fixed operator list. Groups with the same number are AND.
 * Different groups are OR. There is no expression language.
 */
final class WorkflowConditionEvaluator
{
    /** @var list<string> */
    public const OPERATORS = [
        'EQUALS',
        'NOT_EQUALS',
        'GREATER_THAN',
        'GREATER_THAN_OR_EQUAL',
        'LESS_THAN',
        'LESS_THAN_OR_EQUAL',
        'CONTAINS',
        'NOT_CONTAINS',
        'IS_EMPTY',
        'IS_NOT_EMPTY',
        'IN',
        'NOT_IN',
        'BETWEEN',
    ];

    /**
     * @param list<array<string, mixed>> $conditions
     * @param array<string, mixed> $facts
     * @return array{matched: bool, results: list<array{field: string, operator: string, matched: bool}>}
     */
    public function evaluate(array $conditions, array $facts): array
    {
        if ($conditions === []) {
            return ['matched' => true, 'results' => []];
        }
        $groups = [];
        $results = [];
        foreach ($conditions as $condition) {
            $group = (int) ($condition['condition_group'] ?? 1);
            $ok = $this->one($condition, $facts);
            $groups[$group][] = $ok;
            $results[] = [
                'field' => (string) ($condition['field_key'] ?? ''),
                'operator' => (string) ($condition['operator'] ?? ''),
                'matched' => $ok,
            ];
        }
        $matched = false;
        foreach ($groups as $parts) {
            if (!in_array(false, $parts, true)) {
                $matched = true;
                break;
            }
        }

        return ['matched' => $matched, 'results' => $results];
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function one(array $condition, array $facts): bool
    {
        $operator = strtoupper((string) ($condition['operator'] ?? ''));
        if (!in_array($operator, self::OPERATORS, true)) {
            return false;
        }
        $key = (string) ($condition['field_key'] ?? '');
        $left = $facts[$key] ?? null;
        $right = (string) ($condition['comparison_value'] ?? '');
        $text = trim((string) ($left ?? ''));
        if ($operator === 'IS_EMPTY') {
            return $text === '';
        }
        if ($operator === 'IS_NOT_EMPTY') {
            return $text !== '';
        }
        if (in_array($operator, ['CONTAINS', 'NOT_CONTAINS'], true)) {
            $found = $right !== '' && str_contains(strtolower($text), strtolower($right));

            return $operator === 'CONTAINS' ? $found : !$found;
        }
        if (in_array($operator, ['IN', 'NOT_IN'], true)) {
            $options = array_map('trim', explode(',', $right));
            $found = in_array($text, $options, true);

            return $operator === 'IN' ? $found : !$found;
        }
        if ($operator === 'BETWEEN') {
            $ends = array_map('trim', explode('|', $right));
            if (count($ends) !== 2 || !Decimal::isNumeric($text) || !Decimal::isNumeric($ends[0]) || !Decimal::isNumeric($ends[1])) {
                return false;
            }

            return Decimal::cmp($text, $ends[0]) >= 0 && Decimal::cmp($text, $ends[1]) <= 0;
        }
        if (in_array($operator, ['GREATER_THAN', 'GREATER_THAN_OR_EQUAL', 'LESS_THAN', 'LESS_THAN_OR_EQUAL'], true)) {
            if (!Decimal::isNumeric($text) || !Decimal::isNumeric($right)) {
                return false;
            }

            return match ($operator) {
                'GREATER_THAN' => Decimal::cmp($text, $right) > 0,
                'GREATER_THAN_OR_EQUAL' => Decimal::cmp($text, $right) >= 0,
                'LESS_THAN' => Decimal::cmp($text, $right) < 0,
                'LESS_THAN_OR_EQUAL' => Decimal::cmp($text, $right) <= 0,
                default => false,
            };
        }
        $same = strcasecmp($text, $right) === 0;

        return $operator === 'EQUALS' ? $same : !$same;
    }
}
