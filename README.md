# Sign-Forge Pricing & Quotation System

Staff application for **Sign-Forge Signs**. It replaces the Excel pricing sheet and is intended to grow into a light quotation and production desk.

This release is **Phase 1, Step 1**: the project foundation. You can sign in, change the starting password, and use the responsive shell. Categories, products, pricing levels, and company settings are in the database. The calculator, customer screens, quotations, and printable quote are the next build steps. Their menu items open a short explanation instead of a half-built form.

Currency is South African Rand. VAT defaults to 15% and is stored in settings, not in code.

## What this release includes

- Folder layout for a shared-hosting PHP application
- MySQL schema and starter data
- PDO database connection with prepared statements
- Sign-in, sign-out, session regeneration, and CSRF protection
- Forced password change for the seeded administrator
- Dark industrial shell, sidebar on desktop, offcanvas menu on a phone
- Dashboard counts read from the database

## What is deliberately not built yet

- Category, product, pricing-level, and settings screens
- Material calculator and wastage decisions
- Customers, quotes, quote lines, and PDF output

The tables for those features are already in `database/schema.sql`, so the next steps add screens and services instead of redesigning the database.

## Requirements

- PHP 8.2 or newer
- Extensions: `pdo_mysql`, `mbstring`
- MySQL 8 or MariaDB 10.4 or newer
- Apache with `mod_rewrite` on the server (the local PHP server does not need Apache)
- `php-bcmath` is not required yet. The calculator step should use it so money is not calculated with floating point.

No Node.js, Composer, Docker, or framework is required on the server.

Bootstrap 5.3, Font Awesome Free, and the Barlow font files are already in `public/assets`. The browser does not need a CDN.

## Folder structure

```
app/                  PHP that must not be reachable as a URL
  bootstrap.php       config, autoload, session, error log
  routes.php          the list of URLs
  config/             config.example.php and your private config.local.php
  controllers/        one screen's request and response
  models/             prepared SQL
  services/           sign-in now; pricing and wastage services next
  domain/             allowed values such as AREA, Q-levels, quote statuses
  helpers/            database, router, views, CSRF, formatting
  views/              HTML only. No SQL.
public/               document root
  index.php           front controller
  router.php          used only by the PHP built-in server
  assets/             css, javascript, images, vendored libraries
database/
  schema.sql          tables. Drops Sign-Forge tables if you re-import it.
  seed.sql            administrator, categories, price levels, sample products
  install.php         command-line import
storage/
  logs/               application log. Must be writable.
  quotes/             future PDF files. Must be writable.
templates/            future printable quotation template
tests/foundation.php  checks the schema and seed
```

A request hits `public/index.php`, which loads `app/bootstrap.php` and `app/routes.php`. The router calls a controller. The controller asks a model or service for data and passes it to a view. The view does not run SQL. When a price is saved later, the server will recalculate it. The browser total will only be a preview.

## Architecture decisions

These are the problems in a straight "width × height × cost" spreadsheet, and how the schema avoids them.

1. **Printed area and consumed material are different.** An 800 × 1000 mm print on a 1300 mm roll is 0.80 m² of print and 1.30 m² of roll if the spare 500 mm is billed. Quote lines store `actual_area` and `billable_area` (and the same values again on the generic quantity columns). `waste_mode` records the decision: `ACTUAL`, `CONSUMED_WIDTH`, or `MANUAL`.

2. **Manufacturing waste is not unused roll width.** `standard_waste_percent` is the extra 5% or 10% added after the billable measure is known. `waste_threshold_percent` only warns the operator. It never forces a charge. The sample vinyl uses a 50% threshold and a default policy of `ACTUAL`.

3. **Selling price rule, used by every later service.**

   ```
   extended_cost   = billable_measure × unit_cost
   cost_with_waste = extended_cost × (1 + standard_waste_percent / 100)
   sell_ex_vat     = cost_with_waste × (1 + markup_percent / 100)
   ```

   Markup percentages live in `pricing_levels`. Q1 to Q4 can be renamed to Retail or Trade later. Do not copy the seed percentages into PHP.

