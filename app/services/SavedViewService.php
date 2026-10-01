<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Filters are named fields. sql, query, and script keys are dropped.
 */
final class SavedViewService
{
    /** @var list<string> */
    public const FILTERS = ['status', 'min_total', 'due', 'search', 'bucket'];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function save(int $userId, string $name, string $entityType, array $filter): int
    {
        $clean = [];
        foreach (self::FILTERS as $key) {
            if (!isset($filter[$key]) || is_array($filter[$key])) {
                continue;
            }
            $value = trim((string) $filter[$key]);
            if ($value === '' || SafeValue::executable($value)) {
                continue;
            }
            $clean[$key] = mb_substr($value, 0, 80);
        }

        return $this->platform->insertView($userId, mb_substr(trim($name), 0, 120), strtoupper($entityType), $clean);
    }
}
