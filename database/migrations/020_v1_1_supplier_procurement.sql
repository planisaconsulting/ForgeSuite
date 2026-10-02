-- v1.1 Phase 5: supplier RFQs, quotations, contract prices, warehouse bins, lots, and receiving.
-- Extends the existing purchase order and goods receipt tables. Does not replace them.

ALTER TABLE stock_locations
    ADD COLUMN parent_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN location_type VARCHAR(20) NOT NULL DEFAULT 'WAREHOUSE' AFTER name,
    ADD KEY idx_stock_locations_parent (parent_id),
    ADD KEY idx_stock_locations_type (location_type);

ALTER TABLE goods_receipts
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'CONFIRMED' AFTER supplier_invoice_number,
    ADD COLUMN idempotency_key VARCHAR(80) NULL AFTER notes,
    ADD UNIQUE KEY uq_goods_receipts_key (idempotency_key);

ALTER TABLE purchase_requests
    ADD COLUMN project_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN source VARCHAR(40) NOT NULL DEFAULT 'MANUAL' AFTER reason,
    ADD KEY idx_purchase_requests_project (project_id);

ALTER TABLE supplier_price_history
    ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'MANUAL' AFTER effective_date;

ALTER TABLE products
    ADD COLUMN tracking_mode VARCHAR(20) NOT NULL DEFAULT 'NONE' AFTER inventory_method;

