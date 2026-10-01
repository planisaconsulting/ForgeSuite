<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** A stock or purchasing change that should roll back the current transaction. */
final class StockRejected extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors['_form'] ?? 'That stock change was refused.');
    }
}
