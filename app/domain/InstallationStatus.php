<?php

declare(strict_types=1);

namespace App\Domain;

enum InstallationStatus: string
{
    case NotScheduled = 'NOT_SCHEDULED';
    case Scheduled = 'SCHEDULED';
    case EnRoute = 'EN_ROUTE';
    case OnSite = 'ON_SITE';
    case InProgress = 'IN_PROGRESS';
    case Complete = 'COMPLETE';
    case ReturnRequired = 'RETURN_REQUIRED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NotScheduled => 'Not scheduled',
            self::Scheduled => 'Scheduled',
            self::EnRoute => 'En route',
            self::OnSite => 'On site',
            self::InProgress => 'In progress',
            self::Complete => 'Complete',
            self::ReturnRequired => 'Return required',
            self::Cancelled => 'Cancelled',
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
