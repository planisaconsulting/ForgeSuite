<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Ledger movement. Quantity is stored signed: receipts are positive,
 * issues are negative. A correction is a new row, not an edit.
 */
enum MovementType: string
{
    case OpeningBalance = 'OPENING_BALANCE';
    case PurchaseReceipt = 'PURCHASE_RECEIPT';
    case JobConsumption = 'JOB_CONSUMPTION';
    case JobReturn = 'JOB_RETURN';
    case Waste = 'WASTE';
    case TransferIn = 'TRANSFER_IN';
    case TransferOut = 'TRANSFER_OUT';
    case AdjustmentIn = 'ADJUSTMENT_IN';
    case AdjustmentOut = 'ADJUSTMENT_OUT';
    case StockCountCorrection = 'STOCK_COUNT_CORRECTION';
    case SupplierReturn = 'SUPPLIER_RETURN';
    case OffcutCreated = 'OFFCUT_CREATED';
    case OffcutConsumed = 'OFFCUT_CONSUMED';
    case Damage = 'DAMAGE';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Opening balance',
            self::PurchaseReceipt => 'Purchase receipt',
            self::JobConsumption => 'Job consumption',
            self::JobReturn => 'Job return',
            self::Waste => 'Waste',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
            self::AdjustmentIn => 'Adjustment in',
            self::AdjustmentOut => 'Adjustment out',
            self::StockCountCorrection => 'Stock count correction',
            self::SupplierReturn => 'Supplier return',
            self::OffcutCreated => 'Offcut created',
            self::OffcutConsumed => 'Offcut consumed',
            self::Damage => 'Damage',
            self::Other => 'Other',
        };
    }

    public function increasesStock(): bool
    {
        return in_array($this, [
            self::OpeningBalance,
            self::PurchaseReceipt,
            self::JobReturn,
            self::TransferIn,
            self::AdjustmentIn,
            self::OffcutCreated,
        ], true);
    }

    public function decreasesStock(): bool
    {
        return in_array($this, [
            self::JobConsumption,
            self::Waste,
            self::TransferOut,
            self::AdjustmentOut,
            self::SupplierReturn,
            self::OffcutConsumed,
            self::Damage,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
