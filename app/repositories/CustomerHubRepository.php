<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Customer hub reads always take the customer id from the portal session.
 */
final class CustomerHubRepository extends Repository
{
    public function catalogue(int $customerId, int $id): ?array
    {
        return $this->one('SELECT * FROM customer_catalogues WHERE id = ? AND customer_id = ? LIMIT 1', [$id, $customerId]);
    }

    public function catalogues(int $customerId, int $limit, int $offset): array
    {
        return $this->rows(
            'SELECT id, customer_id, name, status, pricing_mode FROM customer_catalogues WHERE customer_id = ? ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            [$customerId]
        );
    }

    public function insertCatalogue(array $row): int
    {
        $this->run(
            'INSERT INTO customer_catalogues (customer_id, name, description, status, valid_from, valid_until, pricing_mode, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [$row['customer_id'], $row['name'], $row['description'], $row['status'], $row['valid_from'], $row['valid_until'], $row['pricing_mode'], $row['created_by']]
        );

        return $this->insertId();
    }

    public function setCatalogueStatus(int $id, string $status, ?int $approvedBy): void
    {
        $this->run('UPDATE customer_catalogues SET status = ?, approved_by = ? WHERE id = ?', [$status, $approvedBy, $id]);
    }

    public function insertItem(array $row): int
    {
        $this->run(
            'INSERT INTO customer_catalogue_items
                (catalogue_id, product_id, specification_id, customer_code, internal_code, name, description, locked_config_json, allowed_variables_json, price_visibility, availability_label, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['catalogue_id'], $row['product_id'], $row['specification_id'], $row['customer_code'], $row['internal_code'],
                $row['name'], $row['description'], $row['locked_config_json'], $row['allowed_variables_json'],
                $row['price_visibility'], $row['availability_label'], $row['status'],
            ]
        );

        return $this->insertId();
    }

    public function itemForCustomer(int $customerId, int $itemId): ?array
    {
        return $this->one(
            'SELECT i.*, c.customer_id, c.status AS catalogue_status, c.pricing_mode
             FROM customer_catalogue_items i
             INNER JOIN customer_catalogues c ON c.id = i.catalogue_id
             WHERE i.id = ? AND c.customer_id = ? LIMIT 1',
            [$itemId, $customerId]
        );
    }

    public function items(int $catalogueId, int $limit, int $offset): array
    {
        return $this->rows(
            'SELECT id, catalogue_id, customer_code, internal_code, name, price_visibility, availability_label, status
             FROM customer_catalogue_items WHERE catalogue_id = ? ORDER BY id LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            [$catalogueId]
        );
    }

