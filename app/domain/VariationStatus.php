<?php

declare(strict_types=1);

namespace App\Domain;

enum VariationStatus: string
{
    case Draft = 'DRAFT';
    case AwaitingApproval = 'AWAITING_APPROVAL';
    case Approved = 'APPROVED';
    case Declined = 'DECLINED';
    case Invoiced = 'INVOICED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingApproval => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Invoiced => 'Invoiced',
        };
    }
}
