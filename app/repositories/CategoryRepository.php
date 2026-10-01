<?php

declare(strict_types=1);

namespace App\Repositories;

final class CategoryRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function allWithParent(): array
    {
        return $this->rows(
            'SELECT c.id, c.parent_id, c.name, c.description, c.active, c.sort_order,
                    p.name AS parent_name
             FROM product_categories c
             LEFT JOIN product_categories p ON p.id = c.parent_id
             ORDER BY COALESCE(p.sort_order, c.sort_order), (c.parent_id IS NOT NULL), c.sort_order, c.name'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM product_categories WHERE id = ? LIMIT 1', [$id]);
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM product_categories WHERE name = ?';
        $params = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return $this->one($sql . ' LIMIT 1', $params) !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO product_categories (parent_id, name, description, active, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$data['parent_id'], $data['name'], $data['description'], $data['active'], $data['sort_order']]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->run(
            'UPDATE product_categories
             SET parent_id = ?, name = ?, description = ?, active = ?, sort_order = ?
             WHERE id = ?',
            [$data['parent_id'], $data['name'], $data['description'], $data['active'], $data['sort_order'], $id]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE product_categories SET active = ? WHERE id = ?', [$active, $id]);
    }

    /**
     * @return list<int>
     */
    public function descendantIds(int $id): array
    {
        $children = $this->rows('SELECT id FROM product_categories WHERE parent_id = ?', [$id]);
        $ids = [];
        foreach ($children as $child) {
            $childId = (int) $child['id'];
            $ids[] = $childId;
            $ids = array_merge($ids, $this->descendantIds($childId));
        }

        return $ids;
    }
}
