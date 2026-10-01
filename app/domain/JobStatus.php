<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Operational state of a job. The allowed moves live in JobWorkflowService.
 */
enum JobStatus: string
{
    case New = 'NEW';
    case AwaitingArtwork = 'AWAITING_ARTWORK';
    case AwaitingCustomerApproval = 'AWAITING_CUSTOMER_APPROVAL';
    case ApprovedForProduction = 'APPROVED_FOR_PRODUCTION';
    case MaterialsRequired = 'MATERIALS_REQUIRED';
    case ReadyForProduction = 'READY_FOR_PRODUCTION';
    case InProduction = 'IN_PRODUCTION';
    case QualityControl = 'QUALITY_CONTROL';
    case ReadyForInstallation = 'READY_FOR_INSTALLATION';
    case InstallationScheduled = 'INSTALLATION_SCHEDULED';
    case InstallationInProgress = 'INSTALLATION_IN_PROGRESS';
    case ReadyForCollection = 'READY_FOR_COLLECTION';
    case Completed = 'COMPLETED';
    case OnHold = 'ON_HOLD';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::AwaitingArtwork => 'Awaiting artwork',
            self::AwaitingCustomerApproval => 'Awaiting customer approval',
            self::ApprovedForProduction => 'Approved for production',
            self::MaterialsRequired => 'Materials required',
            self::ReadyForProduction => 'Ready for production',
            self::InProduction => 'In production',
            self::QualityControl => 'Quality control',
            self::ReadyForInstallation => 'Ready for installation',
            self::InstallationScheduled => 'Installation scheduled',
            self::InstallationInProgress => 'Installation in progress',
            self::ReadyForCollection => 'Ready for collection',
            self::Completed => 'Completed',
            self::OnHold => 'On hold',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function productionEntry(): array
    {
        return [
            self::ApprovedForProduction->value,
            self::MaterialsRequired->value,
            self::ReadyForProduction->value,
            self::InProduction->value,
        ];
    }

    public static function isOpen(string $status): bool
    {
        return !in_array($status, [self::Completed->value, self::Cancelled->value], true);
    }
}
