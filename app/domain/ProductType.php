<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * What a catalogue row is.
 *
 * A later recipe (a printed Chromadek sign made of board, vinyl, laminate,
 * and labour) will point at these rows. It is not a product type of its own
 * in this phase, so stock and pricing stay attached to the thing you buy
 * or the hour you spend.
 */
enum ProductType: string
{
    case Material = 'MATERIAL';
    case Component = 'COMPONENT';
    case Service = 'SERVICE';
    case Labour = 'LABOUR';
    case Consumable = 'CONSUMABLE';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Component => 'Component',
            self::Service => 'Service',
            self::Labour => 'Labour',
            self::Consumable => 'Consumable',
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
