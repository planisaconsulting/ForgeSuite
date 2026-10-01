<?php

declare(strict_types=1);

namespace App\Repositories;

final class MarketingRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function campaigns(): array
    {
        return $this->rows('SELECT * FROM marketing_campaigns ORDER BY id DESC LIMIT 100');
    }

    public function campaign(int $id): ?array
    {
        return $this->one('SELECT * FROM marketing_campaigns WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCampaign(array $data): int
    {
        $this->run(
            'INSERT INTO marketing_campaigns (name, campaign_code, channel, start_date, end_date, budget, description, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['campaign_code'], $data['channel'], $data['start_date'], $data['end_date'],
                $data['budget'], $data['description'], $data['status'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * First-touch counts. Spam is excluded from valid leads. Unqualified leads stay in the valid count.
     *
     * @return list<array<string, mixed>>
     */
    public function sourceReport(): array
    {
        return $this->rows(
            "SELECT l.source,
                    COUNT(*) AS leads,
                    SUM(l.status = 'SPAM') AS spam,
                    SUM(l.status NOT IN ('SPAM')) AS valid_leads,
                    SUM(l.status IN ('QUALIFIED', 'CONVERTED')) AS qualified,
                    SUM(l.status = 'CONVERTED') AS converted,
                    SUM(l.status = 'LOST') AS lost
             FROM leads l
             GROUP BY l.source
             ORDER BY leads DESC"
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignFigures(int $campaignId): array
    {
        $leads = $this->one(
            "SELECT COUNT(*) AS leads,
                    SUM(status <> 'SPAM') AS valid_leads,
                    SUM(status IN ('QUALIFIED', 'CONVERTED')) AS qualified
             FROM leads WHERE campaign_id = ?",
            [$campaignId]
        ) ?? [];
        $quotes = $this->one(
            "SELECT COUNT(*) AS quotes,
                    COALESCE(SUM(total), 0) AS quote_value,
                    SUM(status = 'ACCEPTED') AS accepted,
                    COALESCE(SUM(CASE WHEN status = 'ACCEPTED' THEN total ELSE 0 END), 0) AS accepted_value
             FROM quotes WHERE campaign_id = ? AND archived = 0",
            [$campaignId]
        ) ?? [];
        $jobs = $this->one('SELECT COUNT(*) AS jobs FROM jobs WHERE campaign_id = ? AND archived = 0', [$campaignId]) ?? [];
        $invoices = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS invoiced, COALESCE(SUM(amount_paid), 0) AS paid
             FROM invoices WHERE campaign_id = ? AND status NOT IN ('DRAFT', 'CANCELLED')",
            [$campaignId]
        ) ?? [];
        $profit = $this->one(
            'SELECT COALESCE(SUM(quoted_revenue_snapshot - actual_total_cost), 0) AS gross_profit
             FROM jobs WHERE campaign_id = ? AND status = \'COMPLETED\' AND archived = 0',
            [$campaignId]
        ) ?? [];

        return array_merge($leads, $quotes, $jobs, $invoices, $profit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activity(string $from, string $to): array
    {
        return $this->rows(
            "SELECT u.name,
                    SUM(c.channel = 'PHONE') AS calls,
                    SUM(c.channel = 'EMAIL' AND c.status = 'SENT') AS emails,
                    SUM(c.channel = 'WHATSAPP') AS whatsapp,
                    SUM(c.lead_id IS NOT NULL) AS lead_touches
             FROM communications c
             INNER JOIN users u ON u.id = c.sent_by
             WHERE c.sent_at >= ? AND c.sent_at < ?
             GROUP BY u.id, u.name
             ORDER BY u.name",
            [$from, $to . ' 23:59:59']
        );
    }

    /**
     * @return array{samples: int, average: ?string, median: ?int, low: ?int, high: ?int}
     */
    public function responseMinutes(string $from, string $to): array
    {
        $rows = $this->rows(
            'SELECT TIMESTAMPDIFF(MINUTE, created_at, first_contact_at) AS minutes
             FROM leads
             WHERE first_contact_at IS NOT NULL AND created_at >= ? AND created_at < ?
             ORDER BY minutes
             LIMIT 500',
            [$from, $to . ' 23:59:59']
        );
        $values = array_map(static fn (array $row): int => (int) $row['minutes'], $rows);
        $count = count($values);
        if ($count === 0) {
            return ['samples' => 0, 'average' => null, 'median' => null, 'low' => null, 'high' => null];
        }
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1 ? $values[$middle] : intdiv($values[$middle - 1] + $values[$middle], 2);

        return [
            'samples' => $count,
            'average' => (string) intdiv(array_sum($values), $count),
            'median' => $median,
            'low' => $values[0],
            'high' => $values[$count - 1],
        ];
    }

    /**
     * Repeat customer rate = customers with more than one completed job
     * divided by customers with at least one completed job.
     *
     * @return array{with_job: int, repeaters: int}
     */
    public function repeatCustomers(): array
    {
        $row = $this->one(
            "SELECT SUM(n >= 1) AS with_job, SUM(n > 1) AS repeaters
             FROM (
                SELECT customer_id, COUNT(*) AS n
                FROM jobs
                WHERE status = 'COMPLETED' AND archived = 0
                GROUP BY customer_id
             ) counts"
        ) ?? [];

        return ['with_job' => (int) ($row['with_job'] ?? 0), 'repeaters' => (int) ($row['repeaters'] ?? 0)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dormant(int $months): array
    {
        return $this->rows(
            "SELECT cu.id, cu.company_name, cu.first_name, cu.last_name,
                    MAX(j.completed_at) AS last_job,
                    (SELECT MAX(q.created_at) FROM quotes q WHERE q.customer_id = cu.id) AS last_quote,
                    COALESCE(SUM(j.quoted_revenue_snapshot), 0) AS commercial_value
             FROM customers cu
             INNER JOIN jobs j ON j.customer_id = cu.id AND j.status = 'COMPLETED' AND j.archived = 0
             GROUP BY cu.id, cu.company_name, cu.first_name, cu.last_name
             HAVING last_job < DATE_SUB(NOW(), INTERVAL " . (int) $months . " MONTH)
             ORDER BY last_job
             LIMIT 100"
        );
    }

    /**
     * @return array<string, string>
     */
    public function historicalValue(int $customerId): array
    {
        $row = $this->one(
            "SELECT
                (SELECT COALESCE(SUM(total), 0) FROM quotes WHERE customer_id = ? AND status = 'ACCEPTED' AND archived = 0) AS accepted_commercial,
                (SELECT COALESCE(SUM(total), 0) FROM invoices WHERE customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED')) AS invoiced,
                (SELECT COALESCE(SUM(amount_paid), 0) FROM invoices WHERE customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED')) AS paid,
                (SELECT COALESCE(SUM(quoted_revenue_snapshot - actual_total_cost), 0) FROM jobs WHERE customer_id = ? AND status = 'COMPLETED' AND archived = 0) AS gross_profit",
            [$customerId, $customerId, $customerId, $customerId]
        ) ?? [];

        return [
            'accepted_commercial' => (string) ($row['accepted_commercial'] ?? '0.00'),
            'invoiced' => (string) ($row['invoiced'] ?? '0.00'),
            'paid' => (string) ($row['paid'] ?? '0.00'),
            'gross_profit' => (string) ($row['gross_profit'] ?? '0.00'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function feedback(int $limit = 40): array
    {
        return $this->rows(
            'SELECT f.*, cu.company_name, cu.first_name, cu.last_name
             FROM customer_feedback f
             INNER JOIN customers cu ON cu.id = f.customer_id
             ORDER BY f.submitted_at DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertFeedback(array $data): int
    {
        $this->run(
            'INSERT INTO customer_feedback (customer_id, contact_id, job_id, rating, feedback_text, source, submitted_at, followup_required)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['contact_id'], $data['job_id'], $data['rating'],
                $data['feedback_text'], $data['source'], $data['submitted_at'], $data['followup_required'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReview(array $data): int
    {
        $this->run(
            'INSERT INTO review_requests (customer_id, contact_id, job_id, channel, destination, requested_at, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['contact_id'], $data['job_id'], $data['channel'],
                $data['destination'], $data['requested_at'], $data['requested_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array{received: int, average: ?string, followup: int}
     */
    public function feedbackSummary(): array
    {
        $row = $this->one(
            'SELECT COUNT(*) AS received,
                    AVG(rating) AS average,
                    SUM(followup_required = 1) AS followup
             FROM customer_feedback'
        ) ?? [];

        return [
            'received' => (int) ($row['received'] ?? 0),
            'average' => $row['average'] !== null ? number_format((float) $row['average'], 2, '.', '') : null,
            'followup' => (int) ($row['followup'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function marketingRecipients(int $limit): array
    {
        return $this->rows(
            'SELECT c.id, c.name, c.email, c.customer_id
             FROM customer_contacts c
             INNER JOIN contact_communication_preferences p ON p.contact_id = c.id
             WHERE c.active = 1 AND c.email IS NOT NULL AND c.email <> \'\'
               AND p.marketing_allowed = 1 AND p.email_allowed = 1
               AND NOT EXISTS (
                    SELECT 1 FROM communication_suppressions s
                    WHERE s.channel = \'EMAIL\' AND (s.contact_id = c.id OR s.email = c.email)
               )
             ORDER BY c.id
             LIMIT ' . (int) $limit
        );
    }
}
