<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;

/**
 * Warranties are snapshots taken when the asset is installed.
 * A later catalogue change does not rewrite them.
 * Coverage is a date fact. A person confirms the claim.
 */
final class WarrantyService
{
    public const TYPES = ['SIGN_FORGE_WORKMANSHIP', 'PRODUCT_MATERIAL', 'MANUFACTURER', 'INSTALLATION', 'CUSTOM'];

    public const CLAIM_STATUSES = ['NEW', 'ASSESSING', 'AWAITING_SUPPLIER', 'APPROVED', 'REJECTED', 'REPAIR_IN_PROGRESS', 'RESOLVED', 'CLOSED'];

    public function __construct(
        private readonly AssetRepository $assets = new AssetRepository(),
        private readonly NumberingService $numbers = new NumberingService()
    ) {
    }

    /**
     * Inclusive end date: start plus N calendar months, minus one day.
     * 1 January 2027 plus 24 months ends on 31 December 2028.
     * The last covered day is the end date. The next day is expired.
     */
    public static function inclusiveEnd(string $start, int $months): string
    {
        $origin = new \DateTimeImmutable($start);

        return $origin->modify('+' . $months . ' months')->modify('-1 day')->format('Y-m-d');
    }

    public static function covers(string $start, string $end, string $asOf): bool
    {
        return $asOf >= $start && $asOf <= $end;
    }

