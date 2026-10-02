<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * A future courier connection. Sign-Forge does not call a carrier API.
 * MANUAL mode records the waybill and tracking a person typed in.
 */
interface CourierProviderInterface
{
    public function mode(): string;

    /**
     * @param array<string, mixed> $shipment
     * @return array<string, mixed>
     */
    public function createShipment(array $shipment): array;

    /**
     * @return array<string, mixed>
     */
    public function getLabel(string $waybill): array;

    /**
     * @return array<string, mixed>
     */
    public function getTracking(string $trackingNumber): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelShipment(string $waybill): array;

    /**
     * @param array<string, mixed> $shipment
     * @return array<string, mixed>
     */
    public function getQuote(array $shipment): array;
}
