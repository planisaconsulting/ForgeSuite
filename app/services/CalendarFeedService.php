<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExpenseRepository;

/**
 * A calendar feed is a view of ERP dates.
 * Changing an installation date changes the next feed. The UID stays the same.
 */
final class CalendarFeedService
{
    public function __construct(
        private readonly ExpenseRepository $repo = new ExpenseRepository(),
        private readonly CalendarProviderInterface $provider = new IcalCalendarProvider(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{errors: array<string, string>, token: string|null, id: int|null}
     */
    public function createFeed(int $userId, string $scope, string $detail = 'BASIC', ?int $projectId = null): array
    {
        if (!can('calendar.manage_feed') && !can('calendar.view_company') && !can('expenses.view')) {
            return ['errors' => ['_form' => 'You cannot create a calendar feed.'], 'token' => null, 'id' => null];
        }
        $scope = strtoupper($scope);
        if (!in_array($scope, ['MY', 'TEAM', 'INSTALLATIONS', 'DELIVERIES', 'PROJECT', 'COMPANY'], true)) {
            return ['errors' => ['scope' => 'Choose a feed.'], 'token' => null, 'id' => null];
        }
        if (in_array($scope, ['TEAM', 'COMPANY'], true) && !can('calendar.view_company')) {
            return ['errors' => ['scope' => 'You cannot subscribe to the company calendar.'], 'token' => null, 'id' => null];
        }
        $detail = strtoupper($detail) === 'STANDARD' ? 'STANDARD' : 'BASIC';
        $token = bin2hex(random_bytes(32));
        $id = $this->repo->insertFeed($userId, $scope, $detail, hash('sha256', $token), $projectId);
        $this->audit->record('calendar_feed', $id, 'CALENDAR_FEED_CREATED', null, ['scope' => $scope], $userId);
        BusinessEventDispatcher::emit('CALENDAR_FEED_CREATED', 'CALENDAR_FEED', $id, $userId, ['scope' => strtolower($scope)]);

        return ['errors' => [], 'token' => $token, 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, body: string|null, status: int}
     */
    public function ics(string $token): array
    {
        $hash = hash('sha256', $token);
        $feed = $this->repo->feedByHash($hash);
        if ($feed === null || $feed['revoked_at'] !== null) {
            return ['errors' => ['token' => 'This calendar link is not active.'], 'body' => null, 'status' => 403];
        }
        $limit = (int) SettingsService::get('api_rate_per_minute', '60');
        if (!(new RateLimiter())->allow('ical:' . $feed['id'], max(1, $limit), 60)) {
            return ['errors' => ['rate' => 'Too many requests.'], 'body' => null, 'status' => 429];
        }
        $userId = in_array((string) $feed['scope'], ['TEAM', 'COMPANY', 'INSTALLATIONS'], true) ? null : (int) $feed['user_id'];
        $events = $this->events(date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+120 days')), $userId, (string) $feed['detail_level']);

        return ['errors' => [], 'body' => $this->provider->render($events, $this->timezone()), 'status' => 200];
    }

    public function revoke(int $id, string $token, int $userId): array
    {
        $feed = $this->repo->feedByHash(hash('sha256', $token));
        if ($feed === null || (int) $feed['id'] !== $id) {
            return ['errors' => ['token' => 'That feed was not found.']];
        }
        if ((int) $feed['user_id'] !== $userId && !can('calendar.manage_feed')) {
            return ['errors' => ['_form' => 'You cannot revoke this feed.']];
        }
        $this->repo->revokeFeed($id);
        $this->audit->record('calendar_feed', $id, 'CALENDAR_FEED_REVOKED', null, [], $userId);
        BusinessEventDispatcher::emit('CALENDAR_FEED_REVOKED', 'CALENDAR_FEED', $id, $userId, []);

        return ['errors' => []];
    }

    /**
     * @return array{errors: array<string, string>, body: string|null}
     */
    public function customerEvent(int $installationId, int $customerId): array
    {
        $row = $this->repo->installation($installationId);
        if ($row === null || (int) $row['customer_id'] !== $customerId) {
            return ['errors' => ['_form' => 'That installation was not found.'], 'body' => null];
        }
        $event = $this->installationEvent($row, 'BASIC');
        $event['description'] = (string) $row['job_number'];
        $event['location'] = '';

        return ['errors' => [], 'body' => $this->provider->render([$event], $this->timezone())];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(string $from, string $to, ?int $userId, string $detail = 'BASIC'): array
    {
        $events = [];
        foreach ($this->repo->installations($from, $to, $userId) as $row) {
            $events[] = $this->installationEvent($row, $detail);
        }
        foreach ($this->repo->tasksBetween($from, $to, $userId) as $row) {
            $priority = strtoupper((string) ($row['priority'] ?? 'NORMAL'));
            if (!in_array($priority, ['URGENT', 'HIGH', 'NORMAL', 'LOW'], true)) {
                $priority = 'NORMAL';
            }
            $events[] = [
                'uid' => 'sf-task-' . $row['id'] . '@signforge.local',
                'source' => 'TASK',
                'date' => (string) $row['due_date'],
                'time' => '',
                'summary' => 'Task — ' . $row['job_number'],
                'description' => $detail === 'STANDARD' ? (string) $row['title'] : (string) $row['job_number'],
                'location' => '',
                'status' => (string) $row['status'],
                'priority' => ucfirst(strtolower($priority)),
                'href' => '/jobs',
            ];
        }

        return $events;
    }

    public function remind(int $installationId, int $userId, int $minutes): bool
    {
        $row = $this->repo->installation($installationId);
        if ($row === null) {
            return false;
        }
        $when = trim((string) ($row['scheduled_date'] ?? ''));
        if ($when === '') {
            return false;
        }

        return (new NotificationService())->send(
            $userId,
            null,
            'SYSTEM',
            'Installation ' . $row['job_number'],
            'Scheduled ' . $when . '. This is a reminder, not a promise.',
            'INSTALLATION',
            $installationId,
            'NORMAL',
            'cal-' . $installationId . '-' . $minutes . '-' . $when
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function installationEvent(array $row, string $detail): array
    {
        $summary = 'Installation — ' . $row['job_number'];
        $description = $detail === 'STANDARD' ? (string) ($row['title'] ?? $row['job_number']) : (string) $row['job_number'];

        return [
            'uid' => 'sf-installation-' . $row['id'] . '@signforge.local',
            'source' => 'INSTALLATION',
            'date' => (string) $row['scheduled_date'],
            'time' => (string) ($row['scheduled_start_time'] ?? ''),
            'summary' => $summary,
            'description' => $description,
            'location' => $detail === 'STANDARD' ? (string) ($row['site_address'] ?? '') : '',
            'status' => (string) $row['status'],
            'priority' => 'Normal',
            'href' => '/field/' . $row['id'],
        ];
    }

    private function timezone(): string
    {
        $zone = trim((string) SettingsService::get('company_timezone', 'Africa/Johannesburg'));

        return $zone !== '' ? $zone : 'Africa/Johannesburg';
    }
}
