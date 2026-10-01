<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Status and action names introduced for workshop execution.
 * Existing job, stock, and installation records keep their own enums.
 */
final class WorkshopCodes
{
    public const MODE_BATCH = 'BATCH';
    public const MODE_INDIVIDUAL = 'INDIVIDUAL';
    public const MODE_NONE = 'NONE';

    public const ITEM_WAITING = 'WAITING';
    public const ITEM_IN_PROGRESS = 'IN_PROGRESS';
    public const ITEM_PAUSED = 'PAUSED';
    public const ITEM_BLOCKED = 'BLOCKED';
    public const ITEM_PARTIAL = 'PARTIAL';
    public const ITEM_QC = 'QC';
    public const ITEM_QC_FAILED = 'QC_FAILED';
    public const ITEM_READY = 'READY_FOR_DISPATCH';
    public const ITEM_DAMAGED = 'DAMAGED';
    public const ITEM_MISSING = 'MISSING';
    public const ITEM_COMPLETE = 'COMPLETE';

    public const QC_PASS = 'PASS';
    public const QC_FAIL = 'FAIL';
    public const QC_NOTE = 'PASS_WITH_NOTE';

    public const DISPATCH_DRAFT = 'DRAFT';
    public const DISPATCH_PACKING = 'PACKING';
    public const DISPATCH_INCOMPLETE = 'INCOMPLETE';
    public const DISPATCH_READY = 'READY';
    public const DISPATCH_DISPATCHED = 'DISPATCHED';
    public const DISPATCH_DELIVERED = 'DELIVERED';

    public const DOC_CURRENT = 'CURRENT';
    public const DOC_SUPERSEDED = 'SUPERSEDED';
    public const DOC_CANCELLED = 'CANCELLED';
    public const DOC_ARCHIVED = 'ARCHIVED';

    public const SNAG_OPEN = 'OPEN';
    public const SNAG_PROGRESS = 'IN_PROGRESS';
    public const SNAG_RESOLVED = 'RESOLVED';
    public const SNAG_CANCELLED = 'CANCELLED';

    /** @return list<string> */
    public static function trackingModes(): array
    {
        return [self::MODE_BATCH, self::MODE_INDIVIDUAL, self::MODE_NONE];
    }

    /** @return list<string> */
    public static function pauseReasons(): array
    {
        return ['BREAK', 'MATERIAL', 'MACHINE', 'CUSTOMER', 'ARTWORK', 'OTHER'];
    }

    /** @return list<string> */
    public static function blockReasons(): array
    {
        return ['MATERIAL_SHORTAGE', 'ARTWORK_ISSUE', 'MACHINE_BREAKDOWN', 'CUSTOMER_QUERY', 'QUALITY_ISSUE', 'OTHER'];
    }

    /** @return list<string> */
    public static function dispatchTypes(): array
    {
        return ['DELIVERY', 'COLLECTION', 'INSTALLATION', 'COURIER', 'OTHER'];
    }
}
