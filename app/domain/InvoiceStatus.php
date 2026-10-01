<?php

declare(strict_types=1);

namespace App\Domain;

enum InvoiceStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
    case Credited = 'CREDITED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Credited => 'Credited',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Issued, self::PartiallyPaid, self::Overdue => true,
            default => false,
        };
    }

    /**
     * OVERDUE is a display of an open balance past its due date.
     * The stored status stays ISSUED or PARTIALLY_PAID so no nightly job is required.
     */
    public static function present(string $stored, string $balance, ?string $due, string $today): string
    {
        $status = self::tryFrom($stored) ?? self::Draft;
        if (!$status->isOpen()) {
            return $status->value;
        }
        if ($due !== null && $due !== '' && $due < $today && \App\Helpers\Decimal::cmp($balance, '0') > 0) {
            return self::Overdue->value;
        }

        return $status->value;
    }
}
