# Sign-Forge Management System

Staff application for **Sign-Forge Signs**. Phase 1 is the foundation: sign-in, company settings, customers, the catalogue, and the pricing engine. Phase 2 adds opportunities and quotations. Phase 3 runs the job after an accepted quote: artwork, production, materials actually used, labour, installation, and job costing. Stock movements and invoices are not built yet.

The application name in the interface is Sign-Forge Management System. The company name (Sign-Forge Signs) comes from settings and can be changed without editing code.

## Requirements

- PHP 8.2 or newer, with PDO MySQL and `bcmath` if the host has it
- MySQL 8 or MariaDB 10.4+
- Apache with `mod_rewrite` (or another host that sends requests to `public/index.php`)
- A database you create in the hosting panel

`bcmath` is preferred. If it is missing, the same decimal maths runs through string arithmetic. Do not turn on PHP `display_errors` in production. Currency is never stored as FLOAT.

Composer is not required for Phase 1.

## Folder structure

```
app/config          example config, and config.local.php which is not committed
app/controllers     thin HTTP handlers
app/domain          fixed lists: pricing methods, waste modes, product types, roles
app/helpers         database, router, CSRF, decimal maths
app/middleware      sign-in and permission checks the router calls
app/repositories    SQL only
app/services        business rules, including the pricing engine
app/views           HTML. No pricing formulas in the templates
public              the only folder the web server should expose
database/schema.sql full current schema (it drops Sign-Forge tables)
database/seed.sql   roles, admin, categories, demo products, pricing levels
database/migrations numbered SQL for later changes
storage/logs        PHP error log
storage/uploads     reserved for later files
tests               calculation tests and HTTP checks
```

Repositories read and write rows. Services decide what a save means (validation, cost history, audit). Controllers pass the form to a service and pick the next page. Views only print what they are given.

There is no second copy of the pricing formulas in JavaScript. The calculator sends sizes to the server and displays the JSON that comes back.

## How pricing works

Dimensions are millimetres.

- Area in square metres = `(width × height / 1,000,000) × quantity`
- Linear metres = `(length / 1,000) × quantity`
- Units, litres, hours, and custom quantities are the number you type, times the product cost

Two kinds of waste are kept separate.

**Manufacturing waste** is `standard_waste_percent` on the product. It is applied after the billable quantity is chosen.

```
costed quantity = billable × (1 + standard waste percent / 100)
total cost      = costed quantity × unit cost
```

**Roll or sheet offcut** is a choice the operator makes. The waste threshold only warns. It never charges the customer by itself.

| Mode | What is billed |
| --- | --- |
| ACTUAL | The print area, or the fraction of a sheet that the piece occupies |
| CONSUMED_WIDTH | Roll width × print length × quantity |
| FULL_SHEET | Every sheet the pieces occupy on a simple grid |
| MANUAL | A width, area, or sheet count the operator typed. The screen says manual pricing is in use |

A sheet product's cost is per sheet. Charging actual area bills `(piece area / sheet area) × sheet cost`. Charging the full sheet bills the whole sheet cost times the number of sheets. Rotation, when the product allows it, only picks the orientation that fits more pieces on that grid. It is not a nesting optimiser. `allow_nesting` is stored so a later layout pass can be added in `MaterialConsumptionService` without moving the maths into a controller.

**Markup is not margin.**

```
selling price = total cost × (1 + markup percent / 100)
gross profit  = selling price − total cost
gross margin  = gross profit / selling price × 100
```

A cost of R100 with 50% markup sells at R150. The margin on R150 is 33.33%, not 50%. Markup percentages live in `pricing_levels`. The calculator reads them on every request. Changing Q1 changes the next calculation. Nothing in PHP or JavaScript hard-codes those percentages.

Cost and selling price stay separate so a later job can compare quoted sell, quoted cost, and actual cost.

The browser can show a live preview, but the preview is the server's answer. A posted cost or markup is ignored. `PricingService` loads the product and the pricing levels from the database and calculates again.

## What is deliberately not in the database yet

Quotes, jobs, stock movements, recipes, purchase orders, invoices, and payments. Product rows have `track_stock` and `minimum_stock_level` as flags only. There is no quantity-on-hand column. Future stock should be a ledger of movements (purchase, job usage, waste, adjustment), including individual vinyl rolls later.

A recipe such as a printed Chromadek sign will point at existing products (board, vinyl, laminate, labour). Product types are already material, component, service, labour, and consumable so that recipe does not need a new kind of catalogue row.

`products.supplier_id` is the preferred supplier. A later `product_suppliers` table can add more without replacing that column.

## Database installation

Create an empty database, then copy the config and import.

```bash
cp app/config/config.example.php app/config/config.local.php
```

Edit `config.local.php`: database host, name, user, password, and `app.url`. Leave `app.base_path` empty when the site is the domain root.

