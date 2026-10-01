<?php

declare(strict_types=1);

namespace App\Domain;

enum TaskStatus: string
{
    case Todo = 'TODO';
    case Ready = 'READY';
    case InProgress = 'IN_PROGRESS';
    case Blocked = 'BLOCKED';
    case Complete = 'COMPLETE';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::Ready => 'Ready',
            self::InProgress => 'In progress',
            self::Blocked => 'Blocked',
            self::Complete => 'Complete',
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
