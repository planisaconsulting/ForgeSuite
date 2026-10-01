<?php

declare(strict_types=1);

namespace App\Domain;

enum PurchaseOrderStatus: string
{
    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Approved = 'APPROVED';
    case Ordered = 'ORDERED';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Ordered => 'Ordered',
            self::PartiallyReceived => 'Partially received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Ordered, self::PartiallyReceived], true);
    }

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }
}
