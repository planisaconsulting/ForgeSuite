<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Product and specification rules without eval or dynamic PHP.
 * A hard rule blocks the combination. A soft rule warns or asks for review.
 */
final class CompatibilityRuleService
{
    /** @var list<string> */
    public const TYPES = [
        'REQUIRES', 'EXCLUDES', 'REQUIRES_ONE_OF', 'ALLOWS_ONLY',
        'MIN_VALUE', 'MAX_VALUE', 'MIN_QUANTITY', 'MAX_QUANTITY',
        'WARNING', 'ENGINEERING_REVIEW', 'ELECTRICAL_REVIEW',
    ];

    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $input
     * @return array{blocked: bool, messages: list<string>, warnings: list<string>, engineering: bool, electrical: bool}
     */
    public function evaluate(array $rules, array $input): array
    {
        $blocked = false;
        $messages = [];
        $warnings = [];
        $engineering = false;
        $electrical = false;
        foreach ($rules as $rule) {
            if ((int) ($rule['active'] ?? 1) !== 1) {
                continue;
            }
            if (!$this->matches($input, (string) $rule['when_key'], (string) $rule['when_op'], (string) $rule['when_value'])) {
                continue;
            }
            if (($rule['and_key'] ?? null) !== null && (string) $rule['and_key'] !== '') {
                if (!$this->matches($input, (string) $rule['and_key'], (string) ($rule['and_op'] ?? 'EQ'), (string) ($rule['and_value'] ?? ''))) {
                    continue;
                }
            }
            $type = strtoupper((string) $rule['rule_type']);
            $hard = strtoupper((string) ($rule['hardness'] ?? 'HARD')) === 'HARD';
            $message = (string) $rule['message'];
            $failed = $this->failed($type, $rule, $input);
            if ($type === 'ENGINEERING_REVIEW') {
                $engineering = true;
                $warnings[] = $message;
                continue;
            }
            if ($type === 'ELECTRICAL_REVIEW') {
                $electrical = true;
                $warnings[] = $message;
                continue;
            }
            if ($type === 'WARNING') {
                $warnings[] = $message;
                continue;
            }
            if (!$failed) {
                continue;
            }
            if ($hard) {
                $blocked = true;
                $messages[] = $message;
            } else {
                $warnings[] = $message;
            }
        }

        return [
            'blocked' => $blocked,
            'messages' => $messages,
            'warnings' => $warnings,
            'engineering' => $engineering,
            'electrical' => $electrical,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function matches(array $input, string $key, string $op, string $expected): bool
    {
        $actual = trim((string) ($input[$key] ?? ''));
        $op = strtoupper($op);
        if (in_array($op, ['GT', 'GTE', 'LT', 'LTE'], true)) {
            if (!Decimal::isNumeric($actual) || !Decimal::isNumeric($expected)) {
                return false;
            }
            $cmp = Decimal::cmp($actual, $expected);

            return match ($op) {
                'GT' => $cmp > 0,
                'GTE' => $cmp >= 0,
                'LT' => $cmp < 0,
                'LTE' => $cmp <= 0,
                default => false,
            };
        }
        if ($op === 'NEQ') {
            return strcasecmp($actual, $expected) !== 0;
        }
        if ($op === 'IN') {
            $options = array_map('trim', explode('|', $expected));

            return in_array($actual, $options, true);
        }

        return strcasecmp($actual, $expected) === 0;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $input
     */
    private function failed(string $type, array $rule, array $input): bool
    {
        $thenKey = (string) ($rule['then_key'] ?? '');
        $thenValue = (string) ($rule['then_value'] ?? '');
        $actual = trim((string) ($input[$thenKey] ?? ''));
        if ($type === 'REQUIRES') {
            return strcasecmp($actual, $thenValue) !== 0;
        }
        if ($type === 'EXCLUDES') {
            if ($thenKey === '') {
                return true;
            }

            return strcasecmp($actual, $thenValue) === 0;
        }
        if ($type === 'REQUIRES_ONE_OF' || $type === 'ALLOWS_ONLY') {
            $options = array_map('trim', explode('|', $thenValue));

            return !in_array($actual, $options, true);
        }
        $subject = $thenKey !== '' ? $actual : trim((string) ($input[(string) $rule['when_key']] ?? ''));
        if (!Decimal::isNumeric($subject) || !Decimal::isNumeric($thenValue === '' ? (string) $rule['when_value'] : $thenValue)) {
            return false;
        }
        $limit = $thenValue === '' ? (string) $rule['when_value'] : $thenValue;
        if ($type === 'MIN_VALUE' || $type === 'MIN_QUANTITY') {
            return Decimal::cmp($subject, $limit) < 0;
        }
        if ($type === 'MAX_VALUE' || $type === 'MAX_QUANTITY') {
            return Decimal::cmp($subject, $limit) > 0;
        }

        return false;
    }
}
