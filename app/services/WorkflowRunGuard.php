<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Stops a workflow from handling the event its own action just raised.
 */
final class WorkflowRunGuard
{
    /** @var list<int> */
    private static array $chain = [];

    public static function depth(): int
    {
        return count(self::$chain);
    }

    /**
     * @return list<int>
     */
    public static function chain(): array
    {
        return self::$chain;
    }

    public static function origin(): ?int
    {
        return self::$chain[0] ?? null;
    }

    public static function enter(int $workflowId): ?string
    {
        if (in_array($workflowId, self::$chain, true)) {
            return 'The same workflow is already handling this chain.';
        }
        if (count(self::$chain) >= 3) {
            return 'Workflow depth is limited to three.';
        }
        self::$chain[] = $workflowId;

        return null;
    }

    public static function leave(): void
    {
        array_pop(self::$chain);
    }

    public static function reset(): void
    {
        self::$chain = [];
    }
}