4. **Old quotes must keep old prices.** Each quote line snapshots the product name, method, cost, manufacturing waste, and markup. The quote header snapshots the VAT rate, VAT mode, currency, and pricing-level name. Changing vinyl from R50 to R60 next month does not change a quote issued at R50.

5. **Line prices are stored excluding VAT.** `quotes.vat_mode` is `EXCLUSIVE` or `INCLUSIVE` for how the customer copy is presented. The stored line total stays net so VAT cannot be applied twice. Discount is a rand amount taken off the ex-VAT subtotal before VAT.

6. **Quote numbers.** `quote_sequences` has one row per year. The future numbering service must lock that row (`SELECT ... FOR UPDATE`) and add one. That gives `SFQ-2026-0001` without two staff receiving the same number. The prefix is the `quote_prefix` setting.

7. **Money columns are DECIMAL.** Totals are `DECIMAL(12,2)`. Unit rates are `DECIMAL(12,4)`. Do not use `FLOAT`. Display formatting (`R 1,234.56`) is separate from calculation.

8. **Products are deactivated, not deleted,** once they appear on a quote. Foreign keys use `RESTRICT` for that link. `product_price_history` is filled by the product-save code when the cost changes, not by a hidden database trigger.

9. **Roles are text** (`admin`, `sales`, `production`) checked in PHP, so a later role does not need an `ALTER TABLE`.

10. **Recipes and nesting are not built.** `products.product_type` can be `ASSEMBLY` later. `quote_items.parent_item_id` will hold the component lines. `quote_items.nest_group` will mark cuts that share one length of roll. A future jobs table should point at `quotes.id`. Status `CONVERTED` is the hand-off. Phase 1 does not nest and does not create jobs.

The later services should stay separate so the rules above do not get mixed into a view:

| Service | Responsibility |
| --- | --- |
| Material consumption | Actual measure versus billable measure for area, linear, unit, sheet, litre, and hour |
| Wastage | Roll-width warning and the three waste modes |
| Pricing | Manufacturing waste and the selected pricing level |
| Quote totals | Line totals, discount, VAT mode, header total |
| Quote numbers | `SFQ-2026-0001` from `quote_sequences` |

## Installation

### 1. Put the files on the server

The domain document root should be the `public` folder when the hosting panel allows it.

If the panel can only point at the project folder, the root `.htaccess` blocks `app/`, `database/`, `storage/`, `templates/`, and `tests/`, and sends pages through `index.php`. Prefer a document root of `public/` anyway.

### 2. Configure the database

```bash
cp app/config/config.example.php app/config/config.local.php
```

Edit `app/config/config.local.php`:

```php
'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'your_database_name',
    'user' => 'your_database_user',
    'pass' => 'your_database_password',
    'charset' => 'utf8mb4',
],
```

Set `'debug' => false` on a live site.

If the site is not at the domain root, set `app.base_path` to the subdirectory, for example `/signforge`. Leave it empty when `public/` is the document root.

The same values can be supplied as environment variables, which override the file:

`SF_DB_HOST`, `SF_DB_PORT`, `SF_DB_NAME`, `SF_DB_USER`, `SF_DB_PASS`, `SF_APP_DEBUG`, `SF_APP_URL`, `SF_APP_BASE_PATH`, `SF_APP_ENV`

`config.local.php` is listed in `.gitignore`. Do not commit it.

### 3. Create and import the database

Create an empty database with charset `utf8mb4` and collation `utf8mb4_unicode_ci`. Then either use the installer:

```bash
php database/install.php
```

or import the files yourself, in this order:

1. `database/schema.sql`
2. `database/seed.sql`

`schema.sql` **drops** the Sign-Forge tables before creating them. Import it only into an empty database, or when you mean to wipe the desk. To reload a database that already has tables:

