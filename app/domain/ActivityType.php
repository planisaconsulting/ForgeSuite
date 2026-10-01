<?php

declare(strict_types=1);

namespace App\Domain;

enum ActivityType: string
{
    case Call = 'CALL';
    case Email = 'EMAIL';
    case Whatsapp = 'WHATSAPP';
    case Meeting = 'MEETING';
    case SiteVisit = 'SITE_VISIT';
    case Note = 'NOTE';
    case FollowUp = 'FOLLOW_UP';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Meeting => 'Meeting',
            self::SiteVisit => 'Site visit',
            self::Note => 'Note',
            self::FollowUp => 'Follow-up',
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
