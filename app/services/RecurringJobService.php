<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\PlanningRepository;

/**
 * Creates one follow-up for each due period.
 *
 * A job still has to come from an accepted quote, so a template creates a
 * follow-up rather than an invoice or a second job. Running the scheduler
 * again for the same period does nothing.
 */
final class RecurringJobService
{
    /** @var list<string> */
    public const FREQUENCIES = ['WEEKLY', 'MONTHLY', 'QUARTERLY', 'ANNUALLY', 'CUSTOM_INTERVAL'];

    public function __construct(private readonly PlanningRepository $planning = new PlanningRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(array $input, int $userId): array
    {
        if (!can('recurring_jobs.manage')) {
            return ['errors' => ['_form' => 'You cannot manage recurring work.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $frequency = strtoupper(trim((string) ($input['frequency_type'] ?? '')));
        $next = strtotime((string) ($input['next_run_date'] ?? ''));
        if ($customerId < 1 || $name === '' || !in_array($frequency, self::FREQUENCIES, true) || $next === false) {
            return ['errors' => ['_form' => 'Customer, name, frequency, and next date are required.'], 'id' => null];
        }
        $id = $this->planning->insertRecurring([
            'customer_id' => $customerId,
            'name' => mb_substr($name, 0, 180),
            'description' => $this->blank($input['description'] ?? null),
            'frequency_type' => $frequency,
            'interval_value' => max(1, (int) ($input['interval_value'] ?? 1)),
            'next_run_date' => date('Y-m-d', $next),
            'generation_mode' => 'FOLLOW_UP',
            'default_recipe_id' => ((int) ($input['default_recipe_id'] ?? 0)) > 0 ? (int) $input['default_recipe_id'] : null,
            'anchor_job_id' => ((int) ($input['anchor_job_id'] ?? 0)) > 0 ? (int) $input['anchor_job_id'] : null,
            'assigned_to' => ((int) ($input['assigned_to'] ?? 0)) > 0 ? (int) $input['assigned_to'] : null,
            'active' => 1,
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    public function run(?string $today = null): int
    {
        $today = $today ?? date('Y-m-d');
        $created = 0;
        foreach ($this->planning->recurringDue($today) as $template) {
            $made = Database::transaction(function () use ($template): bool {
                $period = (string) $template['next_run_date'];
                if ($this->planning->recurringRunExists((int) $template['id'], $period)) {
                    return false;
                }
                $followupId = $this->planning->insertFollowup(
                    (int) $template['id'],
                    (int) $template['customer_id'],
                    (string) $template['name'],
                    $period
                );
                $this->planning->insertRecurringRun((int) $template['id'], $period, $followupId);
                $this->planning->advanceRecurring((int) $template['id'], $this->nextDate($template));

                return true;
            });
            if ($made) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $template
     */
    public function nextDate(array $template): string
    {
        $interval = max(1, (int) ($template['interval_value'] ?? 1));
        $from = (string) $template['next_run_date'];

        return match ((string) $template['frequency_type']) {
            'WEEKLY' => date('Y-m-d', strtotime($from . ' +' . (7 * $interval) . ' days')),
            'QUARTERLY' => date('Y-m-d', strtotime($from . ' +' . (3 * $interval) . ' months')),
            'ANNUALLY' => date('Y-m-d', strtotime($from . ' +' . $interval . ' years')),
            'CUSTOM_INTERVAL' => date('Y-m-d', strtotime($from . ' +' . $interval . ' days')),
            default => date('Y-m-d', strtotime($from . ' +' . $interval . ' months')),
        };
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
