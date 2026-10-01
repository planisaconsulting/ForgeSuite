<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * How a customer approval was received.
 *
 * A typed name and one of these methods is a staff record of what happened.
 * It is not a cryptographic signature.
 */
enum ArtworkApprovalMethod: string
{
    case Email = 'EMAIL';
    case Whatsapp = 'WHATSAPP';
    case Signed = 'SIGNED';
    case InPerson = 'IN_PERSON';
    case Phone = 'PHONE';
    case Portal = 'PORTAL';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Signed => 'Signed copy',
            self::InPerson => 'In person',
            self::Phone => 'Phone',
            self::Portal => 'Portal',
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
