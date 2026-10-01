<?php

declare(strict_types=1);

namespace App\Repositories;

final class PricingLevelRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->rows(
            'SELECT * FROM pricing_levels ORDER BY sort_order, code'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        return $this->rows(
            'SELECT id, code, name, markup_percent, active, sort_order
             FROM pricing_levels
             WHERE active = 1
             ORDER BY sort_order, code'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM pricing_levels WHERE id = ? LIMIT 1', [$id]);
    }

    public function countActive(): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM pricing_levels WHERE active = 1');

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->run(
            'UPDATE pricing_levels
             SET name = ?, markup_percent = ?, active = ?, sort_order = ?
             WHERE id = ?',
            [$data['name'], $data['markup_percent'], $data['active'], $data['sort_order'], $id]
        );
    }
}
