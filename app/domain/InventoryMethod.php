<?php

declare(strict_types=1);

namespace App\Domain;

enum InventoryMethod: string
{
    case None = 'NONE';
    case Quantity = 'QUANTITY';
    case Roll = 'ROLL';
    case Sheet = 'SHEET';
    case Length = 'LENGTH';
    case Area = 'AREA';
    case Unit = 'UNIT';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Not tracked',
            self::Quantity => 'Quantity',
            self::Roll => 'Roll',
            self::Sheet => 'Sheet',
            self::Length => 'Length',
            self::Area => 'Area',
            self::Unit => 'Unit',
        };
    }

    public function tracksIndividuals(): bool
    {
        return in_array($this, [self::Roll, self::Sheet, self::Length], true);
    }

    public function allowsWeightedAverage(): bool
    {
        return in_array($this, [self::Quantity, self::Unit, self::Area], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
