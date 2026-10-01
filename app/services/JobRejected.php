<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A job change was refused. The transaction that threw this rolls back.
 */
final class JobRejected extends \RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors['_form'] ?? 'The job could not be saved.');
    }
}