```bash
php database/install.php --force
```

Check the import:

```bash
php tests/foundation.php
```

Run that before anyone changes the seeded password. After the password is changed, the password line in that test will fail. That is expected.

### 4. Folder permissions

The web server user needs to write:

- `storage/logs`
- `storage/quotes`

On shared hosting, `755` is often enough. Use `775` if the panel runs PHP as a different user from the FTP account. Do not make `app/` or `database/` writable by the public, and do not put those folders inside a public document root if you can avoid it.

## Local development

From the project folder, with MySQL running and `config.local.php` in place:

```bash
php database/install.php
php tests/foundation.php
php -S 127.0.0.1:8741 -t public public/router.php
```

Open `http://127.0.0.1:8741`.

The built-in server uses `public/router.php`. Apache uses `public/.htaccess` and ignores that router file.

## Xneelo deployment

1. In the control panel, set the PHP version to 8.2 or 8.3.
2. Create a MySQL database and user. Note the host, which is often not `127.0.0.1` on shared hosting. Put that host in `config.local.php`.
3. Upload the project. Set the site public folder to `public` if the panel allows a custom document root.
4. Import `database/schema.sql`, then `database/seed.sql`, using phpMyAdmin. Do not import `schema.sql` again later unless you intend to erase the tables.
5. Copy `config.example.php` to `config.local.php` on the server and fill in the database details. Turn debug off.
6. Confirm `storage/logs` and `storage/quotes` are writable.
7. Open the site over HTTPS, sign in, and change the administrator password.
8. Confirm `https://your-domain/app/config/config.example.php` is **not** downloadable. If it is, the document root is wrong or `.htaccess` is being ignored.

## Default administrator

| | |
| --- | --- |
| Email | `admin@signforge.local` |
| Password | `Forge#Admin2026` |

The first sign-in asks for a new password of at least 10 characters. The seeded password cannot open the dashboard. Change it before anyone else uses the site. There is no second staff account yet.

Five wrong passwords in one session pause sign-in for a minute. The message is the same for an unknown email, a wrong password, and an inactive account.

## Starter data

- 16 categories (printable vinyl through to Other)
- Pricing levels Q1 100%, Q2 70%, Q3 45%, Q4 25% (editable later; not hard-coded)
- Company settings: Sign-Forge Signs, prefix `SFQ`, VAT 15%, validity 14 days, currency ZAR / `R`
- Nine sample products, including printable vinyl `SF-PV-1300` at R50.0000 per m² on a 1300 mm roll, with 10% manufacturing waste and a 50% roll-width warning

No customers and no quotes are seeded. The dashboard shows zeros for those.

## What to test

1. Sign in with the seeded account. You should land on the password screen, not the dashboard.
2. A wrong password shows one message and does not say whether the email exists.
3. After a new password, the dashboard shows Sign-Forge Signs, VAT 15%, the `SFQ-year-0001` pattern, 9 products, 0 customers, and `R 0.00`.
4. Open every menu item. Catalogue and quote items explain the next build. They must not 404.
5. On a phone-width window, open the menu, go to a page, and close it.
6. Sign out, then open `/`. You should be sent to sign-in.
7. Submit a form with a stale session. You should see the blocked-request page rather than a saved change.

## Recommended next build

**Step 2: catalogue maintenance.**

- Categories: add, edit, deactivate, sort
- Products: add, edit, search, filter by category and supplier, deactivate instead of delete
- Product form that shows roll fields for area media and hides them for unit, litre, and hour items
- Write a `product_price_history` row whenever `cost_price` changes
- Pricing levels: edit names and markup percentages
- Settings: edit the company profile, VAT rate, quote prefix, validity, and currency

After that, Step 3 is the calculation service and its checked examples (area, linear metre, unit, sheet, and the 800 × 1000 mm roll-width case). The calculator screen comes after those numbers are proven on the server.
