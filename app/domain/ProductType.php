<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * What a catalogue row is.
 *
 * A finished product is something the customer buys, such as a printed ACM
 * sign. Materials, labour, and hardware stay separate catalogue rows.
 * A recipe points at those rows. It is not itself a product.
 */
enum ProductType: string
{
    case Material = 'MATERIAL';
    case Component = 'COMPONENT';
    case Service = 'SERVICE';
    case Labour = 'LABOUR';
    case Consumable = 'CONSUMABLE';
    case Finished = 'FINISHED_PRODUCT';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Component => 'Component',
            self::Service => 'Service',
            self::Labour => 'Labour',
            self::Consumable => 'Consumable',
            self::Finished => 'Finished product',
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
