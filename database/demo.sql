-- DEMO DATA ONLY.
-- Import this only into an empty copy of Sign-Forge after schema.sql and seed.sql.
-- Do not import it into a company database that already has customers.

INSERT INTO customers (
    customer_type, company_name, email, phone, physical_address, notes, active
) VALUES (
    'BUSINESS',
    'DEMO — Riverside Signs',
    'demo@example.invalid',
    '0100000000',
    '1 Demo Road, Johannesburg',
    'DEMO record. Delete this customer before go-live if it was imported by mistake.',
    1
);
