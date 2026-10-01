<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * The save was refused and the transaction rolled back.
 *
 * @phpstan-consistent-constructor
 */
final class QuoteRejected extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors['_form'] ?? 'The quotation could not be saved.');
    }
}
