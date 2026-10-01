<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Database;
use PDO;

/**
 * Figures for the home screen.
 *
 * Quotation value this month is the sum of quote totals dated in the current
 * calendar month, excluding DECLINED and EXPIRED. Drafts are included because
 * they are still live pricing work. Totals are ex-VAT or VAT-inclusive as
 * stored on each quote; this screen does not recalculate them.
 */
final class Dashboard
{
    /**
     * @return array{
     *     products: int,
     *     customers: int,
     *     drafts: int,
     *     quotes_this_month: int,
     *     month_value: string,
     *     recent: list<array<string, mixed>>
     * }
     */
    public static function summary(): array
    {
        $today = new \DateTimeImmutable('now');
        $start = $today->modify('first day of this month')->format('Y-m-d');
        $end = $today->modify('first day of next month')->format('Y-m-d');

        return [
            'products' => (int) self::scalar(
                'SELECT COUNT(*) FROM products WHERE active = 1'
            ),
            'customers' => (int) self::scalar(
                'SELECT COUNT(*) FROM customers WHERE active = 1'
            ),
            'drafts' => (int) self::scalar(
                "SELECT COUNT(*) FROM quotes WHERE status = 'DRAFT'"
            ),
            'quotes_this_month' => (int) self::scalar(
                'SELECT COUNT(*) FROM quotes WHERE quote_date >= :start AND quote_date < :end',
                ['start' => $start, 'end' => $end]
            ),
            'month_value' => self::scalar(
                "SELECT COALESCE(SUM(total), 0) FROM quotes
                 WHERE quote_date >= :start AND quote_date < :end
                   AND status NOT IN ('DECLINED', 'EXPIRED')",
                ['start' => $start, 'end' => $end]
            ),
            'recent' => self::recent(),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private static function scalar(string $sql, array $params = []): string
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? '0' : (string) $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function recent(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT q.quote_number, q.quote_date, q.status, q.total, c.company_name
             FROM quotes q
             LEFT JOIN customers c ON c.id = q.customer_id
             ORDER BY q.created_at DESC, q.id DESC
             LIMIT 8'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
