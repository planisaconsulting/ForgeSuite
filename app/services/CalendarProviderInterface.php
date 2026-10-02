<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Renders operational dates. It does not change them.
 */
interface CalendarProviderInterface
{
    public function name(): string;

    /**
     * @param list<array<string, mixed>> $events
     */
    public function render(array $events, string $timezone): string;
}
