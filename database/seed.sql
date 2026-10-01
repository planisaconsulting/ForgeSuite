-- Sign-Forge starter data.
-- Import this AFTER schema.sql.
-- The administrator is forced to choose a new password at first sign-in.
--
-- Default sign-in (change it immediately):
--   email:    admin@signforge.local
--   password: Forge#Admin2026
--
-- Pricing-level percentages below are DATA, not application constants.
-- The pricing screen will edit them. PHP must read markup from this table.

SET NAMES utf8mb4;

INSERT INTO users (name, email, password_hash, role, active, must_change_password)
VALUES (
    'Sign-Forge Admin',
    'admin@signforge.local',
    '$2y$10$PzpWv1xje7w4bt5JvKD9re4FB3yUIPVkvILy5xtAYctGXMf3zUFfy',
    'admin',
    1,
    1
);

INSERT INTO categories (name, description, active, sort_order) VALUES
('Printable Vinyl', 'Roll media that is printed and then applied or laminated.', 1, 10),
('Cut Vinyl', 'Plotter-cut vinyl for lettering and simple shapes.', 1, 20),
('Laminate', 'Protective over-laminate film supplied on a roll.', 1, 30),
('Boards', 'Sheet boards such as Chromadek and foamboard.', 1, 40),
('ACM', 'Aluminium composite panels.', 1, 50),
('Perspex', 'Acrylic and polycarbonate sheet.', 1, 60),
('Steel', 'Steel tube, plate, and fabricated sections.', 1, 70),
('Aluminium', 'Aluminium extrusions, sheet, and angle.', 1, 80),
('LED', 'LED modules, strips, and related light engines.', 1, 90),
('Power Supplies', 'Drivers and power supplies for illuminated signs.', 1, 100),
('Hardware', 'Fixings, stands, brackets, and fasteners.', 1, 110),
('Paint', 'Paint and coatings sold by the litre.', 1, 120),
('Labour', 'Design, production, and installation time.', 1, 130),
('Printing', 'Print production charged apart from the media.', 1, 140),
('CNC', 'Routing and cutting-machine time.', 1, 150),
('Other', 'Anything that does not sit in a catalogue family yet.', 1, 160);

-- Starter markups. Edit them in Pricing Levels. Do not copy these numbers into PHP.
INSERT INTO pricing_levels (name, code, markup_percent, active, sort_order) VALUES
('Q1', 'Q1', 100.00, 1, 10),
('Q2', 'Q2', 70.00, 1, 20),
('Q3', 'Q3', 45.00, 1, 30),
('Q4', 'Q4', 25.00, 1, 40);

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Sign-Forge Signs'),
('company_registration', ''),
('vat_number', ''),
('address', ''),
('telephone', ''),
('email', ''),
('website', ''),
('quote_prefix', 'SFQ'),
('default_vat_percent', '15'),
('default_quote_validity_days', '14'),
('currency_code', 'ZAR'),
('currency_symbol', 'R');

-- Sample catalogue rows so the dashboard and, later, the calculator have
-- something to read. Deactivate them from the product screen if you do not
-- want them. They are not a finished price list.
-- Cost on the printable vinyl is R50.0000 per m2 so the 800 x 1000 mm
-- example in the specification is easy to check by hand.

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-PV-1300', 'Printable vinyl gloss',
    'White gloss printable vinyl on a 1300 mm roll. Priced per square metre.',
    'MediaCo', 'PV-G-1300',
    50.0000, 'AREA', 1300.00, NULL, NULL,
    50.00, 'ACTUAL', 10.00,
    1, 1, 1,
    'Spec check: 800 mm on a 1300 mm roll is 61.54% of the width. The spare 500 mm is a warning, not an automatic charge.'
FROM categories c WHERE c.name = 'Printable Vinyl';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-CV-1220', 'Cut vinyl',
    'Intermediate cut vinyl on a 1220 mm roll. Priced per square metre.',
    'MediaCo', 'CV-1220',
    28.0000, 'AREA', 1220.00, NULL, NULL,
    50.00, 'ACTUAL', 5.00,
    1, 1, 1,
    NULL
FROM categories c WHERE c.name = 'Cut Vinyl';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-LAM-1370', 'Cast laminate',
    'Gloss cast over-laminate on a 1370 mm roll. Priced per square metre.',
    'MediaCo', 'LAM-1370',
    36.0000, 'AREA', 1370.00, NULL, NULL,
    50.00, 'ACTUAL', 5.00,
    1, 1, 1,
    NULL
FROM categories c WHERE c.name = 'Laminate';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-BRD-CHR-2412', 'Chromadek white 0.5 mm',
    'White Chromadek sheet, 2400 x 1200 mm. Priced per sheet.',
    'Sheet Metals', 'CHR-05-2412',
    385.0000, 'SHEET', NULL, 2400.00, 1200.00,
    NULL, 'ACTUAL', 0.00,
    1, 1, 1,
    'Switch the pricing method to AREA if this board should be sold per square metre instead of per sheet.'
FROM categories c WHERE c.name = 'Boards';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-LED-MOD-12', 'LED module 12V',
    'Single 12V LED module. Priced per unit.',
    'SignLED', 'MOD-12-1',
    18.5000, 'UNIT', NULL, NULL, NULL,
    NULL, 'ACTUAL', 0.00,
    0, 0, 1,
    NULL
FROM categories c WHERE c.name = 'LED';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-STL-2525', 'Steel square tube 25 x 25',
    '25 x 25 x 1.6 mm mild-steel square tube. Priced per linear metre.',
    'Tube & Section', 'MS-SHS-2525',
    42.0000, 'LINEAR_METRE', NULL, NULL, NULL,
    NULL, 'ACTUAL', 5.00,
    0, 0, 1,
    NULL
FROM categories c WHERE c.name = 'Steel';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-LAB-INST', 'Installation labour',
    'On-site installation time. Priced per hour.',
    'In-house', NULL,
    180.0000, 'HOUR', NULL, NULL, NULL,
    NULL, 'ACTUAL', 0.00,
    0, 0, 1,
    NULL
FROM categories c WHERE c.name = 'Labour';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-PNT-ENAMEL', 'Enamel paint',
    'Sign enamel. Priced per litre.',
    'Coatings SA', 'EN-WHT-1L',
    65.0000, 'LITRE', NULL, NULL, NULL,
    NULL, 'ACTUAL', 0.00,
    0, 0, 1,
    NULL
FROM categories c WHERE c.name = 'Paint';

INSERT INTO products (
    category_id, sku, name, description, supplier, supplier_code,
    cost_price, pricing_method, roll_width_mm, sheet_width_mm, sheet_height_mm,
    waste_threshold_percent, default_waste_policy, standard_waste_percent,
    allow_rotation, allow_nesting, active, notes
)
SELECT
    c.id, 'SF-OTH-CALLOUT', 'Site call-out',
    'One visit to site to measure or inspect. The unit is one call-out.',
    'In-house', NULL,
    350.0000, 'CUSTOM', NULL, NULL, NULL,
    NULL, 'ACTUAL', 0.00,
    0, 0, 1,
    NULL
FROM categories c WHERE c.name = 'Other';
