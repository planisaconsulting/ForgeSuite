<?php

declare(strict_types=1);

namespace App\Domain;

enum CreditNoteStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Applied = 'APPLIED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Applied => 'Applied',
            self::Cancelled => 'Cancelled',
        };
    }
}
