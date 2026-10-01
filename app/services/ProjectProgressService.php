<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ProjectRepository;

/**
 * Weighted milestone progress. Completing a light task does not move the
 * project as far as completing a heavy one. A project with no weights has
 * no progress figure.
 */
final class ProjectProgressService
{
    public function __construct(private readonly ProjectRepository $projects = new ProjectRepository())
    {
    }

    /**
     * @return array{percent: string|null, explanation: string}
     */
    public function calculate(int $projectId): array
    {
        $milestones = $this->projects->milestones($projectId);
        $weight = '0';
        $done = '0';
        foreach ($milestones as $milestone) {
            $rowWeight = $milestone['weight'];
            if ($rowWeight === null || !Decimal::isNumeric((string) $rowWeight) || Decimal::cmp((string) $rowWeight, '0') <= 0) {
                continue;
            }
            $weight = Decimal::add($weight, (string) $rowWeight, 2);
            if ((string) $milestone['status'] === 'COMPLETE') {
                $done = Decimal::add($done, (string) $rowWeight, 2);
            }
        }
        if (Decimal::cmp($weight, '0') === 0) {
            return ['percent' => null, 'explanation' => 'No weighted milestones are set, so progress is not shown as a percentage of jobs.'];
        }
        $percent = Decimal::round(Decimal::mul(Decimal::div($done, $weight, 8), '100', 8), 2);

        return [
            'percent' => $percent,
            'explanation' => 'Weighted from milestones. Completed weight ' . Decimal::round($done, 2) . ' of ' . Decimal::round($weight, 2) . '.',
        ];
    }
}
