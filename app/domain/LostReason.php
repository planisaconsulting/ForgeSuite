<?php

declare(strict_types=1);

namespace App\Domain;

enum LostReason: string
{
    case Price = 'PRICE';
    case Competitor = 'COMPETITOR';
    case NoResponse = 'NO_RESPONSE';
    case ProjectCancelled = 'PROJECT_CANCELLED';
    case Timing = 'TIMING';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Price',
            self::Competitor => 'Competitor',
            self::NoResponse => 'No response',
            self::ProjectCancelled => 'Project cancelled',
            self::Timing => 'Timing',
            self::Other => 'Other',
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