    public function insertPrice(array $row): int
    {
        $this->run(
            'INSERT INTO customer_catalogue_prices
                (catalogue_item_id, price_ex_vat, unit, currency_code, effective_from, effective_to, min_quantity, max_quantity, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $row['catalogue_item_id'], $row['price_ex_vat'], $row['unit'], $row['currency_code'],
                $row['effective_from'], $row['effective_to'], $row['min_quantity'], $row['max_quantity'], $row['status'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function closePrice(int $id, string $until): void
    {
        $this->run('UPDATE customer_catalogue_prices SET effective_to = ? WHERE id = ? AND (effective_to IS NULL OR effective_to > ?)', [$until, $id, $until]);
    }

    public function openPrice(int $itemId, string $date): ?array
    {
        return $this->one(
            'SELECT * FROM customer_catalogue_prices
             WHERE catalogue_item_id = ? AND status = \'ACTIVE\' AND effective_from <= ?
               AND (effective_to IS NULL OR effective_to >= ?)
             ORDER BY effective_from DESC, id DESC LIMIT 1',
            [$itemId, $date, $date]
        );
    }

    public function latestPrice(int $itemId): ?array
    {
        return $this->one(
            'SELECT * FROM customer_catalogue_prices WHERE catalogue_item_id = ? AND status = \'ACTIVE\' ORDER BY effective_from DESC, id DESC LIMIT 1',
            [$itemId]
        );
    }

    public function insertOrder(array $row): int
    {
        $this->run(
            'INSERT INTO customer_orders
                (order_number, customer_id, portal_user_id, order_kind, status, customer_po, cost_centre, department, branch_reference, campaign_code, idempotency_key, requested_date, notes, submitted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['order_number'], $row['customer_id'], $row['portal_user_id'], $row['order_kind'], $row['status'],
                $row['customer_po'], $row['cost_centre'], $row['department'], $row['branch_reference'], $row['campaign_code'],
                $row['idempotency_key'], $row['requested_date'], $row['notes'], $row['submitted_at'],
            ]
        );

        return $this->insertId();
    }

    public function orderByKey(string $key): ?array
    {
        return $this->one('SELECT * FROM customer_orders WHERE idempotency_key = ? LIMIT 1', [$key]);
    }

    public function order(int $customerId, int $id): ?array
    {
        return $this->one('SELECT * FROM customer_orders WHERE id = ? AND customer_id = ? LIMIT 1', [$id, $customerId]);
    }

    public function orderItem(int $id): ?array
    {
        return $this->one('SELECT * FROM customer_order_items WHERE id = ? LIMIT 1', [$id]);
    }

    public function insertOrderItem(array $row): int
    {
        $this->run(
            'INSERT INTO customer_order_items
                (order_id, catalogue_item_id, product_id, artwork_id, description, quantity, unit_price_ex_vat, vat_amount, line_total, pricing_source, configuration_json, customer_reference, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['order_id'], $row['catalogue_item_id'], $row['product_id'], $row['artwork_id'], $row['description'],
                $row['quantity'], $row['unit_price_ex_vat'], $row['vat_amount'], $row['line_total'], $row['pricing_source'],
                $row['configuration_json'], $row['customer_reference'], $row['notes'],
            ]
        );

        return $this->insertId();
    }

    public function insertOrderSite(int $itemId, int $siteId, string $qty, ?string $date, ?string $notes): void
    {
        $this->run(
            'INSERT INTO customer_order_sites (order_item_id, project_site_id, quantity, required_date, notes) VALUES (?,?,?,?,?)',
            [$itemId, $siteId, $qty, $date, $notes]
        );
    }

    public function orderSites(int $itemId): array
    {
        return $this->rows('SELECT * FROM customer_order_sites WHERE order_item_id = ? ORDER BY id', [$itemId]);
    }

    public function setOrderStatus(int $id, string $status, ?int $userId): void
    {
        $this->run(
            'UPDATE customer_orders SET status = ?, reviewed_at = NOW(), reviewed_by = ?, first_response_at = COALESCE(first_response_at, NOW()) WHERE id = ?',
            [$status, $userId, $id]
        );
    }

    public function searchOrders(int $customerId, string $q, int $limit): array
    {
        $like = '%' . $q . '%';

        return $this->rows(
            'SELECT o.id, o.order_number, o.status, o.customer_po
             FROM customer_orders o
             WHERE o.customer_id = ? AND (o.order_number LIKE ? OR o.customer_po LIKE ?)
             ORDER BY o.id DESC LIMIT ' . (int) $limit,
            [$customerId, $like, $like]
        );
    }

    public function insertQuoteRequest(array $row): int
    {
        $this->run(
            'INSERT INTO customer_quote_requests (customer_id, portal_user_id, request_type, payload_json, status) VALUES (?,?,?,?,?)',
            [$row['customer_id'], $row['portal_user_id'], $row['request_type'], $row['payload_json'], 'SUBMITTED']
        );

        return $this->insertId();
    }

    public function insertTemplate(int $customerId, int $userId, string $name, string $json): int
    {
        $this->run(
            'INSERT INTO customer_order_templates (customer_id, portal_user_id, name, items_json) VALUES (?,?,?,?)',
            [$customerId, $userId, $name, $json]
        );

        return $this->insertId();
    }

    public function insertBundle(int $catalogueId, string $name, string $mode, ?string $fixed): int
    {
        $this->run(
            'INSERT INTO customer_catalogue_bundles (catalogue_id, name, pricing_mode, fixed_price_ex_vat, status) VALUES (?,?,?,?,\'ACTIVE\')',
            [$catalogueId, $name, $mode, $fixed]
        );

        return $this->insertId();
    }

    public function insertBundleItem(int $bundleId, int $itemId, string $qty): void
    {
        $this->run(
            'INSERT INTO customer_catalogue_bundle_items (bundle_id, catalogue_item_id, quantity) VALUES (?,?,?)',
            [$bundleId, $itemId, $qty]
        );
    }

    public function grantSite(int $userId, int $siteId): void
    {
        $this->run('INSERT IGNORE INTO portal_user_sites (portal_user_id, project_site_id) VALUES (?,?)', [$userId, $siteId]);
    }

    public function siteForCustomer(int $customerId, int $siteId): ?array
    {
        return $this->one(
            'SELECT s.* FROM project_sites s INNER JOIN projects p ON p.id = s.project_id WHERE s.id = ? AND p.customer_id = ? LIMIT 1',
            [$siteId, $customerId]
        );
    }

    public function siteGranted(int $userId, int $siteId): bool
    {
        $row = $this->one('SELECT 1 AS ok FROM portal_user_sites WHERE portal_user_id = ? AND project_site_id = ? LIMIT 1', [$userId, $siteId]);

        return $row !== null;
    }

    /**
     * @return list<int>
     */
    public function grantedSiteIds(int $userId): array
    {
        $ids = [];
        foreach ($this->rows('SELECT project_site_id FROM portal_user_sites WHERE portal_user_id = ?', [$userId]) as $row) {
            $ids[] = (int) $row['project_site_id'];
        }

        return $ids;
    }

    public function projectForCustomer(int $customerId, int $projectId): ?array
    {
        return $this->one('SELECT id, project_number, name, status FROM projects WHERE id = ? AND customer_id = ? LIMIT 1', [$projectId, $customerId]);
    }

    public function siteStatusCounts(int $projectId, array $siteIds): array
    {
        $sql = 'SELECT status, COUNT(*) AS n FROM project_sites WHERE project_id = ?';
        $params = [$projectId];
        if ($siteIds !== []) {
            $sql .= ' AND id IN (' . implode(',', array_map('intval', $siteIds)) . ')';
        }
        $sql .= ' GROUP BY status';

        return $this->rows($sql, $params);
    }

    public function matchingSite(int $customerId, string $code, string $address): ?array
    {
        return $this->one(
            'SELECT s.id, s.site_code, s.address_line_1 FROM project_sites s
             INNER JOIN projects p ON p.id = s.project_id
             WHERE p.customer_id = ? AND (s.site_code = ? OR s.address_line_1 = ?) LIMIT 1',
            [$customerId, $code, $address]
        );
    }

    public function insertSiteRequest(array $row): int
    {
        $this->run(
            'INSERT INTO portal_site_requests (customer_id, portal_user_id, site_name, address, branch_code, status, duplicate_warning) VALUES (?,?,?,?,?,?,?)',
            [$row['customer_id'], $row['portal_user_id'], $row['site_name'], $row['address'], $row['branch_code'], 'PENDING', $row['duplicate_warning']]
        );

        return $this->insertId();
    }

    public function insertCancellation(array $row): int
    {
        $this->run(
            'INSERT INTO portal_cancellation_requests (customer_id, portal_user_id, job_id, reason, status, impact) VALUES (?,?,?,?,\'REQUESTED\',?)',
            [$row['customer_id'], $row['portal_user_id'], $row['job_id'], $row['reason'], $row['impact']]
        );

        return $this->insertId();
    }

    public function portalMessages(int $customerId, string $type, int $entityId): array
    {
        return $this->rows(
            'SELECT id, body, created_at FROM portal_messages WHERE customer_id = ? AND entity_type = ? AND entity_id = ? AND visibility = \'CUSTOMER\' ORDER BY id',
            [$customerId, $type, $entityId]
        );
    }

    public function insertNote(int $customerId, ?int $userId, string $type, int $entityId, string $body, string $visibility): void
    {
        $this->run(
            'INSERT INTO portal_messages (customer_id, portal_user_id, entity_type, entity_id, body, visibility) VALUES (?,?,?,?,?,?)',
            [$customerId, $userId, $type, $entityId, $body, $visibility]
        );
    }

    public function insertColour(array $row): int
    {
        $this->run(
            'INSERT INTO customer_brand_colours (customer_id, name, pantone, cmyk, rgb, ral, vinyl_code, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$row['customer_id'], $row['name'], $row['pantone'], $row['cmyk'], $row['rgb'], $row['ral'], $row['vinyl_code'], $row['notes']]
        );

        return $this->insertId();
    }

    public function inbox(): array
    {
        $one = function (string $sql): int {
            $row = $this->one($sql);

            return (int) ($row['n'] ?? 0);
        };

        return [
            'quote_requests' => $one("SELECT COUNT(*) AS n FROM customer_quote_requests WHERE status = 'SUBMITTED'"),
            'new_orders' => $one("SELECT COUNT(*) AS n FROM customer_orders WHERE status = 'SUBMITTED'"),
            'info' => $one("SELECT COUNT(*) AS n FROM customer_orders WHERE status = 'INFO_REQUIRED'"),
            'cancellations' => $one("SELECT COUNT(*) AS n FROM portal_cancellation_requests WHERE status = 'REQUESTED'"),
        ];
    }
}
