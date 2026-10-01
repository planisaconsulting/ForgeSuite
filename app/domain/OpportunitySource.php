<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Where an enquiry came from. Add a case here when a new channel matters.
 */
enum OpportunitySource: string
{
    case WalkIn = 'WALK_IN';
    case Phone = 'PHONE';
    case Email = 'EMAIL';
    case Whatsapp = 'WHATSAPP';
    case Facebook = 'FACEBOOK';
    case Website = 'WEBSITE';
    case Referral = 'REFERRAL';
    case ReturnCustomer = 'RETURN_CUSTOMER';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Facebook => 'Facebook',
            self::Website => 'Website',
            self::Referral => 'Referral',
            self::ReturnCustomer => 'Return customer',
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
