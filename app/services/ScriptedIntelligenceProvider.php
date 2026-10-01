<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic stand-in. It never receives a command channel back into the ERP.
 */
final class ScriptedIntelligenceProvider
{
    public static int $calls = 0;

    public static string $lastInput = '';

    /**
     * @return array{text: string, tokens: int|null, cost: float|null}
     */
    public function complete(string $task, string $data): array
    {
        self::$calls++;
        self::$lastInput = $data;

        return [
            'text' => 'Draft only. ' . SafeValue::summary($data, 180),
            'tokens' => null,
            'cost' => null,
        ];
    }
}
