<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Quote lifecycle. CONVERTED is the hand-off to a future job card.
 * This release does not create jobs.
 */
enum QuoteStatus: string
{
    case Draft = 'DRAFT';
    case Sent = 'SENT';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Converted = 'CONVERTED';

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }
}
