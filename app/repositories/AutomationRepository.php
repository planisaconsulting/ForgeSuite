<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Controlled automation rules and the log of what they did.
 */
final class AutomationRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function active(string $trigger): array
    {
        return $this->rows(
            'SELECT * FROM automation_rules WHERE active = 1 AND trigger_type = ? ORDER BY id',
            [$trigger]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->rows('SELECT * FROM automation_rules ORDER BY active DESC, name');
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM automation_rules WHERE id = ?', [$id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->run('UPDATE automation_rules SET active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO automation_rules (name, trigger_type, conditions_json, action_type, action_config_json, active, created_by)
             VALUES (?, ?, ?, ?, ?, 1, ?)',
            [
                $data['name'], $data['trigger_type'], $data['conditions_json'],
                $data['action_type'], $data['action_config_json'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function log(?int $ruleId, ?string $entityType, ?int $entityId, string $status, string $message): void
    {
        $this->run(
            'INSERT INTO automation_log (automation_rule_id, entity_type, entity_id, status, message)
             VALUES (?, ?, ?, ?, ?)',
            [$ruleId, $entityType, $entityId, $status, $message]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentLog(int $limit = 40): array
    {
        return $this->rows(
            'SELECT l.*, r.name AS rule_name
             FROM automation_log l
             LEFT JOIN automation_rules r ON r.id = l.automation_rule_id
             ORDER BY l.executed_at DESC
             LIMIT ' . (int) $limit
        );
    }

    public function failedSince(string $since): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM automation_log WHERE status = 'FAILED' AND executed_at >= ?",
            [$since]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueReports(string $now): array
    {
        return $this->rows(
            'SELECT * FROM scheduled_reports WHERE active = 1 AND (next_run_at IS NULL OR next_run_at <= ?)',
            [$now]
        );
    }

    public function markReportRun(int $id, string $last, string $next): void
    {
        $this->run(
            'UPDATE scheduled_reports SET last_run_at = ?, next_run_at = ? WHERE id = ?',
            [$last, $next, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReport(array $data): int
    {
        $this->run(
            'INSERT INTO scheduled_reports (report_type, recipient_user_id, frequency, filters_json, next_run_at, active)
             VALUES (?, ?, ?, ?, ?, 1)',
            [$data['report_type'], $data['recipient_user_id'], $data['frequency'], $data['filters_json'], $data['next_run_at']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reports(): array
    {
        return $this->rows(
            'SELECT s.*, u.name AS recipient_name
             FROM scheduled_reports s
             INNER JOIN users u ON u.id = s.recipient_user_id
             ORDER BY s.active DESC, s.id DESC'
        );
    }
}
