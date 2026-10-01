<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Labels can be renamed. Job, quote, invoice, and payment codes stay on the domain list.
 */
final class StatusLabelService
{
    /** @var list<string> */
    public const LOCKED = ['JOB', 'QUOTE', 'INVOICE', 'PAYMENT', 'STOCK', 'PURCHASE_ORDER'];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    public function rename(string $entityType, string $code, string $label): string
    {
        $entityType = strtoupper($entityType);
        $code = strtoupper($code);
        $label = trim($label);
        if ($label === '') {
            return 'Enter a label.';
        }
        if (!$this->platform->relabel($entityType, $code, $label)) {
            return 'That status code is not on the list. New codes are not added for locked records.';
        }

        return '';
    }
}
