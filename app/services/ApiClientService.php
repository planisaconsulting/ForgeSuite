<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForecastRepository;

/**
 * API clients authenticate with identifier.secret.
 * The secret is hashed. The plain value is returned once, when the client is created.
 */
final class ApiClientService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @param list<string> $scopes
     * @return array{id: int, identifier: string, secret: string}
     */
    public function create(string $name, array $scopes, int $userId, ?string $expiresAt = null): array
    {
        $identifier = 'sf_' . bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(24));
        $id = $this->planning->insertApiClient([
            'name' => mb_substr(trim($name), 0, 180),
            'client_identifier' => $identifier,
            'secret_hash' => hash('sha256', $secret),
            'scopes_json' => json_encode(array_values($scopes), JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ]);

        return ['id' => $id, 'identifier' => $identifier, 'secret' => $secret];
    }

    /**
     * @return array{client: array<string, mixed>, scopes: list<string>}|null
     */
    public function authenticate(string $header): ?array
    {
        $header = trim($header);
        if (str_starts_with(strtolower($header), 'bearer ')) {
            $header = trim(substr($header, 7));
        }
        $parts = explode('.', $header, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        $client = $this->planning->apiClientByIdentifier($parts[0]);
        if ($client === null || (int) $client['active'] !== 1 || $client['revoked_at'] !== null) {
            return null;
        }
        if ($client['expires_at'] !== null && strtotime((string) $client['expires_at']) < time()) {
            return null;
        }
        if (!hash_equals((string) $client['secret_hash'], hash('sha256', $parts[1]))) {
            return null;
        }
        $scopes = json_decode((string) ($client['scopes_json'] ?? '[]'), true);
        if (!is_array($scopes)) {
            $scopes = [];
        }
        $this->planning->touchApiClient((int) $client['id']);

        return ['client' => $client, 'scopes' => array_map('strval', $scopes)];
    }

    public function allows(array $scopes, string $required): bool
    {
        return in_array($required, $scopes, true);
    }
}
