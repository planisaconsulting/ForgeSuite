<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FieldRepository;

/**
 * A trusted device is a name and a sync identity. It does not replace the password.
 */
final class DeviceService
{
    public function __construct(
        private readonly FieldRepository $fields = new FieldRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error_code: string|null, message: string, id: int|null}
     */
    public function register(int $userId, array $input): array
    {
        if (!can('device.manage_own') && !can('mobile.use')) {
            return $this->fail('PERMISSION_CHANGED', 'Your access to this record changed.');
        }
        $uuid = strtolower(trim((string) ($input['device_uuid'] ?? '')));
        if (!self::uuid($uuid)) {
            return $this->fail('INVALID', 'That update is not valid.');
        }
        $name = $this->name((string) ($input['device_name'] ?? ''));
        if ($name === '') {
            return $this->fail('INVALID', 'Name this device.');
        }
        $existing = $this->fields->deviceByUuid($uuid);
        if ($existing !== null && (int) $existing['user_id'] !== $userId) {
            return $this->fail('INVALID', 'That device is registered to someone else.');
        }
        if ($existing !== null && $existing['revoked_at'] !== null) {
            return $this->fail('DEVICE_REVOKED', 'This device was revoked.');
        }
        if ($existing === null) {
            $id = $this->fields->insertDevice([
                'user_id' => $userId,
                'device_uuid' => $uuid,
                'device_name' => $name,
                'platform' => $this->short($input['platform'] ?? null, 40),
                'app_version' => $this->short($input['app_version'] ?? null, 20),
                'sw_version' => $this->short($input['sw_version'] ?? null, 40),
                'trusted' => 0,
            ]);
            $this->audit->record('device', $id, 'DEVICE_REGISTERED', null, ['device_name' => $name], $userId);

            return ['ok' => true, 'error_code' => null, 'message' => 'Device registered.', 'id' => $id];
        }
        $this->fields->renameDevice((int) $existing['id'], $name);
        $this->fields->touchDevice((int) $existing['id'], [
            'app_version' => $this->short($input['app_version'] ?? null, 20),
            'sw_version' => $this->short($input['sw_version'] ?? null, 40),
            'pending_count' => null,
            'platform' => $this->short($input['platform'] ?? null, 40),
        ]);

        return ['ok' => true, 'error_code' => null, 'message' => 'Device updated.', 'id' => (int) $existing['id']];
    }

    public function rename(int $id, int $userId, string $name): string
    {
        $device = $this->fields->device($id);
        if ($device === null || !$this->owns($device, $userId)) {
            return 'That device was not found.';
        }
        $clean = $this->name($name);
        if ($clean === '') {
            return 'Name this device.';
        }
        $this->fields->renameDevice($id, $clean);

        return '';
    }

    public function revoke(int $id, int $userId): string
    {
        $device = $this->fields->device($id);
        if ($device === null || !$this->owns($device, $userId)) {
            return 'That device was not found.';
        }
        $this->fields->revokeDevice($id);
        $this->fields->deactivateSubscriptions($id);
        $this->audit->record('device', $id, 'DEVICE_REVOKED', null, ['device_name' => $device['device_name']], $userId);

        return '';
    }

    /**
     * @param array<string, mixed> $device
     */
    private function owns(array $device, int $userId): bool
    {
        return (int) $device['user_id'] === $userId || can('device.manage_all');
    }

    private function name(string $value): string
    {
        $value = trim(strip_tags($value));

        return mb_substr($value, 0, 80);
    }

    private function short(mixed $value, int $length): ?string
    {
        $text = trim(strip_tags((string) $value));

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    public static function uuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value);
    }

    /**
     * @return array{ok: bool, error_code: string, message: string, id: null}
     */
    private function fail(string $code, string $message): array
    {
        return ['ok' => false, 'error_code' => $code, 'message' => $message, 'id' => null];
    }
}