CREATE TABLE supplier_rfqs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rfq_number VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    title VARCHAR(180) NOT NULL,
    purchase_request_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    required_by_date DATE NULL,
    response_deadline DATETIME NULL,
    delivery_location_id INT UNSIGNED NULL,
    currency_code VARCHAR(3) NOT NULL DEFAULT 'ZAR',
    instructions TEXT NULL,
    terms TEXT NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    sent_at DATETIME NULL,
    closed_at DATETIME NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_rfqs_number (rfq_number),
    KEY idx_supplier_rfqs_status (status, response_deadline),
    KEY idx_supplier_rfqs_project (project_id),
    KEY idx_supplier_rfqs_job (job_id),
    CONSTRAINT fk_supplier_rfqs_request FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_rfqs_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_rfq_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rfq_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    specification VARCHAR(255) NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    preferred_brand VARCHAR(80) NULL,
    equivalent_allowed TINYINT(1) NOT NULL DEFAULT 0,
    required_date DATE NULL,
    project_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    material_requirement_id INT UNSIGNED NULL,
    source_kind VARCHAR(40) NOT NULL DEFAULT 'MANUAL',
    PRIMARY KEY (id),
    KEY idx_rfq_items_rfq (rfq_id),
    KEY idx_rfq_items_product (product_id),
    KEY idx_rfq_items_job (job_id),
    CONSTRAINT fk_rfq_items_rfq FOREIGN KEY (rfq_id) REFERENCES supplier_rfqs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_rfq_item_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rfq_item_id INT UNSIGNED NOT NULL,
    source_kind VARCHAR(40) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    purchase_request_id INT UNSIGNED NULL,
    production_release_id INT UNSIGNED NULL,
    required_date DATE NULL,
    brand_restriction VARCHAR(80) NULL,
    PRIMARY KEY (id),
    KEY idx_rfq_sources_item (rfq_item_id),
    KEY idx_rfq_sources_job (job_id),
    CONSTRAINT fk_rfq_sources_item FOREIGN KEY (rfq_item_id) REFERENCES supplier_rfq_items (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_rfq_invitations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rfq_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    token_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    sent_at DATETIME NULL,
    viewed_at DATETIME NULL,
    responded_at DATETIME NULL,
    expires_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rfq_invite (rfq_id, supplier_id),
    UNIQUE KEY uq_rfq_invite_token (token_hash),
    KEY idx_rfq_invite_supplier (supplier_id, status),
    CONSTRAINT fk_rfq_invite_rfq FOREIGN KEY (rfq_id) REFERENCES supplier_rfqs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rfq_invite_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_quotations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_number VARCHAR(40) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    rfq_id INT UNSIGNED NOT NULL,
    invitation_id INT UNSIGNED NULL,
    supplier_reference VARCHAR(80) NULL,
    quote_date DATE NULL,
    valid_until DATE NULL,
    currency_code VARCHAR(3) NOT NULL DEFAULT 'ZAR',
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    delivery_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    lead_time_days INT UNSIGNED NULL,
    earliest_delivery DATE NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    submitted_at DATETIME NULL,
    file_name VARCHAR(180) NULL,
    stored_name VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_quotations_number (quote_number),
    KEY idx_supplier_quotations_rfq (rfq_id, supplier_id),
    KEY idx_supplier_quotations_status (status),
    CONSTRAINT fk_supplier_quotations_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_quotations_rfq FOREIGN KEY (rfq_id) REFERENCES supplier_rfqs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_quotations_invite FOREIGN KEY (invitation_id) REFERENCES supplier_rfq_invitations (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_quotation_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quotation_id INT UNSIGNED NOT NULL,
    rfq_item_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    offered_description VARCHAR(180) NOT NULL,
    offered_code VARCHAR(80) NULL,
    brand VARCHAR(80) NULL,
    requested_quantity DECIMAL(14,4) NOT NULL,
    available_quantity DECIMAL(14,4) NOT NULL,
    moq DECIMAL(14,4) NULL,
    pack_size DECIMAL(14,4) NULL,
    unit_price DECIMAL(14,4) NOT NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    lead_time_days INT UNSIGNED NULL,
    delivery_date DATE NULL,
    alternative TINYINT(1) NOT NULL DEFAULT 0,
    technical_status VARCHAR(30) NOT NULL DEFAULT 'NOT_REQUIRED',
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_quotation_items_quote (quotation_id),
    KEY idx_quotation_items_rfq_item (rfq_item_id),
    CONSTRAINT fk_quotation_items_quote FOREIGN KEY (quotation_id) REFERENCES supplier_quotations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quotation_items_rfq_item FOREIGN KEY (rfq_item_id) REFERENCES supplier_rfq_items (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_rfq_awards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rfq_id INT UNSIGNED NOT NULL,
    quotation_item_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    reason_code VARCHAR(40) NOT NULL,
    purchase_order_id INT UNSIGNED NULL,
    awarded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rfq_awards_rfq (rfq_id),
    KEY idx_rfq_awards_po (purchase_order_id),
    CONSTRAINT fk_rfq_awards_rfq FOREIGN KEY (rfq_id) REFERENCES supplier_rfqs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rfq_awards_item FOREIGN KEY (quotation_item_id) REFERENCES supplier_quotation_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_rfq_awards_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_contract_prices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    supplier_product_id INT UNSIGNED NULL,
    agreed_price DECIMAL(14,4) NOT NULL,
    currency_code VARCHAR(3) NOT NULL DEFAULT 'ZAR',
    moq DECIMAL(14,4) NULL,
    pack_size DECIMAL(14,4) NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    contract_reference VARCHAR(80) NULL,
    delivery_terms VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contract_prices_lookup (supplier_id, product_id, status, effective_from),
    CONSTRAINT fk_contract_prices_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_contract_prices_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_po_confirmations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    confirmed_quantity DECIMAL(14,4) NULL,
    confirmed_delivery_date DATE NULL,
    supplier_reference VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    confirmed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_po_confirmations_po (purchase_order_id),
    CONSTRAINT fk_po_confirmations_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_po_change_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    change_type VARCHAR(40) NOT NULL,
    detail VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_po_changes_po (purchase_order_id, status),
    CONSTRAINT fk_po_changes_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_revisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_po_revisions (purchase_order_id, revision_number),
    CONSTRAINT fk_po_revisions_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receiving_exceptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    goods_receipt_id INT UNSIGNED NULL,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    exception_type VARCHAR(40) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    notes VARCHAR(255) NULL,
    photo_name VARCHAR(80) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_receiving_exceptions_po (purchase_order_id, status),
    CONSTRAINT fk_receiving_exceptions_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_returns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    return_number VARCHAR(40) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    purchase_order_id INT UNSIGNED NULL,
    goods_receipt_id INT UNSIGNED NULL,
    reason_code VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    credit_reference VARCHAR(80) NULL,
    credit_value DECIMAL(14,2) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_returns_number (return_number),
    KEY idx_supplier_returns_supplier (supplier_id, status),
    CONSTRAINT fk_supplier_returns_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_return_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    return_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_supplier_return_items_return (return_id),
    CONSTRAINT fk_supplier_return_items_return FOREIGN KEY (return_id) REFERENCES supplier_returns (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_lots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    lot_code VARCHAR(80) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    goods_receipt_id INT UNSIGNED NULL,
    quantity_received DECIMAL(14,4) NOT NULL,
    quantity_remaining DECIMAL(14,4) NOT NULL,
    received_date DATE NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_lots (product_id, lot_code),
    KEY idx_inventory_lots_code (lot_code),
    KEY idx_inventory_lots_grn (goods_receipt_id),
    CONSTRAINT fk_inventory_lots_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_lot_uses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lot_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    asset_id INT UNSIGNED NULL,
    quantity DECIMAL(14,4) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_lot_uses_lot (lot_id),
    KEY idx_lot_uses_job (job_id),
    KEY idx_lot_uses_asset (asset_id),
    CONSTRAINT fk_lot_uses_lot FOREIGN KEY (lot_id) REFERENCES inventory_lots (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_serials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    serial_number VARCHAR(80) NOT NULL,
    manufacturer_serial VARCHAR(80) NULL,
    goods_receipt_id INT UNSIGNED NULL,
    stock_location_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'IN_STOCK',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_serials (serial_number),
    KEY idx_inventory_serials_product (product_id),
    CONSTRAINT fk_inventory_serials_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL,
    title VARCHAR(180) NOT NULL,
    expires_on DATE NULL,
    stored_name VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_supplier_documents_supplier (supplier_id, category),
    KEY idx_supplier_documents_expiry (expires_on),
    CONSTRAINT fk_supplier_documents_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_invoice_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_reference VARCHAR(80) NULL,
    invoiced_quantity DECIMAL(14,4) NULL,
    invoiced_value DECIMAL(14,2) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_supplier_invoice_checks_po (purchase_order_id),
    CONSTRAINT fk_supplier_invoice_checks_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE procurement_cash_purchases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_name VARCHAR(180) NOT NULL,
    description VARCHAR(180) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    job_id INT UNSIGNED NULL,
    reason VARCHAR(255) NOT NULL,
    purchased_on DATE NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cash_purchases_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE putaway_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_putaway_rules_product (product_id),
    CONSTRAINT fk_putaway_rules_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (code, name, module) VALUES
('procurement.rfq.view', 'View supplier RFQs', 'procurement'),
('procurement.rfq.create', 'Create supplier RFQs', 'procurement'),
('procurement.rfq.send', 'Send supplier RFQs', 'procurement'),
('procurement.rfq.award', 'Award a supplier RFQ', 'procurement'),
('procurement.quotes.view', 'View supplier quotations', 'procurement'),
('procurement.quotes.compare', 'Compare supplier quotations', 'procurement'),
('procurement.po.approve', 'Approve a high-value purchase order', 'procurement'),
('procurement.contract_prices.view', 'View supplier contract prices', 'procurement'),
('procurement.contract_prices.manage', 'Manage supplier contract prices', 'procurement'),
('procurement.supplier_portal.manage', 'Manage supplier portal access', 'procurement'),
('inventory.locations.manage', 'Manage warehouse locations', 'procurement'),
('inventory.putaway', 'Confirm stock put-away', 'procurement'),
('inventory.serial.manage', 'Manage serial numbers', 'procurement'),
('inventory.lot.manage', 'Manage lots and batches', 'procurement'),
('inventory.count.create', 'Create a stock count', 'procurement'),
('inventory.count.perform', 'Enter a physical stock count', 'procurement'),
('inventory.count.approve', 'Approve a stock count variance', 'procurement'),
('receiving.perform', 'Confirm goods receipts', 'procurement'),
('receiving.exceptions.manage', 'Manage receiving exceptions', 'procurement'),
('supplier_returns.manage', 'Manage supplier returns', 'procurement');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.module = 'procurement';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'procurement.rfq.view', 'procurement.quotes.view', 'procurement.contract_prices.view', 'receiving.perform'
);

INSERT INTO settings (setting_key, setting_value) VALUES
('rfq_prefix', 'SFRFQ'),
('supplier_quote_prefix', 'SFVQ'),
('supplier_return_prefix', 'SFRTN'),
('po_approval_amount', '50000'),
('rfq_late_response', 'FLAG');
