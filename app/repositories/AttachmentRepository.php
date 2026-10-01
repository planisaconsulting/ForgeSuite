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
                entity_type, entity_id, original_filename, stored_filename, mime_type, purpose, notes, file_size, uploaded_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['entity_type'], $data['entity_id'], $data['original_filename'],
                $data['stored_filename'], $data['mime_type'], $data['purpose'] ?? 'GENERAL',
                $data['notes'] ?? null, $data['file_size'], $data['uploaded_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function applyMeta(int $id, array $meta): void
    {
        $visibility = strtoupper((string) ($meta['visibility'] ?? 'INTERNAL'));
        if (!in_array($visibility, ['INTERNAL', 'CUSTOMER_VISIBLE', 'CUSTOMER_UPLOADED'], true)) {
            $visibility = 'INTERNAL';
        }
        $tag = strtoupper(preg_replace('/[^A-Z_]/', '', (string) ($meta['photo_tag'] ?? '')) ?? '');
        $this->run(
            'UPDATE attachments SET visibility = ?, photo_tag = ?, measurement_id = ?, portal_user_id = ?, annotation_json = ?, notes = COALESCE(?, notes)
             WHERE id = ?',
            [
                $visibility,
                $tag !== '' ? $tag : null,
                ((int) ($meta['measurement_id'] ?? 0)) > 0 ? (int) $meta['measurement_id'] : null,
                ((int) ($meta['portal_user_id'] ?? 0)) > 0 ? (int) $meta['portal_user_id'] : null,
                isset($meta['annotation_json']) ? json_encode($meta['annotation_json']) : null,
                $meta['caption'] ?? null,
                $id,
            ]
        );
    }
}
