<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutomationRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\QuoteRepository;

/**
 * A small set of triggers and actions. Rules store JSON config only.
 * They never run PHP supplied by a user.
 */
final class AutomationService
{
    /** @var list<string> */
    public const TRIGGERS = ['QUOTE_SENT'];

    /** @var list<string> */
    public const ACTIONS = ['CREATE_REMINDER'];

    public function __construct(
        private readonly AutomationRepository $rules = new AutomationRepository(),
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly QuoteRepository $quotes = new QuoteRepository()
    ) {
    }

    public function fire(string $trigger, string $entityType, int $entityId, int $actorId): void
    {
        foreach ($this->rules->active($trigger) as $rule) {
            try {
                $this->act($rule, $entityType, $entityId, $actorId);
            } catch (\Throwable $e) {
                $this->rules->log((int) $rule['id'], $entityType, $entityId, 'FAILED', mb_substr($e->getMessage(), 0, 240));
                error_log('Automation failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function act(array $rule, string $entityType, int $entityId, int $actorId): void
    {
        $action = (string) $rule['action_type'];
        if ($action !== 'CREATE_REMINDER' || $entityType !== 'quote') {
            $this->rules->log((int) $rule['id'], $entityType, $entityId, 'SKIPPED', 'Unsupported action for this record.');

            return;
        }
        $quote = $this->quotes->find($entityId);
        if ($quote === null) {
            $this->rules->log((int) $rule['id'], $entityType, $entityId, 'SKIPPED', 'Quotation was not found.');

            return;
        }
        $config = json_decode((string) ($rule['action_config_json'] ?? '{}'), true);
        $days = is_array($config) ? (int) ($config['days'] ?? 3) : 3;
        $days = max(1, min(60, $days));
        $when = (new \DateTimeImmutable('today'))->modify('+' . $days . ' days');
        $userId = (int) ($quote['assigned_to'] ?? 0);
        if ($userId < 1) {
            $userId = $actorId;
        }
        $dedupe = 'rule:' . $rule['id'] . ':quote:' . $entityId;
        $created = $this->notifications->insertReminder([
            'user_id' => $userId,
            'entity_type' => 'quote',
            'entity_id' => $entityId,
            'title' => 'Follow up ' . $quote['quote_number'],
            'description' => 'Created when the quotation was marked sent.',
            'remind_at' => $when->format('Y-m-d 08:00:00'),
            'dedupe_key' => $dedupe,
        ]);
        $this->quotes->setFollowUp($entityId, $when->format('Y-m-d'));
        $this->rules->log(
            (int) $rule['id'],
            'quote',
            $entityId,
            $created ? 'DONE' : 'SKIPPED',
            $created ? 'Reminder set for ' . $when->format('Y-m-d') . '.' : 'Reminder already existed.'
        );
    }

    public function deliverScheduledReports(): int
    {
        $now = date('Y-m-d H:i:s');
        $sent = 0;
        $notifier = new NotificationService();
        foreach ($this->rules->dueReports($now) as $report) {
            $type = (string) $report['report_type'];
            $notifier->send(
                (int) $report['recipient_user_id'],
                null,
                'SCHEDULED_REPORT',
                'Scheduled ' . str_replace('_', ' ', strtolower($type)) . ' report',
                'Open Reports to read the current figures. This notice is not an email.',
                'report',
                null,
                'NORMAL',
                'SCHEDULED_REPORT:' . $report['id'] . ':' . date('Y-m-d')
            );
            $next = $this->nextRun((string) $report['frequency']);
            $this->rules->markReportRun((int) $report['id'], $now, $next);
            $sent++;
        }

        return $sent;
    }

    public function nextRun(string $frequency): string
    {
        $today = new \DateTimeImmutable('today');
        $next = match (strtoupper($frequency)) {
            'DAILY' => $today->modify('+1 day'),
            'MONTHLY' => $today->modify('+1 month'),
            default => $today->modify('+7 days'),
        };

        return $next->format('Y-m-d 06:00:00');
    }
}
