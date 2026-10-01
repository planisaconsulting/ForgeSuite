<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Quote lifecycle.
 *
 * DRAFT can be edited. READY and SENT are issued documents: a later edit
 * starts a new revision and leaves the issued one read-only.
 * VIEWED is set by a person, not by guessing that a customer opened a file.
 * CONVERTED means a job record now points at the accepted revision.
 */
enum QuoteStatus: string
{
    case Draft = 'DRAFT';
    case Ready = 'READY';
    case Sent = 'SENT';
    case Viewed = 'VIEWED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Superseded = 'SUPERSEDED';
    case Converted = 'CONVERTED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Superseded => 'Superseded',
            self::Converted => 'Converted',
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
