<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\AttachmentRepository;

/**
 * Files for a customer, opportunity, quotation, job, artwork, or installation.
 * They are stored outside the public folder and downloaded through PHP.
 * Stored names are generated. The original name stays in the database.
 */
final class AttachmentService
{
    private const MAX_BYTES = 8388608;

    /** @var list<string> */
    private const ENTITIES = ['customer', 'quote', 'opportunity', 'job', 'job_item', 'artwork', 'installation', 'site_survey', 'invoice'];

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
    public function store(
        string $entityType,
        int $entityId,
        array $file,
        int $userId,
        string $purpose = 'GENERAL',
        ?string $notes = null,
        bool $requireUpload = true,
        ?array $meta = null
    ): array {
        if (!in_array($entityType, self::ENTITIES, true) || $entityId < 1) {
            return ['_form' => 'That record cannot take a file.'];
        }
        $checked = $this->inspect($file, $requireUpload);
        if (!$checked['ok']) {
            return $checked['errors'];
        }
        $placed = $this->place($entityType, $entityId, $checked, $requireUpload);
        if (isset($placed['errors'])) {
            return $placed['errors'];
        }
        $purpose = strtoupper(preg_replace('/[^A-Z_]/', '', strtoupper($purpose)) ?? 'GENERAL');
        if ($purpose === '') {
            $purpose = 'GENERAL';
        }
        Database::transaction(function () use ($entityType, $entityId, $checked, $placed, $userId, $purpose, $notes, $meta): void {
            $id = $this->attachments->insert([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'original_filename' => $checked['original'],
                'stored_filename' => $placed['relative'],
                'mime_type' => $checked['mime'],
                'file_size' => $checked['size'],
                'purpose' => $purpose,
                'notes' => blank_to_null($notes),
                'uploaded_by' => $userId > 0 ? $userId : null,
            ]);
            if ($meta !== null) {
                $this->attachments->applyMeta($id, $meta);
            }
            $this->audit->record('attachment', $id, 'uploaded', null, [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'filename' => $checked['original'],
                'purpose' => $purpose,
            ], $userId);
        });

        return [];
    }

    /**
     * Check extension, MIME type, and size. PHP and other scripts are refused.
     *
     * @param array<string, mixed> $file
     * @return array{ok: bool, errors: array<string, string>, original: string, extension: string, mime: string, size: int, tmp: string}
     */
    public function inspect(array $file, bool $requireUpload = true): array
    {
        $empty = ['ok' => false, 'errors' => [], 'original' => '', 'extension' => '', 'mime' => '', 'size' => 0, 'tmp' => ''];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            $empty['errors'] = ['file' => 'Choose a file.'];

            return $empty;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $uploaded = $requireUpload ? ($error === UPLOAD_ERR_OK && is_uploaded_file($tmp)) : ($error === UPLOAD_ERR_OK && is_file($tmp));
        if (!$uploaded) {
            $empty['errors'] = ['file' => 'The file did not upload. Try a smaller PDF or image.'];

            return $empty;
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            $size = (int) filesize($tmp);
        }
        if ($size < 1 || $size > self::MAX_BYTES) {
            $empty['errors'] = ['file' => 'Files must be between 1 byte and 8 MB.'];

            return $empty;
        }
        $original = (string) ($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $lowerName = strtolower($original);
        if (!isset(self::ALLOWED[$extension]) || str_contains($lowerName, '.php') || str_contains($lowerName, '.phtml') || str_contains($lowerName, '.phar')) {
            $empty['errors'] = ['file' => 'Allowed files are PDF, JPG, PNG, WEBP, GIF, and TXT.'];

            return $empty;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED[$extension], true)) {
            $empty['errors'] = ['file' => 'The file contents do not match its extension.'];

            return $empty;
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($original)) ?? 'file';
        $safeName = trim($safeName, '-');
        if ($safeName === '') {
            $safeName = 'file.' . $extension;
        }

        return [
            'ok' => true,
            'errors' => [],
            'original' => $safeName,
            'extension' => $extension,
            'mime' => $mime,
            'size' => $size,
            'tmp' => $tmp,
        ];
    }

    /**
     * @param array{original: string, extension: string, mime: string, size: int, tmp: string} $checked
     * @return array{relative: string}|array{errors: array<string, string>}
     */
    public function place(string $entityType, int $entityId, array $checked, bool $requireUpload = true): array
    {
        $stored = bin2hex(random_bytes(16)) . '.' . $checked['extension'];
        $directory = base_path('storage/uploads/' . $entityType . '/' . $entityId);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['errors' => ['file' => 'The upload folder is not available.']];
        }
        $target = $directory . '/' . $stored;
        $moved = $requireUpload
            ? move_uploaded_file($checked['tmp'], $target)
            : copy($checked['tmp'], $target);
        if (!$moved) {
            return ['errors' => ['file' => 'The file could not be stored.']];
        }
        chmod($target, 0640);
        $this->writePreview($target, $checked['extension']);

        return ['relative' => $entityType . '/' . $entityId . '/' . $stored];
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

    /**
     * A smaller JPEG beside the original. The original file is left as stored.
     */
    private function writePreview(string $path, string $extension): void
    {
        if (!in_array($extension, ['jpg', 'jpeg'], true) || !function_exists('imagecreatefromjpeg')) {
            return;
        }
        $size = filesize($path);
        if ($size === false || $size < 1500000) {
            return;
        }
        $image = @imagecreatefromjpeg($path);
        if ($image === false) {
            return;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1600;
        if ($width > $max) {
            $scale = $max / $width;
            $copy = imagescale($image, $max, (int) max(1, round($height * $scale)));
            imagedestroy($image);
            if ($copy === false) {
                return;
            }
            $image = $copy;
        }
        imagejpeg($image, $path . '.preview.jpg', 75);
        imagedestroy($image);
    }
}
