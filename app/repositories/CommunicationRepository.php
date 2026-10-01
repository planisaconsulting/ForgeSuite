<?php

declare(strict_types=1);

namespace App\Repositories;

final class CommunicationRepository extends Repository
{
    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset): array
    {
        $sql = ['1 = 1'];
        $params = [];
        foreach (['customer_id' => 'c.customer_id', 'lead_id' => 'c.lead_id', 'quote_id' => 'c.quote_id', 'job_id' => 'c.job_id', 'contact_id' => 'c.contact_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $sql[] = $column . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }
        if (!empty($filters['channel'])) {
            $sql[] = 'c.channel = ?';
            $params[] = $filters['channel'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql[] = '(c.subject LIKE ? OR c.message_summary LIKE ? OR c.message_body LIKE ? OR cu.email LIKE ? OR l.lead_number LIKE ?)';
            $like = like_term($q);
            array_push($params, $like, $like, $like, $like, $like);
        }

        return $this->rows(
            'SELECT c.*, u.name AS user_name, cu.company_name, l.lead_number
             FROM communications c
             LEFT JOIN users u ON u.id = c.sent_by
             LEFT JOIN customers cu ON cu.id = c.customer_id
             LEFT JOIN leads l ON l.id = c.lead_id
             WHERE ' . implode(' AND ', $sql) . '
             ORDER BY c.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(?string $channel = null): array
    {
        if ($channel === null || $channel === '') {
            return $this->rows('SELECT * FROM communication_templates ORDER BY channel, name');
        }

        return $this->rows(
            'SELECT * FROM communication_templates WHERE channel = ? AND active = 1 ORDER BY name',
            [$channel]
        );
    }

    public function template(int $id): ?array
    {
        return $this->one('SELECT * FROM communication_templates WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveTemplate(array $data, ?int $id = null): int
    {
        if ($id === null) {
            $this->run(
                'INSERT INTO communication_templates (name, channel, category, subject_template, body_template, active, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$data['name'], $data['channel'], $data['category'], $data['subject_template'], $data['body_template'], $data['active'], $data['created_by']]
            );

            return $this->insertId();
        }
        $this->run(
            'UPDATE communication_templates
             SET name = ?, channel = ?, category = ?, subject_template = ?, body_template = ?, active = ?
             WHERE id = ?',
            [$data['name'], $data['channel'], $data['category'], $data['subject_template'], $data['body_template'], $data['active'], $id]
        );

        return $id;
    }

    public function secret(string $key): ?string
    {
        $row = $this->one('SELECT secret_value FROM integration_secrets WHERE secret_key = ?', [$key]);
        if ($row === null || (string) $row['secret_value'] === '') {
            return null;
        }

        return (string) $row['secret_value'];
    }

    public function hasSecret(string $key): bool
    {
        return $this->secret($key) !== null;
    }

    public function putSecret(string $key, string $value): void
    {
        $this->run(
            'INSERT INTO integration_secrets (secret_key, secret_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE secret_value = VALUES(secret_value)',
            [$key, $value]
        );
    }

    public function rateCount(string $bucket, string $ip): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM public_rate_limits
             WHERE bucket = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$bucket, $ip]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function rateHit(string $bucket, string $ip): void
    {
        $this->run(
            'INSERT INTO public_rate_limits (bucket, ip_address) VALUES (?, ?)',
            [$bucket, $ip]
        );
    }

    public function purgeRates(): void
    {
        $this->run('DELETE FROM public_rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function insertEvent(string $provider, string $externalId, string $type, string $hash, string $when): void
    {
        $this->run(
            'INSERT INTO integration_events (provider, external_event_id, event_type, payload_hash, status, received_at, processed_at)
             VALUES (?, ?, ?, ?, \'PROCESSED\', ?, ?)',
            [$provider, $externalId, $type, $hash, $when, $when]
        );
    }

    public function event(string $provider, string $externalId): ?array
    {
        return $this->one(
            'SELECT * FROM integration_events WHERE provider = ? AND external_event_id = ?',
            [$provider, $externalId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function preferences(int $contactId): array
    {
        return $this->one(
            'SELECT * FROM contact_communication_preferences WHERE contact_id = ?',
            [$contactId]
        ) ?? [
            'contact_id' => $contactId,
            'email_allowed' => 1,
            'whatsapp_allowed' => 1,
            'sms_allowed' => 0,
            'marketing_allowed' => 0,
            'transactional_allowed' => 1,
            'preferred_channel' => null,
            'source' => 'DEFAULT',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function savePreferences(int $contactId, array $data): void
    {
        $this->run(
            'INSERT INTO contact_communication_preferences
                (contact_id, email_allowed, whatsapp_allowed, sms_allowed, marketing_allowed, transactional_allowed, preferred_channel, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                email_allowed = VALUES(email_allowed),
                whatsapp_allowed = VALUES(whatsapp_allowed),
                sms_allowed = VALUES(sms_allowed),
                marketing_allowed = VALUES(marketing_allowed),
                transactional_allowed = VALUES(transactional_allowed),
                preferred_channel = VALUES(preferred_channel),
                source = VALUES(source)',
            [
                $contactId, $data['email_allowed'], $data['whatsapp_allowed'], $data['sms_allowed'],
                $data['marketing_allowed'], $data['transactional_allowed'], $data['preferred_channel'], $data['source'],
            ]
        );
    }

    public function suppress(array $data): void
    {
        $this->run(
            'INSERT INTO communication_suppressions (contact_id, email, phone, channel, reason) VALUES (?, ?, ?, ?, ?)',
            [$data['contact_id'], $data['email'], $data['phone'], $data['channel'], $data['reason']]
        );
    }

    public function suppressed(string $channel, ?string $email, ?string $phone, ?int $contactId): bool
    {
        $row = $this->one(
            'SELECT id FROM communication_suppressions
             WHERE channel = ?
               AND (
                    (? <> \'\' AND email = ?)
                    OR (? <> \'\' AND phone = ?)
                    OR (? > 0 AND contact_id = ?)
               )
             LIMIT 1',
            [$channel, (string) $email, (string) $email, (string) $phone, (string) $phone, (int) $contactId, (int) $contactId]
        );

        return $row !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestFailure(): ?array
    {
        return $this->one(
            'SELECT created_at, channel, failure_reason FROM communications WHERE status = \'FAILED\' ORDER BY id DESC LIMIT 1'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestEvent(): ?array
    {
        return $this->one('SELECT provider, status, received_at, error_message FROM integration_events ORDER BY id DESC LIMIT 1');
    }
}
