<?php

declare(strict_types=1);

namespace App\Services;

final class FinanceRejected extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct((string) (reset($errors) ?: 'Finance action refused.'));
    }
}
