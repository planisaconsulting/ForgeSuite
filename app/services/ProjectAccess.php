<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectRepository;

/**
 * Server-side project visibility. The URL is not proof of access.
 */
final class ProjectAccess
{
    public function __construct(private readonly ProjectRepository $projects = new ProjectRepository())
    {
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function canOpen(?array $user, int $projectId): bool
    {
        if ($user === null || $projectId < 1) {
            return false;
        }
        if (AuthorizationService::allows($user, 'projects.view')) {
            return true;
        }
        if (!AuthorizationService::allows($user, 'projects.view_assigned')) {
            return false;
        }

        return $this->projects->userAssigned($projectId, (int) $user['id']);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function canViewFinancials(?array $user): bool
    {
        return AuthorizationService::allows($user, 'projects.view_financials');
    }

    /**
     * Null means the user may see every project. An id limits the list to assignments.
     *
     * @param array<string, mixed>|null $user
     */
    public function listUserId(?array $user): ?int
    {
        if ($user === null) {
            return null;
        }
        if (AuthorizationService::allows($user, 'projects.view')) {
            return null;
        }

        return (int) $user['id'];
    }
}
