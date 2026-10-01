<?php

declare(strict_types=1);

namespace App\Domain;

enum JobItemStatus: string
{
    case NotStarted = 'NOT_STARTED';
    case Artwork = 'ARTWORK';
    case Ready = 'READY';
    case InProduction = 'IN_PRODUCTION';
    case Qc = 'QC';
    case Complete = 'COMPLETE';
    case OnHold = 'ON_HOLD';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Artwork => 'Artwork',
            self::Ready => 'Ready',
            self::InProduction => 'In production',
            self::Qc => 'Quality control',
            self::Complete => 'Complete',
            self::OnHold => 'On hold',
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
