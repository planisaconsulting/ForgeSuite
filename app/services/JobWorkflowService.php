<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DeliveryMethod;
use App\Domain\JobStatus;

/**
 * Decides which job status changes are allowed.
 *
 * Collection, delivery, and courier jobs do not have to pass through
 * installation. A completed or cancelled job stays locked unless someone
 * with jobs.reopen opens it again.
 */
final class JobWorkflowService
{
    /**
     * @param array<string, mixed> $job
     * @param array{artwork_blocking: bool, items_open: bool, qc_blocking: bool, installation_open: bool} $checks
     * @return array{ok: bool, errors: array<string, string>, artwork_override: bool, completion_override: bool}
     */
    public function evaluate(array $job, string $to, string $notes, string $overrideReason, array $checks): array
    {
        $from = (string) $job['status'];
        $to = strtoupper(trim($to));
        if (!in_array($to, JobStatus::values(), true)) {
            return $this->refuse('That status is not valid.');
        }
        if ($from === $to) {
            return $this->refuse('The job is already in that status.');
        }
        if (!$this->allowed($from, $to, (string) ($job['delivery_method'] ?? 'INSTALLATION'))) {
            return $this->refuse('That status change is not available from ' . $from . '.');
        }
        if (in_array($from, [JobStatus::Completed->value, JobStatus::Cancelled->value], true) && !can('jobs.reopen')) {
            return $this->refuse('This job is locked. Reopening it needs authorisation.');
        }
        if ($from === JobStatus::Cancelled->value && !can('jobs.reopen')) {
            return $this->refuse('A cancelled job stays locked.');
        }

        $artworkOverride = false;
        $completionOverride = false;
        $enteringProduction = in_array($to, JobStatus::productionEntry(), true)
            && !in_array($from, JobStatus::productionEntry(), true);
        if ($enteringProduction && $checks['artwork_blocking']) {
            if (trim($overrideReason) === '' || !can('artwork.override_approval')) {
                return $this->refuse('Artwork has not been approved by the customer.');
            }
            $artworkOverride = true;
        }
        if ($to === JobStatus::Completed->value) {
            $warnings = [];
            if ($checks['items_open']) {
                $warnings[] = 'Some production items are not complete.';
            }
            if ($checks['qc_blocking']) {
                $warnings[] = 'Quality control still has a failure or rework.';
            }
            if ($checks['artwork_blocking']) {
                $warnings[] = 'Artwork has not been approved by the customer.';
            }
            if ($checks['installation_open']) {
                $warnings[] = 'Installation is not complete.';
            }
            if (!empty($checks['fulfilment_open'])) {
                $warnings[] = 'Fulfilment is not complete.';
            }
            if ($warnings !== []) {
                if (trim($overrideReason) === '' || !can('jobs.complete')) {
                    return $this->refuse(implode(' ', $warnings));
                }
                $completionOverride = true;
            }
        }

        return [
            'ok' => true,
            'errors' => [],
            'artwork_override' => $artworkOverride,
            'completion_override' => $completionOverride,
        ];
    }

    /**
     * @return list<string>
     */
    public function choices(string $from, string $delivery): array
    {
        $from = strtoupper($from);
        $allowed = $this->map($delivery)[$from] ?? [];
        if (in_array($from, [JobStatus::Completed->value, JobStatus::Cancelled->value], true) && can('jobs.reopen')) {
            $allowed[] = JobStatus::InProduction->value;
        }

        return array_values(array_unique($allowed));
    }

    private function allowed(string $from, string $to, string $delivery): bool
    {
        if (in_array($to, $this->choices($from, $delivery), true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function map(string $delivery): array
    {
        $method = DeliveryMethod::tryFrom($delivery) ?? DeliveryMethod::Installation;
        $afterQc = $method->needsInstallation()
            ? [JobStatus::ReadyForInstallation->value, JobStatus::InProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value]
            : [JobStatus::ReadyForCollection->value, JobStatus::InProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value];
        if ($method === DeliveryMethod::Other) {
            $afterQc = [
                JobStatus::ReadyForInstallation->value,
                JobStatus::ReadyForCollection->value,
                JobStatus::InProduction->value,
                JobStatus::OnHold->value,
                JobStatus::Cancelled->value,
            ];
        }
        $holdResume = [
            JobStatus::New->value,
            JobStatus::AwaitingArtwork->value,
            JobStatus::AwaitingCustomerApproval->value,
            JobStatus::ReadyForProduction->value,
            JobStatus::InProduction->value,
            JobStatus::Cancelled->value,
        ];

        return [
            JobStatus::New->value => [JobStatus::AwaitingArtwork->value, JobStatus::ApprovedForProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::AwaitingArtwork->value => [JobStatus::AwaitingCustomerApproval->value, JobStatus::ApprovedForProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::AwaitingCustomerApproval->value => [JobStatus::AwaitingArtwork->value, JobStatus::ApprovedForProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::ApprovedForProduction->value => [JobStatus::MaterialsRequired->value, JobStatus::ReadyForProduction->value, JobStatus::InProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::MaterialsRequired->value => [JobStatus::ReadyForProduction->value, JobStatus::InProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::ReadyForProduction->value => [JobStatus::InProduction->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::InProduction->value => [JobStatus::QualityControl->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::QualityControl->value => $afterQc,
            JobStatus::ReadyForInstallation->value => [JobStatus::InstallationScheduled->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::InstallationScheduled->value => [JobStatus::InstallationInProgress->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::InstallationInProgress->value => [JobStatus::Completed->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::ReadyForCollection->value => [JobStatus::Completed->value, JobStatus::OnHold->value, JobStatus::Cancelled->value],
            JobStatus::OnHold->value => $holdResume,
            JobStatus::Completed->value => [],
            JobStatus::Cancelled->value => [],
        ];
    }

    /**
     * @return array{ok: bool, errors: array<string, string>, artwork_override: bool, completion_override: bool}
     */
    private function refuse(string $message): array
    {
        return [
            'ok' => false,
            'errors' => ['_form' => $message],
            'artwork_override' => false,
            'completion_override' => false,
        ];
    }
}