    public static function statusAt(string $start, string $end, string $asOf, string $stored, int $alertDays = 30): string
    {
        if (in_array($stored, ['VOID', 'CLAIM_OPEN', 'CLAIMED'], true)) {
            return $stored;
        }
        if ($asOf > $end) {
            return 'EXPIRED';
        }
        $soon = (new \DateTimeImmutable($asOf))->modify('+' . max(1, $alertDays) . ' days')->format('Y-m-d');
        if ($asOf >= $start && $end <= $soon) {
            return 'EXPIRING';
        }

        return 'ACTIVE';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function add(int $assetId, array $input, int $userId): array
    {
        if (!can('assets.manage_warranties')) {
            return ['errors' => ['_form' => 'You cannot manage warranties.'], 'id' => null];
        }
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            return ['errors' => ['_form' => 'That asset was not found.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['warranty_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            return ['errors' => ['warranty_type' => 'Choose a warranty type.'], 'id' => null];
        }
        $start = trim((string) ($input['start_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            return ['errors' => ['start_date' => 'Start date is required.'], 'id' => null];
        }
        $end = trim((string) ($input['end_date'] ?? ''));
        $months = (int) ($input['months'] ?? 0);
        if ($months > 0) {
            $end = self::inclusiveEnd($start, $months);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) {
            return ['errors' => ['end_date' => 'End date must be on or after the start date.'], 'id' => null];
        }
        $provider = strtoupper(trim((string) ($input['provider_type'] ?? 'SIGN_FORGE')));
        if (!in_array($provider, ['SIGN_FORGE', 'MANUFACTURER', 'SUPPLIER', 'CUSTOM'], true)) {
            $provider = 'SIGN_FORGE';
        }
        $componentId = (int) ($input['asset_component_id'] ?? 0);
        if ($componentId > 0) {
            $component = $this->assets->component($componentId);
            if ($component === null || (int) $component['asset_id'] !== $assetId) {
                return ['errors' => ['asset_component_id' => 'That component is not on this asset.'], 'id' => null];
            }
        }
        $id = 0;
        Database::transaction(function () use ($assetId, $input, $userId, $type, $provider, $start, $end, $componentId, &$id): void {
            $id = $this->assets->insertWarranty([
                'asset_id' => $assetId,
                'asset_component_id' => $componentId > 0 ? $componentId : null,
                'warranty_type' => $type,
                'provider_type' => $provider,
                'supplier_id' => null,
                'provider_name' => blank_to_null($input['provider_name'] ?? null),
                'start_date' => $start,
                'end_date' => $end,
                'terms' => blank_to_null($input['terms'] ?? null),
                'exclusions' => blank_to_null($input['exclusions'] ?? null),
                'status' => 'ACTIVE',
            ]);
            $this->refreshSpan($assetId);
            $this->assets->insertEvent([
                'asset_id' => $assetId,
                'event_type' => 'WARRANTY',
                'summary' => $type . ' warranty ' . $start . ' to ' . $end,
                'related_type' => 'asset_warranty',
                'related_id' => $id,
                'happened_at' => $start . ' 00:00:00',
                'created_by' => $userId,
            ]);
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function withStatus(int $assetId, string $asOf): array
    {
        $alert = (int) SettingsService::get('warranty_alert_days', '30');
        $rows = [];
        foreach ($this->assets->warranties($assetId) as $row) {
            $row['derived_status'] = self::statusAt(
                (string) $row['start_date'],
                (string) $row['end_date'],
                $asOf,
                (string) $row['status'],
                $alert
            );
            $row['likely_active'] = self::covers((string) $row['start_date'], (string) $row['end_date'], $asOf)
                && !in_array((string) $row['status'], ['VOID'], true);
            $rows[] = $row;
        }

        return $rows;
    }

    public function anyLikelyActive(int $assetId, string $asOf): bool
    {
        foreach ($this->withStatus($assetId, $asOf) as $row) {
            if ($row['likely_active']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function openClaim(int $assetId, array $input, int $userId): array
    {
        if (!can('warranty_claims.manage')) {
            return ['errors' => ['_form' => 'You cannot open a warranty claim.'], 'id' => null];
        }
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            return ['errors' => ['_form' => 'That asset was not found.'], 'id' => null];
        }
        $failure = trim((string) ($input['failure_description'] ?? ''));
        if ($failure === '') {
            return ['errors' => ['failure_description' => 'Describe the failure.'], 'id' => null];
        }
        $warrantyId = (int) ($input['warranty_id'] ?? 0);
        if ($warrantyId > 0) {
            $warranty = $this->assets->warranty($warrantyId);
            if ($warranty === null || (int) $warranty['asset_id'] !== $assetId) {
                return ['errors' => ['warranty_id' => 'That warranty is not on this asset.'], 'id' => null];
            }
        }
        $componentId = (int) ($input['asset_component_id'] ?? 0);
        $requestId = (int) ($input['service_request_id'] ?? 0);
        $reported = trim((string) ($input['reported_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reported)) {
            $reported = date('Y-m-d');
        }
        $recovery = trim((string) ($input['cost_recovery_amount'] ?? ''));
        $recoveryValue = null;
        if ($recovery !== '') {
            if (!Decimal::isNumeric($recovery) || Decimal::cmp($recovery, '0') < 0) {
                return ['errors' => ['cost_recovery_amount' => 'Recovery amount must be zero or more.'], 'id' => null];
            }
            $recoveryValue = Decimal::money($recovery);
        }
        $id = 0;
        Database::transaction(function () use ($assetId, $input, $userId, $failure, $warrantyId, $componentId, $requestId, $reported, $recoveryValue, &$id): void {
            $id = $this->assets->insertClaim([
                'claim_number' => $this->numbers->warrantyClaim(),
                'asset_id' => $assetId,
                'asset_component_id' => $componentId > 0 ? $componentId : null,
                'warranty_id' => $warrantyId > 0 ? $warrantyId : null,
                'service_request_id' => $requestId > 0 ? $requestId : null,
                'status' => 'NEW',
                'reported_date' => $reported,
                'failure_description' => $failure,
                'assessment' => blank_to_null($input['assessment'] ?? null),
                'supplier_reference' => null,
                'cost_recovery_amount' => $recoveryValue,
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
            if ($warrantyId > 0) {
                $this->assets->setWarrantyStatus($warrantyId, 'CLAIM_OPEN');
            }
            $this->assets->insertEvent([
                'asset_id' => $assetId,
                'event_type' => 'WARRANTY_CLAIM',
                'summary' => 'Warranty claim opened',
                'related_type' => 'warranty_claim',
                'related_id' => $id,
                'happened_at' => $reported . ' 00:00:00',
                'created_by' => $userId,
            ]);
        });
        $claim = $this->assets->claim($id);
        BusinessEventDispatcher::emit('WARRANTY_CLAIM_CREATED', 'WARRANTY_CLAIM', $id, $userId, [
            'asset_id' => $assetId,
            'number' => (string) ($claim['claim_number'] ?? ''),
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function decide(int $claimId, array $input, int $userId): array
    {
        if (!can('warranty_claims.manage')) {
            return ['_form' => 'You cannot decide a warranty claim.'];
        }
        $claim = $this->assets->claim($claimId);
        if ($claim === null) {
            return ['_form' => 'That claim was not found.'];
        }
        $status = strtoupper(trim((string) ($input['status'] ?? '')));
        if (!in_array($status, self::CLAIM_STATUSES, true)) {
            return ['status' => 'Choose a claim status.'];
        }
        $recovered = trim((string) ($input['recovered_amount'] ?? (string) $claim['recovered_amount']));
        if (!Decimal::isNumeric($recovered) || Decimal::cmp($recovered, '0') < 0) {
            return ['recovered_amount' => 'Recovered amount must be zero or more.'];
        }
        $recoverable = trim((string) ($input['cost_recovery_amount'] ?? (string) ($claim['cost_recovery_amount'] ?? '')));
        $recoverableValue = $claim['cost_recovery_amount'];
        if ($recoverable !== '') {
            if (!Decimal::isNumeric($recoverable) || Decimal::cmp($recoverable, '0') < 0) {
                return ['cost_recovery_amount' => 'Recoverable amount must be zero or more.'];
            }
            $recoverableValue = Decimal::money($recoverable);
        }
        $this->assets->updateClaim($claimId, [
            'status' => $status,
            'assessment' => blank_to_null($input['assessment'] ?? $claim['assessment']),
            'claim_outcome' => blank_to_null($input['claim_outcome'] ?? $claim['claim_outcome']),
            'supplier_reference' => blank_to_null($input['supplier_reference'] ?? $claim['supplier_reference']),
            'cost_recovery_amount' => $recoverableValue,
            'recovered_amount' => Decimal::money($recovered),
            'notes' => blank_to_null($input['notes'] ?? $claim['notes']),
        ]);
        if (in_array($status, ['RESOLVED', 'CLOSED'], true) && $claim['warranty_id'] !== null) {
            $this->assets->setWarrantyStatus((int) $claim['warranty_id'], 'CLAIMED');
        }
        if (in_array($status, ['RESOLVED', 'CLOSED'], true)) {
            BusinessEventDispatcher::emit('WARRANTY_CLAIM_RESOLVED', 'WARRANTY_CLAIM', $claimId, $userId, [
                'status' => $status,
            ]);
        }

        return [];
    }

    public function refreshSpan(int $assetId): void
    {
        $rows = $this->assets->warranties($assetId);
        if ($rows === []) {
            return;
        }
        $start = null;
        $end = null;
        foreach ($rows as $row) {
            if ((string) $row['warranty_type'] !== 'SIGN_FORGE_WORKMANSHIP') {
                continue;
            }
            $start = (string) $row['start_date'];
            $end = (string) $row['end_date'];
        }
        if ($start === null) {
            $start = (string) $rows[0]['start_date'];
            $end = (string) $rows[0]['end_date'];
        }
        $this->assets->setWarrantySpan($assetId, $start, $end);
    }

    /**
     * @return array{notified: int}
     */
    public function notifyExpiring(): array
    {
        $notified = 0;
        $notes = new NotificationService();
        $role = $this->assets->rowsForReport("SELECT id FROM roles WHERE code = 'MANAGEMENT' LIMIT 1");
        $roleId = (int) ($role[0]['id'] ?? 0);
        foreach ([90, 30, 7] as $days) {
            $day = (new \DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');
            $rows = $this->assets->rowsForReport(
                'SELECT w.id, w.asset_id, a.asset_number FROM asset_warranties w
                 JOIN customer_assets a ON a.id = w.asset_id
                 WHERE w.end_date = ? AND w.status NOT IN (\'VOID\',\'EXPIRED\')',
                [$day]
            );
            foreach ($rows as $row) {
                $sent = $notes->send(
                    null,
                    $roleId > 0 ? $roleId : null,
                    'WARRANTY_ALERT',
                    'Warranty expiring',
                    (string) $row['asset_number'] . ' warranty ends in ' . $days . ' days.',
                    'customer_asset',
                    (int) $row['asset_id'],
                    'NORMAL',
                    'warranty-expiring:' . $row['id'] . ':' . $days
                );
                if ($sent) {
                    $notified++;
                    $event = $days === 0 ? 'ASSET_WARRANTY_EXPIRED' : 'ASSET_WARRANTY_EXPIRING';
                    BusinessEventDispatcher::emit($event, 'ASSET', (int) $row['asset_id'], null, [
                        'days' => $days,
                        'warranty_id' => (int) $row['id'],
                    ]);
                }
            }
        }

        return ['notified' => $notified];
    }
}
