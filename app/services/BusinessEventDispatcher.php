<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Domain events are recorded once and then offered to workflows.
 * A repeated event uuid is ignored.
 */
final class BusinessEventDispatcher
{
    /**
     * @param array<string, scalar|null> $summary
     */
    public static function emit(string $eventType, string $entityType, int $entityId, ?int $actorId, array $summary = [], ?string $eventUuid = null): void
    {
        try {
            $eventType = strtoupper(trim($eventType));
            $entityType = strtoupper(trim($entityType));
            if ($eventType === '' || $entityType === '' || $entityId < 1) {
                return;
            }
            $platform = new PlatformRepository();
            $uuid = $eventUuid ?? self::uuid();
            $existing = $platform->eventByUuid($uuid);
            if ($existing !== null) {
                if ($existing['processed_at'] === null) {
                    (new WorkflowEngine())->handle((int) $existing['id']);
                }

                return;
            }
            $payload = [];
            foreach ($summary as $key => $value) {
                if (!is_string($key) || !preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                    continue;
                }
                if (is_string($value) && (str_contains(strtolower($key), 'password') || str_contains(strtolower($key), 'secret'))) {
                    continue;
                }
                $payload[$key] = is_scalar($value) || $value === null ? $value : null;
            }
            $id = $platform->insertEvent([
                'event_uuid' => $uuid,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'actor_user_id' => $actorId,
                'payload_summary_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
            (new WorkflowEngine())->handle($id);
        } catch (\Throwable $e) {
            error_log('Business event failed: ' . $e->getMessage());
        }
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
