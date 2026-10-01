<?php

declare(strict_types=1);

namespace App\Domain;

enum PurchaseRequestStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Ordered = 'ORDERED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Ordered => 'Ordered',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