```bash
php database/install.php
```

That creates the database when the user is allowed to, then imports `schema.sql` and `seed.sql`. If the tables already exist:

```bash
php database/install.php --force
```

`--force` drops the Sign-Forge tables. Do not run it on a database that already has customers you need.

`database/migrations/001_initial_schema.sql` is the same schema. Later changes should be new files (`002_quotes.sql`, `003_jobs.sql`, `004_stock.sql`) and should also be folded into `schema.sql` so a fresh install stays current. Apply a new migration by importing that file once. Do not re-import `001` on a live database.

## Local setup

From the project folder, with PHP on your PATH:

```bash
php -S 127.0.0.1:8741 -t public public/router.php
```

Open [http://127.0.0.1:8741](http://127.0.0.1:8741).

The built-in server is only for development. Apache should use `public` as the document root. If the panel cannot do that, the root `.htaccess` rewrites into `public/` and refuses `app`, `database`, `storage`, `tests`, and `deploy`.

## Development login

These are starter credentials for a local database. Change them before any real use. The account is sent to the password screen on first sign-in.

- Email: `admin@signforge.local`
- Password: `Forge#Admin2026`

There is no public registration page. An administrator adds staff under Users. A password an administrator sets must be changed at the next sign-in.

Demo product costs are training numbers, not Sign-Forge's buy prices. The product notes say so. Pricing levels start at Q1 65%, Q2 50%, Q3 35%, Q4 25%. Edit them under Pricing levels. Company defaults are Sign-Forge Signs, ZAR, symbol R, VAT 15%, timezone Africa/Johannesburg, prefixes SFO / SFQ / SFI / SFJ, quote validity 14 days. Default quotation terms are copied onto each new quote.

## Roles

ADMIN, SALES, DESIGN, PRODUCTION, ACCOUNTS, and INSTALLER. ADMIN can open every current screen even if a permission row is missing. Other roles only get the codes in `role_permissions`. SALES can build quotations, apply a discount, override a selling price, accept a quote, and convert it to a job. Discounting below cost stays with ADMIN. The router checks the code before the controller runs. Production, stock, and invoices stay disabled in the menu until those phases exist.

## Xneelo deployment

1. In the Xneelo panel, create a MySQL database and user. Note the host (often `sqlXX.jnb1.host-h.net`, not `localhost`).
2. Point the domain's document root at `public_html/public` if the panel allows it. If it does not, upload the project into `public_html` and leave the root `.htaccess` in place.
3. Copy `config.example.php` to `config.local.php` on the server only. Set `app.debug` to `false`, `app.url` to the https address, and the database details. Do not commit that file. Permissions `600` are enough.
4. On an empty database, import `database/schema.sql` then `database/seed.sql`. `schema.sql` drops existing Sign-Forge tables. Do not import it over a database that already has customers. A database that already has Phase 1 tables should receive `database/migrations/002_sales_and_quotes.sql` once, and nothing else.
5. Upload the project by SFTP. Passive FTP from some networks cannot open a data port. `deploy/upload.py` uses SFTP on port 22 and skips `config.local.php` unless you pass `SF_CONFIG_LOCAL`.
6. Confirm `https://your-domain/login` loads, sign in, and change the admin password.
7. Delete any one-off import script. Confirm `README.md`, `database/`, and `app/config/config.local.php` are not downloadable (the `.htaccess` rules return 403).

`storage/logs` must be writable by PHP. The application logs errors there and shows a plain message to the user.

## Tests

```bash
php tests/calculations.php
php tests/quotes.php
php tests/operations.php
php tests/sales_flow.php
php tests/jobs_flow.php
php tests/acceptance.php http://127.0.0.1:8741
```

`calculations.php` does not need the database. It checks area, linear, unit, manufacturing waste, roll consumption, the threshold warning, actual / consumed-width / manual modes, sheet actual and full-sheet charging, markup, gross profit, and gross margin. It runs the checks with bcmath and again with the string fallback.

`quotes.php` checks quote-line snapshots and quote totals: discounts, VAT modes, deposits, optional lines, and a saved cost that survives a later catalogue change. It does not need the database.

`operations.php` checks job gross profit, gross margin, cost variance, material variance, and labour-time variance without a database. It also checks that a completed job stays locked and that collection work does not require installation.

`sales_flow.php` needs the database. It creates a quotation, keeps the saved cost after the product price changes, refreshes prices onto a new revision, accepts the quote, converts it to one job, and checks the customer PDF.

`acceptance.php` needs the dev server. It signs in, changes the password, walks customers, products, suppliers, the calculator, and the quotation list, then puts the seed password back.

## Security

- Passwords are hashed with `password_hash`. The audit log never stores them.
- Sessions use `httponly` and `SameSite=Lax`, and `secure` on HTTPS. Sign-in calls `session_regenerate_id`. Remember me keeps the cookie for 30 days.
- Forms and the calculator POST send a CSRF token. Failed sign-ins are throttled in the session.
- SQL uses PDO prepared statements. Do not reuse the same named placeholder twice in one statement.
- Output is escaped with `e()`.
- Records that later documents will point at are deactivated (`active = 0`), not deleted.
- Foreign keys use RESTRICT where a delete would destroy history. `created_by` uses SET NULL.
- `config.local.php` is gitignored. Production passwords do not belong in this repository.

## Phase 2 sales workflow

A lead can be an opportunity, and a quotation can also be raised directly from a customer, the calculator, or New quotation.

Opportunity → quotation → revision → acceptance → job hand-off.

Numbers come from a locked counter in `number_sequences`, not from `COUNT(*) + 1`.

- Opportunities: `SFO-2026-0001` (`opportunity_prefix`)
- Quotations: `SFQ-2026-0001` (`quote_prefix`). A revision keeps that number and increments `revision_number`.
- Jobs: `SFJ-2026-0001` (`job_prefix`)

Each quote line calls `PricingService` and stores the cost, waste choice, markup, calculated price, and final sell price. Opening the quote again uses those saved figures. Refresh current prices shows the difference and, after confirmation, writes the live catalogue cost onto a new revision.

VAT mode is exclusive, inclusive, or none. The rate is copied from settings when the quote is created. A later change to the company VAT rate does not rewrite old quotations. Posted VAT rates are ignored.

Customer PDFs are built with Dompdf from the saved quote. Costs, markup, gross profit, internal notes, waste, and supplier details stay off that document. Files are named `SFQ-2026-0001-R1.pdf`.

An accepted quote is locked. Convert to job creates one `jobs` row in status NEW and, when the quote belongs to an opportunity, marks that opportunity won. The conversion also copies included quote lines into job items and stores the quoted revenue and quoted cost. Optional lines that were not included stay off the job.

Deposit fields record what the customer must pay before work starts. They are not receipts, invoices, or ledger entries.

## Upgrading a Phase 1 database

Back up the database first. From the host panel, export the MySQL database to a `.sql` file and keep that file off the server. Confirm the export opens and contains the `users` and `customers` tables before you change anything.

Then import `database/migrations/002_sales_and_quotes.sql` once. It adds opportunities, quotations, revisions, jobs, attachments, and the new permissions. It does not drop Phase 1 rows. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `002` on a database created from the current `schema.sql`.

## Phase 3 operations

Accepted quote → job → artwork → customer approval → production route → material usage → labour → quality control → installation or collection → completion → actual cost.

Job numbers stay on the Phase 2 counter: `SFJ-2026-0001`.

The job page is tabbed: overview, artwork, tasks, production, materials, time and labour, installation, files, costing, and activity. Costing is hidden from roles without `costing.view`. The printable job card and its PDF leave markup, margin, and selling prices off the page.

Quoted revenue is the accepted quotation's ex-VAT amount. It is not money received. Gross profit is quoted revenue minus actual cost. Gross margin is that profit divided by quoted revenue. Markup is not used on the costing tab.

Actual cost is the sum of material usage, labour time, and other costs. Each material row stores the product cost at the moment it was recorded. Each time row stores the internal hourly cost. Later catalogue or wage changes do not rewrite those rows. Recording usage does not move stock. `MaterialUsageService` accepts a `StockConsumptionHook` so Phase 4 can attach stock movements without a second stock table.

A production route template is copied onto the job. Editing the job's stages does not change the template. Tasks are not created automatically at conversion.

Artwork revisions are kept. A new upload of the same title marks the older file superseded. Customer approval is a staff record of an email, phone call, or signed copy. It is not an electronic signature. Moving into production while required artwork is unapproved stops with "Artwork has not been approved by the customer." unless someone with `artwork.override_approval` records a reason.

Collection, delivery, and courier jobs can be completed without an installation visit. Installation jobs cannot, unless someone with `jobs.complete` records an override reason.

## Upgrading a Phase 2 database

Back up the database first. Export it from the host panel and keep that file off the server. Confirm the export contains `users`, `quotes`, and `jobs` before you change anything.

Then import `database/migrations/003_jobs_operations.sql` once. It widens job status, adds the operational tables, and inserts permissions, teams, stages, route templates, and checklists. It does not drop Phase 1 or Phase 2 rows. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `003` on a database created from the current `schema.sql`.

## Phase 4, when you ask for it

Stock locations, stock movements, roll and sheet tracking, offcuts, purchase orders, goods receiving, supplier orders, reservations, allocations, low-stock alerts, and stock valuation. Connect those movements through `StockConsumptionHook` on material usage. Invoicing, deposits as receipts, payments, credit notes, and statements stay after that. Do not start them until Phase 3 is accepted.
