<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * One locked counter per document type and year.
 * Call this inside a transaction. The row lock stops two requests
 * taking the same number.
 */
final class NumberSequenceRepository extends Repository
{
    public function allocate(string $key): int
    {
        $this->run(
            'INSERT INTO number_sequences (sequence_key, last_number) VALUES (?, 0)
             ON DUPLICATE KEY UPDATE last_number = last_number',
            [$key]
        );
        $row = $this->one(
            'SELECT last_number FROM number_sequences WHERE sequence_key = ? FOR UPDATE',
            [$key]
        );
        $next = ((int) ($row['last_number'] ?? 0)) + 1;
        $this->run(
            'UPDATE number_sequences SET last_number = ? WHERE sequence_key = ?',
            [$next, $key]
        );

        return $next;
    }
}
