<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WorkshopRepository;

/**
 * Stores a signature image with the statement, the signer, and the server time.
 * The image alone is not treated as proof.
 */
final class SignatureService
{
    public function __construct(private readonly WorkshopRepository $workshop = new WorkshopRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function capture(string $entityType, int $entityId, array $input, int $userId): array
    {
        $name = trim((string) ($input['signer_name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['signer_name' => 'Who is signing?'], 'id' => null];
        }
        $statement = trim((string) ($input['statement'] ?? ''));
        if ($statement === '') {
            $statement = (string) SettingsService::get(
                'completion_acceptance_statement',
                'I confirm that the listed goods/services were received/completed.'
            );
        }
        $binary = $this->png($input['signature_png'] ?? '');
        if ($binary === null) {
            return ['errors' => ['signature_png' => 'Draw a signature before saving.'], 'id' => null];
        }
        $relative = $this->write($binary);
        $id = $this->workshop->insertSignature([
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
            'signer_name' => mb_substr($name, 0, 120),
            'signer_contact' => blank_to_null($input['signer_contact'] ?? null),
            'statement_text' => $statement,
            'statement_version' => (string) SettingsService::get('acceptance_statement_version', '1'),
            'file_path' => $relative,
            'captured_by' => $userId,
            'signed_at' => date('Y-m-d H:i:s'),
            'client_signed_at' => $this->clientTime($input['client_signed_at'] ?? null),
        ]);

        return ['errors' => [], 'id' => $id];
    }

    private function png(mixed $raw): ?string
    {
        $text = trim((string) $raw);
        if (str_starts_with($text, 'data:image/png;base64,')) {
            $text = substr($text, strlen('data:image/png;base64,'));
        }
        $binary = base64_decode($text, true);
        if (!is_string($binary) || strlen($binary) < 16 || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        if (strlen($binary) > 1_500_000) {
            return null;
        }

        return $binary;
    }

    private function write(string $binary): string
    {
        $dir = base_path('storage/signatures');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'sig-' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($dir . '/' . $name, $binary);

        return 'storage/signatures/' . $name;
    }

    private function clientTime(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $stamp = strtotime($text);

        return $stamp === false ? null : date('Y-m-d H:i:s', $stamp);
    }
}
