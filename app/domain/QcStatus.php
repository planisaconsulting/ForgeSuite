<?php

declare(strict_types=1);

namespace App\Domain;

enum QcStatus: string
{
    case Pass = 'PASS';
    case Fail = 'FAIL';
    case ReworkRequired = 'REWORK_REQUIRED';
    case NotApplicable = 'NOT_APPLICABLE';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Fail => 'Fail',
            self::ReworkRequired => 'Rework required',
            self::NotApplicable => 'Not applicable',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
