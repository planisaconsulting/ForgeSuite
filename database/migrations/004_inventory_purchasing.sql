-- Sign-Forge Management System
-- Phase 4: inventory ledger, rolls, sheets, offcuts, purchasing, and receiving.
--
-- Apply this once to a database that already has Phases 1 to 3.
-- It does not drop customers, quotations, jobs, or existing usage rows.
-- A fresh install uses schema.sql, which already includes these tables.
-- products.stock_quantity is not a column and is not the stock balance.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @sf_inv_method = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'inventory_method'
);
SET @sf_inv_sql = IF(
    @sf_inv_method = 0,
    'ALTER TABLE products
        ADD COLUMN inventory_method VARCHAR(20) NOT NULL DEFAULT ''NONE'' AFTER track_stock,
        ADD COLUMN reorder_level DECIMAL(14,4) NULL AFTER minimum_stock_level,
        ADD COLUMN preferred_order_quantity DECIMAL(14,4) NULL AFTER reorder_level,
        ADD COLUMN costing_method VARCHAR(30) NOT NULL DEFAULT ''LAST_COST'' AFTER preferred_order_quantity,
        ADD COLUMN average_cost DECIMAL(14,4) NULL AFTER costing_method',
    'SELECT 1'
);
PREPARE sf_inv_stmt FROM @sf_inv_sql;
EXECUTE sf_inv_stmt;
DEALLOCATE PREPARE sf_inv_stmt;

