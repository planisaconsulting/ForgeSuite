<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Payment and accounting failures are not retried automatically.
 */
final class IntegrationIssueService
{
    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @return array<string, string>
     */
    public function act(int $id, string $action, int $userId): array
    {
        if (!can('integrations.retry')) {
            return ['_form' => 'You cannot update integration issues.'];
        }
        $issue = $this->platform->issue($id);
        if ($issue === null) {
            return ['_form' => 'That issue was not found.'];
        }
        $action = strtoupper($action);
        if ($action === 'IGNORE') {
            $this->platform->updateIssue($id, 'IGNORED', (int) $issue['attempts'], null);

            return [];
        }
        if ($action === 'RESOLVE') {
            $this->platform->updateIssue($id, 'RESOLVED', (int) $issue['attempts'], null);

            return [];
        }
        if ($action !== 'RETRY') {
            return ['action' => 'Choose retry, ignore, or resolve.'];
        }
        if ((int) $issue['safe_retry'] !== 1) {
            return ['_form' => 'This issue is not safe to retry automatically. Resolve it after checking the provider.'];
        }
        $attempts = (int) $issue['attempts'] + 1;
        $status = $attempts >= (int) $issue['max_attempts'] ? 'FAILED' : 'OPEN';
        $next = $status === 'OPEN' ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
        $this->platform->updateIssue($id, $status, $attempts, $next);

        return [];
    }

    public function retryDue(): int
    {
        $count = 0;
        foreach ($this->platform->dueIssues() as $issue) {
            $attempts = (int) $issue['attempts'] + 1;
            $status = $attempts >= (int) $issue['max_attempts'] ? 'FAILED' : 'OPEN';
            $this->platform->updateIssue((int) $issue['id'], $status, $attempts, $status === 'OPEN' ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null);
            $count++;
        }

        return $count;
    }
}
