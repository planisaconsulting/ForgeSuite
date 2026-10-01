<?php

declare(strict_types=1);

namespace App\Domain;

enum ArtworkStatus: string
{
    case Draft = 'DRAFT';
    case InternalReview = 'INTERNAL_REVIEW';
    case SentForApproval = 'SENT_FOR_APPROVAL';
    case ChangesRequested = 'CHANGES_REQUESTED';
    case Approved = 'APPROVED';
    case Superseded = 'SUPERSEDED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InternalReview => 'Internal review',
            self::SentForApproval => 'Sent for approval',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Superseded => 'Superseded',
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
