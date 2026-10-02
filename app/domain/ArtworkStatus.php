<?php

declare(strict_types=1);

namespace App\Domain;

enum ArtworkStatus: string
{
    case Draft = 'DRAFT';
    case InDesign = 'IN_DESIGN';
    case InternalReview = 'INTERNAL_REVIEW';
    case SentForApproval = 'SENT_FOR_APPROVAL';
    case CustomerReview = 'CUSTOMER_REVIEW';
    case ChangesRequested = 'CHANGES_REQUESTED';
    case Approved = 'APPROVED';
    case ProductionPrep = 'PRODUCTION_PREP';
    case ApprovedForProduction = 'APPROVED_FOR_PRODUCTION';
    case Superseded = 'SUPERSEDED';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InDesign => 'In design',
            self::InternalReview => 'Internal review',
            self::SentForApproval => 'Sent for approval',
            self::CustomerReview => 'Customer review',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::ProductionPrep => 'Production preparation',
            self::ApprovedForProduction => 'Approved for production',
            self::Superseded => 'Superseded',
            self::Archived => 'Archived',
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
