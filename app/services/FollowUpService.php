<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\QuoteRepository;

/**
 * Quote follow-up reminders. The same dedupe key means a repeated cron run adds nothing.
 * This creates an internal reminder. It does not email the customer.
 */
final class FollowUpService
{
    public function __construct(
        private readonly NotificationRepository $notifications = new NotificationRepository(),
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly BusinessTimeService $time = new BusinessTimeService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function scheduleQuote(int $quoteId, int $actorId): bool
    {
        $quote = $this->quotes->find($quoteId);
        if ($quote === null || (string) $quote['status'] !== 'SENT') {
            return false;
        }
        $days = max(1, (int) SettingsService::get('quote_followup_business_days', '3'));
        $when = $this->time->addBusinessDays(new \DateTimeImmutable('today'), $days);
        $userId = (int) ($quote['assigned_to'] ?? 0);
        if ($userId < 1) {
            $userId = $actorId;
        }
        $created = $this->notifications->insertReminder([
            'user_id' => $userId,
            'entity_type' => 'quote',
            'entity_id' => $quoteId,
            'title' => 'Follow up ' . $quote['quote_number'],
            'description' => 'Due ' . $days . ' business days after the quotation was sent. This is an internal reminder.',
            'remind_at' => $when->format('Y-m-d 08:00:00'),
            'dedupe_key' => 'quote-followup:' . $quoteId,
        ]);
        $this->quotes->setFollowUp($quoteId, $when->format('Y-m-d'));
        if ($created) {
            $this->audit->record('quote', $quoteId, 'FOLLOWUP_CREATED', null, [
                'remind_at' => $when->format('Y-m-d'),
                'business_days' => $days,
            ], $actorId);
        }

        return $created;
    }

    /**
     * @return array{overdue: list<array<string, mixed>>, today: list<array<string, mixed>>, upcoming: list<array<string, mixed>>}
     */
    public function queue(int $userId, bool $mineOnly): array
    {
        $rows = (new \App\Repositories\LeadRepository())->inbox([
            'status' => 'FOLLOWUP',
            'assigned_only' => $mineOnly ? $userId : 0,
        ], 80, 0);
        $quoteSql = "SELECT q.id, q.quote_number AS lead_number, c.company_name AS name, q.next_follow_up_date AS next_followup_at
             FROM quotes q
             INNER JOIN customers c ON c.id = q.customer_id
             WHERE q.status = 'SENT' AND q.archived = 0 AND q.next_follow_up_date IS NOT NULL";
        $params = [];
        if ($mineOnly) {
            $quoteSql .= ' AND q.assigned_to = ?';
            $params[] = $userId;
        }
        $quoteSql .= ' ORDER BY q.next_follow_up_date LIMIT 80';
        $statement = \App\Helpers\Database::connection()->prepare($quoteSql);
        $statement->execute($params);
        foreach ($statement->fetchAll() as $quote) {
            $quote['href'] = '/quotes/' . $quote['id'];
            $quote['next_followup_at'] = $quote['next_followup_at'] . ' 08:00:00';
            $rows[] = $quote;
        }
        $groups = ['overdue' => [], 'today' => [], 'upcoming' => []];
        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $when = substr((string) $row['next_followup_at'], 0, 10);
            if ($when < $today) {
                $groups['overdue'][] = $row;
            } elseif ($when === $today) {
                $groups['today'][] = $row;
            } else {
                $groups['upcoming'][] = $row;
            }
        }

        return $groups;
    }
}
