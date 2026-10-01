<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Two people saved the same quotation. The later save is refused
 * so it cannot wipe the earlier one.
 */
final class QuoteConflictException extends RuntimeException
{
}
