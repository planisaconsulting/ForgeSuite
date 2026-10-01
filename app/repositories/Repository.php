<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

/**
 * Shared prepared-statement helpers.
 *
 * Repositories are the only place that should contain SQL. Services decide
 * what a save means. Views only print the rows they are given.
 */
abstract class Repository
{
    protected function pdo(): PDO
    {
        return Database::connection();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function rows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return $rows === false ? [] : $rows;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    protected function run(string $sql, array $params = []): void
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    protected function insertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }
}