CREATE TABLE IF NOT EXISTS stock_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_locations_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    inventory_type VARCHAR(20) NOT NULL,
    inventory_code VARCHAR(40) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    purchase_order_item_id INT UNSIGNED NULL,
    source_inventory_item_id INT UNSIGNED NULL,
    source_job_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
    received_date DATE NULL,
    expiry_date DATE NULL,
    original_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    remaining_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    acquisition_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    valuation_treatment VARCHAR(20) NULL,
    width_mm DECIMAL(10,2) NULL,
    length_mm DECIMAL(12,2) NULL,
    height_mm DECIMAL(10,2) NULL,
    batch_number VARCHAR(80) NULL,
    supplier_reference VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_items_code (inventory_code),
    KEY idx_inventory_items_product (product_id),
    KEY idx_inventory_items_location (stock_location_id),
    KEY idx_inventory_items_status (status),
    KEY idx_inventory_items_type (inventory_type, status),
    CONSTRAINT fk_inventory_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_source_item FOREIGN KEY (source_inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_source_job FOREIGN KEY (source_job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    movement_type VARCHAR(40) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    purchase_order_id INT UNSIGNED NULL,
    job_material_usage_id INT UNSIGNED NULL,
    reason VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    movement_date DATE NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_movements_product (product_id),
    KEY idx_stock_movements_location (stock_location_id),
    KEY idx_stock_movements_job (job_id),
    KEY idx_stock_movements_date (movement_date),
    KEY idx_stock_movements_item (inventory_item_id),
    KEY idx_stock_movements_po (purchase_order_id),
    KEY idx_stock_movements_usage (job_material_usage_id),
    CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_usage FOREIGN KEY (job_material_usage_id) REFERENCES job_material_usage (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_material_requirement_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'RESERVED',
    reserved_by INT UNSIGNED NULL,
    reserved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL DEFAULT NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_stock_reservations_job (job_id),
    KEY idx_stock_reservations_product (product_id),
    KEY idx_stock_reservations_status (status),
    KEY idx_stock_reservations_item (inventory_item_id),
    CONSTRAINT fk_stock_reservations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_requirement FOREIGN KEY (job_material_requirement_id) REFERENCES job_material_requirements (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_user FOREIGN KEY (reserved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    from_location_id INT UNSIGNED NOT NULL,
    to_location_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    reason VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_transfers_product (product_id),
    CONSTRAINT fk_stock_transfers_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_from FOREIGN KEY (from_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_to FOREIGN KEY (to_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_counts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_code VARCHAR(40) NOT NULL,
    stock_location_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_counts_code (reference_code),
    KEY idx_stock_counts_status (status),
    CONSTRAINT fk_stock_counts_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_counts_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_counts_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_count_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_count_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    system_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    physical_quantity DECIMAL(14,4) NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_stock_count_items_count (stock_count_id),
    KEY idx_stock_count_items_product (product_id),
    CONSTRAINT fk_stock_count_items_count FOREIGN KEY (stock_count_id) REFERENCES stock_counts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stock_count_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_count_items_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_count_items_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    supplier_sku VARCHAR(80) NULL,
    supplier_description VARCHAR(180) NULL,
    cost_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    minimum_order_quantity DECIMAL(14,4) NULL,
    lead_time_days INT UNSIGNED NULL,
    preferred_supplier TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_price_update TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_products (supplier_id, product_id),
    KEY idx_supplier_products_product (product_id),
    KEY idx_supplier_products_supplier (supplier_id),
    CONSTRAINT fk_supplier_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_products_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_product_id INT UNSIGNED NOT NULL,
    old_price DECIMAL(14,4) NOT NULL,
    new_price DECIMAL(14,4) NOT NULL,
    effective_date DATE NOT NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_supplier_price_history_product (supplier_product_id, created_at),
    CONSTRAINT fk_supplier_price_history_row FOREIGN KEY (supplier_product_id) REFERENCES supplier_products (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_price_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_number VARCHAR(40) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    supplier_reference VARCHAR(80) NULL,
    notes TEXT NULL,
    internal_notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_purchase_orders_number (po_number),
    KEY idx_purchase_orders_supplier (supplier_id),
    KEY idx_purchase_orders_status (status),
    KEY idx_purchase_orders_date (order_date),
    CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_orders_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_orders_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    supplier_product_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    ordered_quantity DECIMAL(14,4) NOT NULL,
    received_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_date DATE NULL,
    job_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_po_items_po (purchase_order_id),
    KEY idx_po_items_product (product_id),
    KEY idx_po_items_job (job_id),
    CONSTRAINT fk_po_items_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_po_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_items_supplier_product FOREIGN KEY (supplier_product_id) REFERENCES supplier_products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_po_items_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    grn_number VARCHAR(40) NOT NULL,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    supplier_delivery_note VARCHAR(80) NULL,
    supplier_invoice_number VARCHAR(80) NULL,
    received_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_goods_receipts_number (grn_number),
    KEY idx_goods_receipts_po (purchase_order_id),
    CONSTRAINT fk_goods_receipts_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_goods_receipts_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_goods_receipts_user FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    goods_receipt_id INT UNSIGNED NOT NULL,
    purchase_order_item_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_received DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    stock_location_id INT UNSIGNED NOT NULL,
    width_mm DECIMAL(10,2) NULL,
    length_mm DECIMAL(12,2) NULL,
    track_each TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_grn_items_receipt (goods_receipt_id),
    KEY idx_grn_items_po_item (purchase_order_item_id),
    CONSTRAINT fk_grn_items_receipt FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_grn_items_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_grn_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_grn_items_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    required_by DATE NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
    approved_by INT UNSIGNED NULL,
    purchase_order_id INT UNSIGNED NULL,
    purchase_order_item_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_purchase_requests_status (status),
    KEY idx_purchase_requests_job (job_id),
    KEY idx_purchase_requests_product (product_id),
    CONSTRAINT fk_purchase_requests_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sf_po_move = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements' AND CONSTRAINT_NAME = 'fk_stock_movements_po'
);
SET @sf_po_move_sql = IF(
    @sf_po_move = 0,
    'ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE sf_po_move_stmt FROM @sf_po_move_sql;
EXECUTE sf_po_move_stmt;
DEALLOCATE PREPARE sf_po_move_stmt;

SET @sf_item_po = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items' AND CONSTRAINT_NAME = 'fk_inventory_items_po_item'
);
SET @sf_item_po_sql = IF(
    @sf_item_po = 0,
    'ALTER TABLE inventory_items ADD CONSTRAINT fk_inventory_items_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE sf_item_po_stmt FROM @sf_item_po_sql;
EXECUTE sf_item_po_stmt;
DEALLOCATE PREPARE sf_item_po_stmt;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO permissions (code, name, module)
SELECT * FROM (
    SELECT 'inventory.view' AS code, 'View stock balances' AS name, 'inventory' AS module
    UNION ALL SELECT 'inventory.receive', 'Receive and open stock', 'inventory'
    UNION ALL SELECT 'inventory.consume', 'Consume stock on a job', 'inventory'
    UNION ALL SELECT 'inventory.transfer', 'Transfer stock between locations', 'inventory'
    UNION ALL SELECT 'inventory.adjust', 'Adjust stock and approve counts', 'inventory'
    UNION ALL SELECT 'inventory.count', 'Record a stock count', 'inventory'
    UNION ALL SELECT 'inventory.view_cost', 'See stock values and costs', 'inventory'
    UNION ALL SELECT 'inventory.override', 'Consume more stock than is available', 'inventory'
    UNION ALL SELECT 'purchasing.view', 'View purchase orders', 'purchasing'
    UNION ALL SELECT 'purchasing.create', 'Create purchase orders and requests', 'purchasing'
    UNION ALL SELECT 'purchasing.edit', 'Edit draft purchase orders', 'purchasing'
    UNION ALL SELECT 'purchasing.approve', 'Approve purchase orders', 'purchasing'
    UNION ALL SELECT 'purchasing.receive', 'Receive goods against a purchase order', 'purchasing'
    UNION ALL SELECT 'purchasing.cancel', 'Cancel a purchase order', 'purchasing'
    UNION ALL SELECT 'supplier_prices.view', 'View supplier prices', 'purchasing'
    UNION ALL SELECT 'supplier_prices.edit', 'Edit supplier prices', 'purchasing'
) AS incoming
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = incoming.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('inventory.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN ('inventory.view', 'inventory.consume', 'inventory.transfer')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN ('inventory.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'inventory.view', 'inventory.view_cost', 'purchasing.view', 'purchasing.receive',
    'supplier_prices.view', 'supplier_prices.edit'
)
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT v.setting_key, v.setting_value FROM (
    SELECT 'po_prefix' AS setting_key, 'SFPO' AS setting_value
    UNION ALL SELECT 'grn_prefix', 'SFGRN'
    UNION ALL SELECT 'roll_prefix', 'ROL'
    UNION ALL SELECT 'sheet_prefix', 'SHT'
    UNION ALL SELECT 'offcut_prefix', 'OFC'
    UNION ALL SELECT 'batch_prefix', 'BAT'
    UNION ALL SELECT 'default_costing_method', 'LAST_COST'
    UNION ALL SELECT 'offcut_valuation', 'REDUCED_COST'
    UNION ALL SELECT 'offcut_value_percent', '50'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = v.setting_key);

INSERT INTO stock_locations (code, name, description, active)
SELECT v.code, v.name, v.description, 1 FROM (
    SELECT 'MAIN' AS code, 'Main Store' AS name, 'Primary stores' AS description
    UNION ALL SELECT 'WORKSHOP', 'Workshop', 'Fabrication and assembly'
    UNION ALL SELECT 'VEHICLE', 'Installation Vehicle', 'Material loaded for site work'
    UNION ALL SELECT 'INSTALLATION', 'Installation', 'Issued to an installation'
    UNION ALL SELECT 'OFFCUTS', 'Offcut Rack', 'Usable offcuts'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM stock_locations l WHERE l.code = v.code);

UPDATE products SET inventory_method = 'ROLL', track_stock = 1
WHERE sku IN ('SF-PV-1300', 'SF-PV-1600', 'SF-LAM-1300') AND inventory_method = 'NONE';
UPDATE products SET inventory_method = 'SHEET', track_stock = 1
WHERE sku IN ('SF-CHR-2450', 'SF-ACM-2440', 'SF-PX-3MM') AND inventory_method = 'NONE';
UPDATE products SET inventory_method = 'UNIT', track_stock = 1
WHERE sku IN ('SF-LED-MOD', 'SF-PSU-12V') AND inventory_method = 'NONE';
UPDATE products SET inventory_method = 'LENGTH', track_stock = 1
WHERE sku = 'SF-ST-2525' AND inventory_method = 'NONE';
UPDATE products SET inventory_method = 'NONE', track_stock = 0
WHERE sku IN ('SF-LAB-DES', 'SF-LAB-INS');
