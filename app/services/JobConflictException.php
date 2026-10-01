<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Two people saved the same job record. The later save is refused.
 */
final class JobConflictException extends RuntimeException
{
}
