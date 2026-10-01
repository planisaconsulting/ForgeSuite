<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WorkshopRepository;

/**
 * Opaque scan tokens and display tracking codes.
 *
 * The QR payload is a random token. The database stores only its hash.
 * A client-supplied entity id is never used to resolve a scan.
 */
final class TrackingCodeService
{
    public function __construct(private readonly WorkshopRepository $workshop = new WorkshopRepository())
    {
    }

    /**
     * @return array{tracking_code: string, token: string, url: string}
     */
    public function ensure(string $entityType, int $entityId, string $trackingCode, int $userId): array
    {
        $entityType = strtoupper($entityType);
        $existing = $this->workshop->findCodeByEntity($entityType, $entityId);
        if ($existing === null) {
            $this->workshop->insertCode($entityType, $entityId, $trackingCode);
        } else {
            $trackingCode = (string) $existing['tracking_code'];
        }
        $tokenRow = $this->workshop->activeToken($entityType, $entityId);
        $raw = bin2hex(random_bytes(32));
        if ($tokenRow === null) {
            $this->workshop->insertToken($entityType, $entityId, hash('sha256', $raw), $userId);
        } else {
            $raw = '';
        }

        return [
            'tracking_code' => $trackingCode,
            'token' => $raw,
            'url' => $raw === '' ? '' : $this->url($raw),
        ];
    }

    /**
     * Issues a fresh token and revokes the previous one. The raw token is returned once.
     *
     * @return array{tracking_code: string, token: string, url: string}
     */
    public function issue(string $entityType, int $entityId, string $trackingCode, int $userId): array
    {
        $entityType = strtoupper($entityType);
        $existing = $this->workshop->findCodeByEntity($entityType, $entityId);
        if ($existing === null) {
            $this->workshop->insertCode($entityType, $entityId, $trackingCode);
        } else {
            $trackingCode = (string) $existing['tracking_code'];
        }
        $raw = bin2hex(random_bytes(32));
        $this->workshop->insertToken($entityType, $entityId, hash('sha256', $raw), $userId);

        return [
            'tracking_code' => $trackingCode,
            'token' => $raw,
            'url' => $this->url($raw),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $token): ?array
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = $this->workshop->tokenByHash(hash('sha256', $token));
        if ($row === null) {
            return null;
        }
        $code = $this->workshop->findCodeByEntity((string) $row['entity_type'], (int) $row['entity_id']);

        return [
            'token_id' => (int) $row['id'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => (int) $row['entity_id'],
            'tracking_code' => $code['tracking_code'] ?? '',
        ];
    }

    public function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return $this->workshop->findCode($code);
    }

    /**
     * A printed code still resolves when it has not been given a QR token yet.
     *
     * @return array<string, mixed>|null
     */
    public function locate(string $code): ?array
    {
        $known = $this->findByCode($code);
        if ($known !== null) {
            return $known;
        }
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $piece = $this->workshop->productionItemByCode($code);
        if ($piece !== null) {
            return $this->located('PRODUCTION_ITEM', (int) $piece['id'], $code);
        }
        $stock = $this->workshop->inventoryByCode($code);
        if ($stock !== null) {
            $type = strtoupper((string) $stock['inventory_type']);
            if (!in_array($type, ['ROLL', 'SHEET', 'OFFCUT'], true)) {
                $type = 'INVENTORY_ITEM';
            }

            return $this->located($type, (int) $stock['id'], $code);
        }
        $package = $this->workshop->packageByCode($code);
        if ($package !== null) {
            return $this->located('PACKAGE', (int) $package['id'], $code);
        }
        $dispatch = $this->workshop->dispatchByNumber($code);
        if ($dispatch !== null) {
            return $this->located('DISPATCH', (int) $dispatch['id'], $code);
        }
        $job = $this->workshop->jobByNumber($code);
        if ($job !== null) {
            return $this->located('JOB', (int) $job['id'], $code);
        }

        return null;
    }

    /**
     * @return array{entity_type: string, entity_id: int, tracking_code: string}
     */
    private function located(string $type, int $id, string $code): array
    {
        return [
            'entity_type' => $type,
            'entity_id' => $id,
            'tracking_code' => $code,
        ];
    }

    public function recordScan(array $resolved, string $action, int $userId, ?string $device = null, array $meta = []): void
    {
        $this->workshop->insertScan([
            'tracking_token_id' => $resolved['token_id'] ?? null,
            'tracking_code' => $resolved['tracking_code'] ?? null,
            'entity_type' => $resolved['entity_type'],
            'entity_id' => $resolved['entity_id'],
            'action' => $action,
            'user_id' => $userId > 0 ? $userId : null,
            'device_identifier' => $device,
            'metadata_json' => $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR),
        ]);
    }

    public function url(string $token): string
    {
        $base = rtrim((string) config('app.url', ''), '/');

        return $base . '/scan/' . $token;
    }
}
