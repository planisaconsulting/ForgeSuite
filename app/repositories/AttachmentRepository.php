<?php

declare(strict_types=1);

namespace App\Repositories;

final class AttachmentRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forEntity(string $type, int $id): array
    {
        return $this->rows(
            'SELECT a.*, u.name AS user_name
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.id DESC',
            [$type, $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM attachments WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO attachments (
                entity_type, entity_id, original_filename, stored_filename, mime_type, file_size, uploaded_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['entity_type'], $data['entity_id'], $data['original_filename'],
                $data['stored_filename'], $data['mime_type'], $data['file_size'], $data['uploaded_by'],
            ]
        );

        return $this->insertId();
    }
}
