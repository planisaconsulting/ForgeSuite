<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CommunicationRepository;
use App\Repositories\SystemRepository;

/**
 * Signed provider events. The same external id is stored once and does not create a second message.
 * The raw payload is not kept. A hash is enough to see that the body matched.
 */
final class WebhookService
{
    public function __construct(
        private readonly CommunicationRepository $events = new CommunicationRepository(),
        private readonly SystemRepository $system = new SystemRepository()
    ) {
    }

    /**
     * @return array{http: int, body: array<string, mixed>}
     */
    public function receive(string $provider, string $raw, string $signature): array
    {
        $provider = strtolower(preg_replace('/[^a-z0-9_-]/', '', $provider) ?? '');
        if ($provider === '') {
            return ['http' => 404, 'body' => ['ok' => false]];
        }
        $secret = $this->events->secret('webhook_secret');
        if ($secret === null) {
            return ['http' => 404, 'body' => ['ok' => false]];
        }
        $expected = hash_hmac('sha256', $raw, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['http' => 401, 'body' => ['ok' => false]];
        }
        try {
            $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['http' => 400, 'body' => ['ok' => false]];
        }
        if (!is_array($payload)) {
            return ['http' => 400, 'body' => ['ok' => false]];
        }
        $externalId = trim((string) ($payload['external_event_id'] ?? ''));
        if ($externalId === '') {
            return ['http' => 422, 'body' => ['ok' => false]];
        }
        $existing = $this->events->event($provider, $externalId);
        if ($existing !== null) {
            return ['http' => 200, 'body' => ['ok' => true, 'duplicate' => true]];
        }
        $hash = hash('sha256', $raw);
        $now = date('Y-m-d H:i:s');
        try {
            $this->events->insertEvent($provider, $externalId, (string) ($payload['event_type'] ?? 'message'), $hash, $now);
        } catch (\Throwable) {
            return ['http' => 200, 'body' => ['ok' => true, 'duplicate' => true]];
        }
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message !== '') {
            $this->system->insertCommunication([
                'channel' => strtoupper($provider) === 'WHATSAPP' ? 'WHATSAPP' : 'OTHER',
                'direction' => 'INBOUND',
                'subject' => 'Inbound ' . $provider,
                'message_summary' => mb_substr($message, 0, 500),
                'message_body' => $message,
                'status' => 'LOGGED',
                'external_message_id' => $externalId,
                'received_at' => $now,
                'sent_at' => null,
            ]);
        }

        return ['http' => 200, 'body' => ['ok' => true, 'duplicate' => false]];
    }
}
