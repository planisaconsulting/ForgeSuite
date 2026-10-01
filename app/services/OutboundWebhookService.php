<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForecastRepository;

/**
 * Outbound events are signed and retried.
 * A failed delivery does not roll back the business record that raised it.
 */
final class OutboundWebhookService
{
    /** @var list<int> */
    private const RETRY_MINUTES = [1, 5, 30, 120];

    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function emit(string $eventType, string $entityType, int $entityId, array $data): string
    {
        $eventId = bin2hex(random_bytes(16));
        $payload = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'occurred_at' => date('c'),
            'data' => $data,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach ($this->planning->activeWebhooks() as $subscription) {
            $types = json_decode((string) ($subscription['event_types_json'] ?? '[]'), true);
            if (is_array($types) && $types !== [] && !in_array($eventType, $types, true)) {
                continue;
            }
            $this->deliver((int) $subscription['id'], (string) $subscription['endpoint_url'], (string) $subscription['secret'], $eventId, $eventType, $body, 1);
        }

        return $eventId;
    }

    public function processDue(): int
    {
        $count = 0;
        foreach ($this->planning->dueDeliveries() as $row) {
            if ((int) $row['subscription_active'] !== 1) {
                continue;
            }
            $this->deliver(
                (int) $row['subscription_id'],
                (string) $row['endpoint_url'],
                (string) $row['secret'],
                (string) $row['event_id'],
                (string) $row['event_type'],
                (string) $row['payload_json'],
                (int) $row['attempt'] + 1,
                (int) $row['id']
            );
            $count++;
        }

        return $count;
    }

    private function deliver(
        int $subscriptionId,
        string $url,
        string $secret,
        string $eventId,
        string $eventType,
        string $body,
        int $attempt,
        ?int $existingId = null
    ): void {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $result = $this->post($url, $body, $signature, $timestamp, $eventId);
        $status = $result['ok'] ? 'DELIVERED' : ($attempt >= count(self::RETRY_MINUTES) + 1 ? 'FAILED' : 'RETRY');
        $next = null;
        if ($status === 'RETRY') {
            $minutes = self::RETRY_MINUTES[min($attempt - 1, count(self::RETRY_MINUTES) - 1)];
            $next = date('Y-m-d H:i:s', time() + ($minutes * 60));
        }
        $fields = [
            'attempt' => $attempt,
            'status' => $status,
            'response_code' => $result['code'],
            'error_message' => $result['error'],
            'next_retry_at' => $next,
        ];
        if ($existingId === null) {
            $this->planning->insertDelivery([
                'subscription_id' => $subscriptionId,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'attempt' => $attempt,
                'status' => $status,
                'response_code' => $result['code'],
                'error_message' => $result['error'],
                'payload_json' => $body,
                'next_retry_at' => $next,
            ]);

            return;
        }
        $this->planning->updateDelivery($existingId, $fields);
    }

    /**
     * @return array{ok: bool, code: int|null, error: string|null}
     */
    private function post(string $url, string $body, string $signature, string $timestamp, string $eventId): array
    {
        if (str_starts_with($url, 'test://fail')) {
            return ['ok' => false, 'code' => 500, 'error' => 'Receiver failed'];
        }
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            return ['ok' => false, 'code' => null, 'error' => 'Endpoint must be an http address'];
        }
        $headers = [
            'Content-Type: application/json',
            'X-SignForge-Event: ' . $eventId,
            'X-SignForge-Timestamp: ' . $timestamp,
            'X-SignForge-Signature: ' . $signature,
        ];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $code = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $header, $match)) {
                $code = (int) $match[1];
            }
        }
        if ($response === false || $code < 200 || $code >= 300) {
            return ['ok' => false, 'code' => $code > 0 ? $code : null, 'error' => 'Delivery was not accepted'];
        }

        return ['ok' => true, 'code' => $code, 'error' => null];
    }
}
