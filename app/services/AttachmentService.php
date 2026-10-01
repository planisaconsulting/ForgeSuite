<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\AttachmentRepository;

/**
 * Files for a customer, opportunity, or quotation.
 * They are stored outside the public folder and downloaded through PHP.
 */
final class AttachmentService
{
    private const MAX_BYTES = 8388608;

    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
    ];

    public function __construct(
        private readonly AttachmentRepository $attachments = new AttachmentRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    public function store(string $entityType, int $entityId, array $file, int $userId): array
    {
        if (!in_array($entityType, ['customer', 'quote', 'opportunity'], true) || $entityId < 1) {
            return ['_form' => 'That record cannot take a file.'];
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['file' => 'Choose a file.'];
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['file' => 'The file did not upload. Try a smaller PDF or image.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            return ['file' => 'Files must be between 1 byte and 8 MB.'];
        }
        $original = (string) ($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$extension])) {
            return ['file' => 'Allowed files are PDF, JPG, PNG, WEBP, GIF, and TXT.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED[$extension], true)) {
            return ['file' => 'The file contents do not match its extension.'];
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($original)) ?? 'file';
        $safeName = trim($safeName, '-');
        if ($safeName === '') {
            $safeName = 'file.' . $extension;
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $directory = base_path('storage/uploads/' . $entityType . '/' . $entityId);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['file' => 'The upload folder is not available.'];
        }
        $target = $directory . '/' . $stored;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['file' => 'The file could not be stored.'];
        }
        chmod($target, 0640);

        Database::transaction(function () use ($entityType, $entityId, $safeName, $stored, $mime, $size, $userId): void {
            $id = $this->attachments->insert([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'original_filename' => $safeName,
                'stored_filename' => $entityType . '/' . $entityId . '/' . $stored,
                'mime_type' => $mime,
                'file_size' => $size,
                'uploaded_by' => $userId,
            ]);
            $this->audit->record('attachment', $id, 'uploaded', null, [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'filename' => $safeName,
            ], $userId);
        });

        return [];
    }

    /**
     * @return array{path: string, name: string, mime: string}|null
     */
    public function download(int $id): ?array
    {
        $row = $this->attachments->find($id);
        if ($row === null) {
            return null;
        }
        $relative = (string) $row['stored_filename'];
        if (str_contains($relative, '..')) {
            return null;
        }
        $path = base_path('storage/uploads/' . $relative);
        if (!is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => (string) $row['original_filename'],
            'mime' => (string) $row['mime_type'],
        ];
    }
}
