-- Sign-Forge Management System starter data.
-- Import this AFTER schema.sql.
--
-- Development sign-in (change it before any real use):
--   email:    admin@signforge.local
--   password: Forge#Admin2026
-- The account is forced to choose a new password at first sign-in.
-- Do not put a production password in this file.
--
-- Pricing-level percentages below are DATA, not application constants.
-- Demo product costs are illustrative. They are not Sign-Forge's buy prices.

SET NAMES utf8mb4;

INSERT INTO roles (code, name, description) VALUES
('ADMIN', 'Admin', 'Full access to every current module.'),
('SALES', 'Sales', 'Customers, activities, and the pricing calculator.'),
('DESIGN', 'Design', 'View customers and products, and use the calculator.'),
('PRODUCTION', 'Production', 'View the catalogue.'),
('ACCOUNTS', 'Accounts', 'View customers, products, suppliers, and pricing levels.'),
('INSTALLER', 'Installer', 'View customers.');

INSERT INTO permissions (code, name, module) VALUES
('dashboard.view', 'View dashboard', 'dashboard'),
('customers.view', 'View customers', 'crm'),
('customers.manage', 'Manage customers', 'crm'),
('activities.view', 'View activities', 'crm'),
('activities.manage', 'Record activities', 'crm'),
('products.view', 'View products', 'inventory'),
('products.manage', 'Manage products', 'inventory'),
('categories.manage', 'Manage categories', 'inventory'),
('suppliers.view', 'View suppliers', 'inventory'),
('suppliers.manage', 'Manage suppliers', 'inventory'),
('pricing.view', 'View pricing levels', 'pricing'),
('pricing.manage', 'Manage pricing levels', 'pricing'),
('calculator.use', 'Use pricing calculator', 'sales'),
('users.manage', 'Manage users', 'admin'),
('settings.manage', 'Manage settings', 'admin'),
('audit.view', 'View audit history', 'admin');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'ADMIN';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'SALES'
  AND p.code IN (
    'dashboard.view',
    'customers.view', 'customers.manage',
    'activities.view', 'activities.manage',
    'products.view', 'suppliers.view',
    'calculator.use'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'DESIGN'
  AND p.code IN (
    'dashboard.view',
    'customers.view', 'activities.view',
    'products.view', 'calculator.use'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'PRODUCTION'
  AND p.code IN ('dashboard.view', 'products.view', 'suppliers.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'ACCOUNTS'
  AND p.code IN (
    'dashboard.view',
    'customers.view', 'products.view', 'suppliers.view', 'pricing.view'
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'INSTALLER'
  AND p.code IN ('dashboard.view', 'customers.view');

INSERT INTO users (name, email, password_hash, role_id, active, must_change_password)
SELECT
    'Sign-Forge Admin',
    'admin@signforge.local',
    '$2y$10$PzpWv1xje7w4bt5JvKD9re4FB3yUIPVkvILy5xtAYctGXMf3zUFfy',
    id,
    1,
    1
FROM roles
WHERE code = 'ADMIN';

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Sign-Forge Signs'),
('trading_name', 'Sign-Forge Signs'),
('company_registration', ''),
('vat_number', ''),
('address', ''),
('telephone', ''),
('email', ''),
('website', ''),
('currency_code', 'ZAR'),
('currency_symbol', 'R'),
('default_vat_percent', '15'),
('quote_prefix', 'SFQ'),
('invoice_prefix', 'SFI'),
('job_prefix', 'SFJ'),
('default_quote_validity_days', '14'),
('timezone', 'Africa/Johannesburg');

-- Markup percentages are starter data. Change them under Pricing levels.
INSERT INTO pricing_levels (code, name, markup_percent, active, sort_order) VALUES
('Q1', 'Q1', 65.00, 1, 10),
('Q2', 'Q2', 50.00, 1, 20),
('Q3', 'Q3', 35.00, 1, 30),
('Q4', 'Q4', 25.00, 1, 40);

INSERT INTO product_categories (parent_id, name, description, active, sort_order) VALUES
(NULL, 'Vinyl', 'Roll films: printable, cut, and laminate.', 1, 10);

SET @vinyl := LAST_INSERT_ID();

INSERT INTO product_categories (parent_id, name, description, active, sort_order) VALUES
(@vinyl, 'Printable Vinyl', 'Roll media that is printed and then applied or laminated.', 1, 11),
(@vinyl, 'Cut Vinyl', 'Plotter-cut vinyl for lettering and simple shapes.', 1, 12),
(@vinyl, 'Laminate', 'Protective over-laminate film supplied on a roll.', 1, 13);

INSERT INTO product_categories (parent_id, name, description, active, sort_order) VALUES
(NULL, 'Boards', 'Rigid sheet materials.', 1, 20);

SET @boards := LAST_INSERT_ID();

INSERT INTO product_categories (parent_id, name, description, active, sort_order) VALUES
(@boards, 'Chromadek', 'Pre-painted steel sheet.', 1, 21),
(@boards, 'ACM', 'Aluminium composite panels.', 1, 22),
(@boards, 'Perspex', 'Acrylic sheet.', 1, 23),
(@boards, 'PVC', 'Foamed and solid PVC sheet.', 1, 24),
(NULL, 'Banners', 'Flexible banner materials.', 1, 30),
(NULL, 'Canvas', 'Canvas and similar fine-art media.', 1, 40),
(NULL, 'Steel', 'Steel tube, plate, and fabricated sections.', 1, 50),
(NULL, 'Aluminium', 'Aluminium extrusions, sheet, and angle.', 1, 60),
(NULL, 'LED', 'LED modules, strips, and light engines.', 1, 70),
(NULL, 'Power Supplies', 'Drivers and power supplies for illuminated signs.', 1, 80),
(NULL, 'Electrical', 'Cable, connectors, and electrical accessories.', 1, 90),
(NULL, 'Hardware', 'Fixings, stands, brackets, and fasteners.', 1, 100),
(NULL, 'Paint', 'Paint and coatings.', 1, 110),
(NULL, 'Printing', 'Print production charged apart from the media.', 1, 120),
(NULL, 'CNC', 'Routing and cutting-machine time.', 1, 130),
(NULL, 'Labour', 'Design and production time.', 1, 140),
(NULL, 'Installation', 'Site installation time.', 1, 150),
(NULL, 'Transport', 'Delivery and collection.', 1, 160),
(NULL, 'Other', 'Anything that does not sit in a family yet.', 1, 170);

INSERT INTO suppliers (name, contact_name, email, phone, website, account_number, address, notes, active) VALUES
('Demo Media Supplies', 'Alex Naidoo', 'sales@demo-media.example', '011 555 0101', 'https://demo-media.example', 'DM-100', '1 Demo Street, Johannesburg', 'Illustrative supplier. Not a real Sign-Forge account.', 1),
('Demo Board Merchant', 'Thandi Mokoena', 'orders@demo-boards.example', '011 555 0102', NULL, 'DB-200', '14 Panel Road, Germiston', 'Illustrative supplier. Not a real Sign-Forge account.', 1),
('Demo Electrical', 'Ravi Pillay', 'hello@demo-electrical.example', '011 555 0103', NULL, 'DE-300', '8 Circuit Close, Midrand', 'Illustrative supplier. Not a real Sign-Forge account.', 1),
('Demo Steel', 'Johan Botha', 'desk@demo-steel.example', '011 555 0104', NULL, 'DS-400', '3 Yard Lane, Alrode', 'Illustrative supplier. Not a real Sign-Forge account.', 1),
('Internal workshop', NULL, NULL, NULL, NULL, NULL, NULL, 'Use for labour that is not bought from a supplier.', 1);

-- Demo costs are round training numbers, not current buy prices.
INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-PV-1300', 'Printable vinyl 1300 mm',
    'White gloss printable vinyl on a 1300 mm roll. Demo cost, not a live buy price.',
    'MATERIAL', 'AREA', 85.0000, 'm2', 1300.00, NULL, NULL,
    10.00, 50.00, 'ACTUAL', 1, 1, 1, 20.0000, 'PV-G-1300',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Media Supplies'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Printable Vinyl';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-PV-1600', 'Printable vinyl 1600 mm',
    'White gloss printable vinyl on a 1600 mm roll. Demo cost, not a live buy price.',
    'MATERIAL', 'AREA', 92.0000, 'm2', 1600.00, NULL, NULL,
    10.00, 50.00, 'ACTUAL', 1, 1, 1, 20.0000, 'PV-G-1600',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Media Supplies'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Printable Vinyl';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-LAM-1300', 'Laminate 1300 mm',
    'Gloss over-laminate on a 1300 mm roll. Demo cost, not a live buy price.',
    'MATERIAL', 'AREA', 45.0000, 'm2', 1300.00, NULL, NULL,
    8.00, 50.00, 'ACTUAL', 1, 1, 1, 10.0000, 'LAM-1300',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Media Supplies'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Laminate';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-CHR-2450', 'Chromadek 2450 x 1225',
    'Demo sheet size 2450 x 1225 mm. Cost is per sheet, not a live buy price.',
    'MATERIAL', 'SHEET', 420.0000, 'sheet', NULL, 2450.00, 1225.00,
    0.00, NULL, 'ACTUAL', 1, 0, 1, 4.0000, 'CHR-2450',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Board Merchant'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Chromadek';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-ACM-2440', 'ACM 2440 x 1220',
    'Demo sheet size 2440 x 1220 mm. Cost is per sheet, not a live buy price.',
    'MATERIAL', 'SHEET', 510.0000, 'sheet', NULL, 2440.00, 1220.00,
    0.00, NULL, 'ACTUAL', 1, 0, 1, 4.0000, 'ACM-2440',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Board Merchant'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'ACM';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-PX-3MM', '3 mm clear Perspex',
    'Demo sheet size 2440 x 1220 mm. Cost is per sheet, not a live buy price.',
    'MATERIAL', 'SHEET', 680.0000, 'sheet', NULL, 2440.00, 1220.00,
    0.00, NULL, 'ACTUAL', 1, 0, 1, 2.0000, 'PX-3-CLR',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Board Merchant'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Perspex';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-LED-MOD', 'LED module',
    'Single LED module. Demo unit cost, not a live buy price.',
    'COMPONENT', 'UNIT', 18.5000, 'unit', NULL, NULL, NULL,
    0.00, NULL, 'ACTUAL', 0, 0, 1, 50.0000, 'LED-MOD',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Electrical'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'LED';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-PSU-12V', '12V power supply',
    '12V power supply. Demo unit cost, not a live buy price.',
    'COMPONENT', 'UNIT', 220.0000, 'unit', NULL, NULL, NULL,
    0.00, NULL, 'ACTUAL', 0, 0, 1, 5.0000, 'PSU-12V',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Electrical'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Power Supplies';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-ST-2525', '25 x 25 steel tube',
    '25 x 25 mm steel tube, priced per linear metre. Demo cost, not a live buy price.',
    'MATERIAL', 'LINEAR_METRE', 48.0000, 'm', NULL, NULL, NULL,
    5.00, NULL, 'ACTUAL', 0, 0, 1, 30.0000, 'ST-25',
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Demo Steel'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Steel';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-LAB-DES', 'Design labour',
    'Design time, priced per hour. Demo internal cost, not a charge-out rate.',
    'LABOUR', 'HOUR', 350.0000, 'hour', NULL, NULL, NULL,
    0.00, NULL, 'ACTUAL', 0, 0, 0, NULL, NULL,
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Internal workshop'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Labour';

INSERT INTO products (
    category_id, supplier_id, sku, name, description, product_type, pricing_method,
    cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
    standard_waste_percent, waste_threshold_percent, default_waste_policy,
    allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
    active, notes, created_by
)
SELECT c.id, s.id, 'SF-LAB-INS', 'Installation labour',
    'Installation time, priced per hour. Demo internal cost, not a charge-out rate.',
    'LABOUR', 'HOUR', 280.0000, 'hour', NULL, NULL, NULL,
    0.00, NULL, 'ACTUAL', 0, 0, 0, NULL, NULL,
    1, 'Illustrative catalogue row for the calculator.', u.id
FROM product_categories c
JOIN suppliers s ON s.name = 'Internal workshop'
JOIN users u ON u.email = 'admin@signforge.local'
WHERE c.name = 'Installation';
