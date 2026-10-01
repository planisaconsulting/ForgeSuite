<?php

declare(strict_types=1);

namespace App\Domain;

enum OpportunityStatus: string
{
    case New = 'NEW';
    case Contacted = 'CONTACTED';
    case Qualified = 'QUALIFIED';
    case Quoted = 'QUOTED';
    case Won = 'WON';
    case Lost = 'LOST';
    case OnHold = 'ON_HOLD';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Quoted => 'Quoted',
            self::Won => 'Won',
            self::Lost => 'Lost',
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
