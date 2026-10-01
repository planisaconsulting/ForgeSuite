<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Decides whether a resource can take a piece of work at a given time.
 *
 * Inactive resources and double bookings are hard stops. Leave, holidays,
 * working hours, dependencies, artwork, and material shortages can be
 * overridden by an authorised planner who records a reason.
 */
final class SchedulingConflictService
{
    /**
     * @param array<string, mixed> $facts
     * @return array{blocked: bool, hard: bool, overridable: bool, code: string, reasons: list<array{code: string, message: string, overridable: bool}>}
     */
    public function assess(array $facts): array
    {
        $reasons = [];
        if (empty($facts['exists'])) {
            $reasons[] = $this->reason('MISSING', 'That resource was not found.', false);
        }
        if (!empty($facts['exists']) && empty($facts['active'])) {
            $reasons[] = $this->reason('INACTIVE', 'This resource is inactive.', false);
        }
        $status = strtoupper((string) ($facts['status'] ?? 'AVAILABLE'));
        if ($status === 'OUT_OF_SERVICE') {
            $reasons[] = $this->reason('OUT_OF_SERVICE', 'This resource is out of service.', false);
        }
        if ($status === 'RETIRED') {
            $reasons[] = $this->reason('RETIRED', 'This resource is retired.', false);
        }
        $capacity = max(1, (int) ($facts['concurrent_capacity'] ?? 1));
        if ((int) ($facts['overlaps'] ?? 0) >= $capacity) {
            $reasons[] = $this->reason('CONFLICT', 'This resource is already booked for that time.', false);
        }
        $unavailable = strtoupper((string) ($facts['unavailable_reason'] ?? ''));
        if ($unavailable !== '') {
            $label = strtolower(str_replace('_', ' ', $unavailable));
            $reasons[] = $this->reason($unavailable, 'This resource is unavailable (' . $label . ').', true);
        }
        if (!empty($facts['non_working_day'])) {
            $name = trim((string) ($facts['exception_name'] ?? ''));
            $reasons[] = $this->reason(
                'NON_WORKING_DAY',
                $name !== '' ? $name . ' is a non-working day.' : 'That date is a non-working day.',
                true
            );
        }
        if (!empty($facts['outside_hours'])) {
            $reasons[] = $this->reason('OUTSIDE_HOURS', 'That time is outside working hours.', true);
        }
        if (!empty($facts['dependency_open'])) {
            $reasons[] = $this->reason(
                'DEPENDENCY',
                (string) ($facts['dependency_message'] ?? 'A previous step is not complete.'),
                true
            );
        }
        if (!empty($facts['artwork_blocked'])) {
            $reasons[] = $this->reason('ARTWORK', 'Artwork is not approved, so production cannot be confirmed.', true);
        }
        if (!empty($facts['material_shortage'])) {
            $reasons[] = $this->reason('MATERIAL_SHORTAGE', 'Materials are short. Do not start production until this is resolved or overridden.', true);
        }

        $hard = false;
        foreach ($reasons as $reason) {
            if (!$reason['overridable']) {
                $hard = true;
            }
        }

        return [
            'blocked' => $reasons !== [],
            'hard' => $hard,
            'overridable' => $reasons !== [] && !$hard,
            'code' => $reasons[0]['code'] ?? 'OK',
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{code: string, message: string, overridable: bool}
     */
    private function reason(string $code, string $message, bool $overridable): array
    {
        return ['code' => $code, 'message' => $message, 'overridable' => $overridable];
    }
}
