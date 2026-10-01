<?php

declare(strict_types=1);

namespace App\Domain;

enum AcceptanceMethod: string
{
    case Signed = 'SIGNED';
    case Email = 'EMAIL';
    case Whatsapp = 'WHATSAPP';
    case Phone = 'PHONE';
    case InPerson = 'IN_PERSON';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Signed => 'Signed copy',
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Phone => 'Phone',
            self::InPerson => 'In person',
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
