<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\PlanningRepository;

/**
 * Says whether a job can move, and why it cannot.
 *
 * The result is a list of facts: artwork, materials, files, predecessors,
 * equipment, and the customer site. It is not a hidden score.
 */
final class JobReadinessService
{
    public function __construct(private readonly PlanningRepository $planning = new PlanningRepository())
    {
    }

    /**
     * Reserved quantity below the required quantity is a shortage.
     * A partial reservation is still a shortage of the balance.
     */
    public function materialState(string $required, string $reserved): string
    {
        if (!Decimal::isNumeric($required) || Decimal::cmp($required, '0') <= 0) {
            return 'NONE';
        }
        if (!Decimal::isNumeric($reserved)) {
            $reserved = '0';
        }
        if (Decimal::cmp($reserved, $required) >= 0) {
            return 'READY';
        }

        return 'SHORTAGE';
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(int $jobId): array
    {
        $job = $this->planning->job($jobId);
        if ($job === null) {
            return ['found' => false];
        }
        $artwork = $this->artworkState($job);
        $materials = $this->materialState(
            $this->planning->requiredQuantity($jobId),
            $this->planning->reservedQuantity($jobId)
        );
        $files = $artwork === 'WAITING' ? 'MISSING' : 'READY';
        $predecessors = $this->planning->openPredecessorCount($jobId);
        $equipment = $this->planning->blockedEquipment($jobId);
        $production = 'READY';
        if ($predecessors > 0) {
            $production = 'BLOCKED';
        }
        $installation = $this->installationState($job, $production);
        $reasons = [];
        if ($artwork === 'WAITING') {
            $reasons[] = 'Artwork is not approved.';
        }
        if ($materials === 'SHORTAGE') {
            $reasons[] = 'Material shortage: ' . $this->planning->reservedQuantity($jobId) . ' reserved of ' . $this->planning->requiredQuantity($jobId) . ' required.';
        }
        if ($predecessors > 0) {
            $reasons[] = $predecessors . ' earlier production step' . ($predecessors === 1 ? ' is' : 's are') . ' not complete.';
        }
        foreach ($equipment as $name) {
            $reasons[] = $name . ' is out of service.';
        }

        return [
            'found' => true,
            'job_number' => (string) $job['job_number'],
            'artwork' => $artwork,
            'materials' => $materials,
            'files' => $files,
            'production' => $equipment !== [] ? 'NOT_READY' : $production,
            'installation' => $installation,
            'reasons' => $reasons,
        ];
    }

    /**
     * @param array<string, mixed> $facts
     * @return array{status: string, reasons: list<string>}
     */
    public function health(array $facts): array
    {
        $reasons = [];
        $blocked = trim((string) ($facts['blocked_reason'] ?? ''));
        if ($blocked !== '') {
            $reasons[] = $blocked;

            return ['status' => 'BLOCKED', 'reasons' => $reasons];
        }
        $complete = !empty($facts['complete']);
        $target = (string) ($facts['target_date'] ?? '');
        if (!$complete && $target !== '' && $target < date('Y-m-d')) {
            $reasons[] = 'The internal target date ' . $target . ' has passed.';
        }
        if (($facts['materials'] ?? '') === 'SHORTAGE') {
            $reasons[] = (string) ($facts['material_reason'] ?? 'Material shortage.');
        }
        if (($facts['artwork'] ?? '') === 'WAITING') {
            $reasons[] = 'Artwork is not approved.';
        }
        $remaining = (int) ($facts['remaining_minutes'] ?? 0);
        $available = (int) ($facts['available_minutes'] ?? 0);
        if ($remaining > 0 && $available < $remaining) {
            $hours = Decimal::round(Decimal::div((string) $remaining, '60'), 1);
            $reasons[] = $hours . 'h of work remains and the open capacity before the target is shorter.';
        }
        if (!empty($facts['machine_down'])) {
            $reasons[] = (string) $facts['machine_down'];
        }
        if (!empty($facts['installation_note'])) {
            $reasons[] = (string) $facts['installation_note'];
        }
        if ($reasons === []) {
            return ['status' => 'ON_SCHEDULE', 'reasons' => []];
        }
        foreach ($reasons as $reason) {
            if (str_starts_with($reason, 'The internal target date')) {
                return ['status' => 'OVERDUE', 'reasons' => $reasons];
            }
        }

        return ['status' => 'AT_RISK', 'reasons' => $reasons];
    }

    /**
     * @return array{status: string, reasons: list<string>, readiness: array<string, mixed>}
     */
    public function jobHealth(int $jobId): array
    {
        $job = $this->planning->job($jobId);
        if ($job === null) {
            return ['status' => 'ON_SCHEDULE', 'reasons' => [], 'readiness' => ['found' => false]];
        }
        $readiness = $this->evaluate($jobId);
        $block = $this->planning->openBlock($jobId);
        $remaining = $this->planning->remainingMinutes($jobId);
        $target = (string) ($job['target_date'] ?? '');
        $available = 0;
        if ($target !== '' && $target >= date('Y-m-d')) {
            $available = $this->planning->companyMinutesBetween(date('Y-m-d'), $target);
        }
        $machine = $readiness['reasons'] !== [] ? '' : '';
        foreach ($readiness['reasons'] as $reason) {
            if (str_contains($reason, 'out of service')) {
                $machine = $reason;
            }
        }
        $installNote = '';
        if (($job['delivery_method'] ?? '') === 'INSTALLATION' && $target !== '') {
            $install = $this->planning->nextInstallationDate($jobId);
            if ($install !== null && $install < $target) {
                $installNote = 'Installation is booked on ' . $install . ', before the internal target.';
            } elseif ($install === null && $target <= date('Y-m-d', strtotime('+7 days'))) {
                $installNote = 'Installation is required and is not booked.';
            }
        }
        $health = $this->health([
            'complete' => in_array((string) $job['status'], ['COMPLETED', 'CANCELLED'], true),
            'target_date' => $target,
            'blocked_reason' => $block,
            'materials' => $readiness['materials'] ?? 'NONE',
            'material_reason' => $this->materialReason($readiness),
            'artwork' => $readiness['artwork'] ?? 'NOT_REQUIRED',
            'remaining_minutes' => $remaining,
            'available_minutes' => $available,
            'machine_down' => $machine,
            'installation_note' => $installNote,
        ]);
        $health['readiness'] = $readiness;

        return $health;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function artworkState(array $job): string
    {
        if ((int) $this->planning->artworkRequiredCount((int) $job['id']) === 0) {
            return 'NOT_REQUIRED';
        }
        if (!empty($job['artwork_override_by']) || $this->planning->approvedArtworkCount((int) $job['id']) > 0) {
            return 'READY';
        }

        return 'WAITING';
    }

    /**
     * @param array<string, mixed> $job
     */
    private function installationState(array $job, string $production): string
    {
        if ((string) ($job['delivery_method'] ?? '') !== 'INSTALLATION') {
            return 'NOT_REQUIRED';
        }
        if ($production === 'BLOCKED' || $this->planning->openStageCount((int) $job['id']) > 0) {
            return 'NOT_READY';
        }
        if ($this->planning->nextInstallationDate((int) $job['id']) === null && empty($job['installation_date'])) {
            return 'NOT_READY';
        }

        return 'READY';
    }

    /**
     * @param array<string, mixed> $readiness
     */
    private function materialReason(array $readiness): string
    {
        foreach ($readiness['reasons'] as $reason) {
            if (str_starts_with((string) $reason, 'Material shortage')) {
                return (string) $reason;
            }
        }

        return 'Material shortage.';
    }
}
