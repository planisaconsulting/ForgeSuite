<?php

declare(strict_types=1);

namespace App\Domain;

enum DeliveryMethod: string
{
    case Installation = 'INSTALLATION';
    case Collection = 'COLLECTION';
    case Delivery = 'DELIVERY';
    case Courier = 'COURIER';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Installation => 'Installation',
            self::Collection => 'Collection',
            self::Delivery => 'Delivery',
            self::Courier => 'Courier',
            self::Other => 'Other',
        };
    }

    public function needsInstallation(): bool
    {
        return $this === self::Installation;
    }

    public static function normalise(string $value): string
    {
        $value = strtoupper(trim($value));

        return self::tryFrom($value)?->value ?? self::Installation->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
