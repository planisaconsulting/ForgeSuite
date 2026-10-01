<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CommunicationRepository;
use App\Repositories\NotificationRepository;

/**
 * Internal alerts for uncontacted leads, stale quotes, and completed jobs.
 * Nothing here sends a customer message.
 */
final class LeadAlertService
{
    public function evaluate(): int
    {
        $created = 0;
        $created += $this->uncontacted();
        $created += $this->staleQuotes();
        $created += $this->postJob();
        (new CommunicationRepository())->purgeRates();

        return $created;
    }

    private function uncontacted(): int
    {
        $hours = max(1, (int) SettingsService::get('stale_lead_business_hours', '4'));
        $rows = \App\Helpers\Database::connection()->query(
            "SELECT id, lead_number, created_at, assigned_to, source
             FROM leads
             WHERE first_contact_at IS NULL AND status IN ('NEW', 'UNASSIGNED', 'ASSIGNED')"
        )->fetchAll();
        $time = new BusinessTimeService();
        $now = new \DateTimeImmutable('now');
        $notifications = new NotificationRepository();
        $count = 0;
        foreach ($rows as $row) {
            $elapsed = $time->businessMinutesBetween(new \DateTimeImmutable((string) $row['created_at']), $now);
            if ($elapsed < $hours * 60) {
                continue;
            }
            $userId = (int) ($row['assigned_to'] ?? 0);
            if ($notifications->insert([
                'user_id' => $userId > 0 ? $userId : null,
                'role_id' => $userId > 0 ? null : (new \App\Repositories\SystemRepository())->roleId('SALES'),
                'type' => 'LEAD_NOT_CONTACTED',
                'title' => 'Uncontacted lead ' . $row['lead_number'],
                'message' => 'No genuine sales contact within ' . $hours . ' business hours.',
                'entity_type' => 'lead',
                'entity_id' => (int) $row['id'],
                'priority' => 'HIGH',
                'dedupe_key' => 'stale-lead:' . $row['id'],
                'expires_at' => null,
            ])) {
                $count++;
            }
        }

        return $count;
    }

    private function staleQuotes(): int
    {
        $days = max(1, (int) SettingsService::get('stale_quote_business_days', '5'));
        $rows = \App\Helpers\Database::connection()->query(
            "SELECT q.id, q.quote_number, q.assigned_to, q.status_changed_at
             FROM quotes q
             WHERE q.status = 'SENT' AND q.archived = 0"
        )->fetchAll();
        $time = new BusinessTimeService();
        $today = new \DateTimeImmutable('today');
        $notifications = new NotificationRepository();
        $count = 0;
        foreach ($rows as $row) {
            $sent = new \DateTimeImmutable(substr((string) ($row['status_changed_at'] ?? 'now'), 0, 10));
            $due = $time->addBusinessDays($sent, $days);
            if ($today < $due) {
                continue;
            }
            $userId = (int) ($row['assigned_to'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            if ($notifications->insert([
                'user_id' => $userId,
                'role_id' => null,
                'type' => 'QUOTE_FOLLOWUP_DUE',
                'title' => 'Follow-up due ' . $row['quote_number'],
                'message' => 'No follow-up threshold of ' . $days . ' business days has passed since the quote was sent.',
                'entity_type' => 'quote',
                'entity_id' => (int) $row['id'],
                'priority' => 'NORMAL',
                'dedupe_key' => 'stale-quote:' . $row['id'],
                'expires_at' => null,
            ])) {
                $count++;
            }
        }

        return $count;
    }

    private function postJob(): int
    {
        $days = max(0, (int) SettingsService::get('post_job_followup_days', '2'));
        $rows = \App\Helpers\Database::connection()->query(
            "SELECT id, job_number, assigned_to FROM jobs
             WHERE status = 'COMPLETED' AND completed_at IS NOT NULL
               AND completed_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
               AND archived = 0
             ORDER BY id DESC LIMIT 50"
        )->fetchAll();
        $notifications = new NotificationRepository();
        $count = 0;
        foreach ($rows as $row) {
            $userId = (int) ($row['assigned_to'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            if ($notifications->insert([
                'user_id' => $userId,
                'role_id' => null,
                'type' => 'JOB_COMPLETED',
                'title' => 'Post-job follow-up ' . $row['job_number'],
                'message' => 'Internal reminder to thank the customer or request feedback. No message is sent automatically.',
                'entity_type' => 'job',
                'entity_id' => (int) $row['id'],
                'priority' => 'NORMAL',
                'dedupe_key' => 'post-job:' . $row['id'],
                'expires_at' => null,
            ])) {
                $count++;
            }
        }

        return $count;
    }
}
