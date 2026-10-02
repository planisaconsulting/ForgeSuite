<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\CourierProviderInterface;

/**
 * Records a courier booking that a person entered.
 * It does not open a network connection and does not invent a carrier response.
 */
final class ManualCourierProvider implements CourierProviderInterface
{
    public function mode(): string
    {
        return 'MANUAL';
    }

    public function createShipment(array $shipment): array
    {
        return [
            'ok' => true,
            'mode' => 'MANUAL',
            'waybill' => (string) ($shipment['waybill_number'] ?? ''),
            'tracking_number' => (string) ($shipment['tracking_number'] ?? ''),
            'message' => 'Recorded manually. No courier API was called.',
        ];
    }

    public function getLabel(string $waybill): array
    {
        return [
            'ok' => false,
            'mode' => 'MANUAL',
            'waybill' => $waybill,
            'message' => 'This courier has no label API. Print the Sign-Forge package label.',
        ];
    }

    public function getTracking(string $trackingNumber): array
    {
        return [
            'ok' => false,
            'mode' => 'MANUAL',
            'tracking_number' => $trackingNumber,
            'events' => [],
            'message' => 'Tracking events are entered by a person, or by a verified webhook when one is configured.',
        ];
    }

    public function cancelShipment(string $waybill): array
    {
        return [
            'ok' => false,
            'mode' => 'MANUAL',
            'waybill' => $waybill,
            'message' => 'Cancel the booking with the courier, then record the cancellation here.',
        ];
    }

    public function getQuote(array $shipment): array
    {
        return [
            'ok' => false,
            'mode' => 'MANUAL',
            'message' => 'Enter the courier estimate yourself. No quote API was called.',
        ];
    }
}
