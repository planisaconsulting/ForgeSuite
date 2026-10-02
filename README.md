# Sign-Forge Management System

Staff application for **Sign-Forge Signs**. Phases 1 to 6 cover customers, quotations, jobs, stock, invoices, and management reports. Phase 7 adds site surveys, signage recipes, and a customer portal at `/portal`.

The application name in the interface is Sign-Forge Management System. The release name is Sign-Forge ERP v1.1.0. The company name (Sign-Forge Signs) comes from settings and can be changed without editing code.

Guides for install, deploy, backup, restore, security, and go-live are in `docs/`. v1.0.0 closed the original phase roadmap. v1.1 Phase 1 adds projects and multi-site rollouts. A job still does not have to belong to a project. See `docs/PROJECTS_AND_ROLLOUTS.md`. v1.1 Phase 2 adds the customer asset, warranty, service request, inspection, and maintenance records. See `docs/ASSET_MANAGEMENT.md`. v1.1 Phase 3 adds sign specifications and the vehicle wrap, channel letter, lightbox, pylon, and panel estimators. See `docs/ADVANCED_ESTIMATING.md`. Apply `database/migrations/018_v1_1_signage_engineering.sql` once on a database that already has Phase 2. v1.1 Phase 4 adds release to production and line-item fulfilment. An accepted job is not released. See `docs/PRODUCTION_RELEASE.md`. Apply `database/migrations/019_v1_1_production_control.sql` once on a database that already has Phase 3. v1.1 Phase 5 adds supplier RFQs, a supplier portal, contract prices, receiving exceptions, and warehouse bins. A purchase order is not stock on hand. See `docs/PROCUREMENT.md`. Apply `database/migrations/020_v1_1_supplier_procurement.sql` once on a database that already has Phase 4. v1.1 Phase 6 adds customer catalogues, reorders, and portal orders on the existing customer portal. A portal order is not a production release. See `docs/CUSTOMER_HUB.md`. Apply `database/migrations/021_v1_1_customer_commerce.sql` once on a database that already has Phase 5. v1.1 Phase 7 adds artwork revisions, visual proofs, and production files. A customer proof is not a print file. See `docs/ARTWORK_MANAGEMENT.md`. Apply `database/migrations/022_v1_1_artwork_proofing.sql` once on a database that already has Phase 6. Do not import `schema.sql` over that database.

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

Quotations, jobs, stock movements, purchase orders, invoices, payments, credit notes, and statements are stored. A signage recipe is a separate bill of materials. It points at material, labour, and hardware products. A finished product is the sign the customer buys. It is not the same row as the board or vinyl it is made from.

There is no `products.stock_quantity` column. On hand is the sum of signed `stock_movements` rows. A product is tracked only when `inventory_method` is not `NONE`.

Product types are material, component, service, labour, consumable, and finished product. Stock and buy prices stay on the material rows. The recipe calculates how much of each one a configured sign needs.

`products.supplier_id` is the preferred supplier. `supplier_products` holds extra supplier SKUs and buy prices without replacing that column.

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
php tests/inventory_math.php
php tests/inventory_flow.php
php tests/finance_math.php
php tests/finance_flow.php
php tests/reporting.php
php tests/reporting_flow.php
php tests/phase7.php
php tests/phase8.php
php tests/acceptance.php http://127.0.0.1:8741
php tests/phase13.php
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

Actual cost is the sum of material usage, labour time, and other costs. Each material row stores the product cost at the moment it was recorded. Each time row stores the internal hourly cost. Later catalogue or wage changes do not rewrite those rows. When the product's inventory method is not `NONE`, recording usage also writes a stock movement in the same transaction. Untracked products leave the ledger alone.

A production route template is copied onto the job. Editing the job's stages does not change the template. Tasks are not created automatically at conversion.

Artwork revisions are kept. A new upload of the same title marks the older file superseded. Customer approval is a staff record of an email, phone call, or signed copy. It is not an electronic signature. Moving into production while required artwork is unapproved stops with "Artwork has not been approved by the customer." unless someone with `artwork.override_approval` records a reason.

Collection, delivery, and courier jobs can be completed without an installation visit. Installation jobs cannot, unless someone with `jobs.complete` records an override reason.

## Upgrading a Phase 2 database

Back up the database first. Export it from the host panel and keep that file off the server. Confirm the export contains `users`, `quotes`, and `jobs` before you change anything.

Then import `database/migrations/003_jobs_operations.sql` once. It widens job status, adds the operational tables, and inserts permissions, teams, stages, route templates, and checklists. It does not drop Phase 1 or Phase 2 rows. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `003` on a database created from the current `schema.sql`.

## Phase 4 inventory and purchasing

On hand for a product at a location is `SUM(stock_movements.quantity)`. Increases are positive. Decreases are negative. Offcut movements are kept out of the full-stock on-hand figure so a leftover piece is not counted as another full sheet. Available stock is on hand minus reservations that are still `RESERVED`. Consuming a reservation reduces on hand and clears the reservation, so the quantity is not subtracted twice.

Movements are insert-only. A correction is a new movement.

Inventory methods are `NONE`, `QUANTITY`, `ROLL`, `SHEET`, `LENGTH`, `AREA`, and `UNIT`. Rolls, sheets, and offcuts are `inventory_items` with codes such as `ROL-2026-0001`. Bulk quantity stock can be movements only. Roll consumption stores the physical length taken from the roll. The quotation's billable area stays on the quote and is not rewritten from that length.

Offcuts are entered by hand after a cut. There is no automatic nesting. Search can allow rotation. Valuation of a new offcut follows settings: `FULL_COST`, `REDUCED_COST` (default, 50% of acquisition cost), or `ZERO_COST`. The acquisition cost stays on the item.

Purchase orders use `NumberingService` (`SFPO-2026-0001`). Goods receipts use `SFGRN-2026-0001`. A receipt can be partial. Receiving rolls creates one inventory item per roll. Posted unit costs and PO totals are ignored. The server uses the supplier price, or the product cost when no supplier price is linked.

Costing methods are `LAST_COST` and `WEIGHTED_AVERAGE_COST`. Weighted average is `(existing quantity × existing unit cost + receipt quantity × receipt cost) / total quantity`, and it is applied to quantity, unit, and area products. Rolls, sheets, and lengths keep the item's acquisition cost. `LAST_COST` updates `products.cost_price` on receipt so the next new quote sees it. Saved quote lines and posted job usage keep their snapshots.

Waste rate is waste divided by production plus waste. It is left blank when both are zero.

Negative stock is refused unless the user has `inventory.override` and records a reason. That writes `NEGATIVE_STOCK_OVERRIDE`.

## Upgrading a Phase 3 database

Back up the database first. Export it from the host panel and keep that file off the server. Confirm the export contains `users`, `quotes`, and `jobs` before you change anything.

Then import `database/migrations/004_inventory_purchasing.sql` once. It adds the ledger, purchasing tables, permissions, settings, and the five starter locations. It does not drop earlier rows. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `004` on a database created from the current `schema.sql`.

## Phase 5 invoices, payments, and debtors

Issued invoices, credit notes, and payments are historical documents. A later change to the customer, the company address, the VAT rate, or a product price does not rewrite them. The correction is a credit note or a new invoice.

Draft invoices have no official number. Issuing assigns `SFI-2026-0001` through `NumberingService`. Payments use `SFPAY-2026-0001`. Credit notes use `SFCN-2026-0001`.

VAT follows the quotation rules. Exclusive adds tax on the discounted subtotal. Inclusive treats that subtotal as the amount the customer pays and lifts the VAT out of it. No VAT leaves the subtotal as the total. The VAT amount is calculated once for the document. Line VAT shares are portions of that amount, and the last line takes the remainder so the lines add back to the header.

Commercial value for a job is the accepted quotation total plus approved variations. Invoiced value is issued invoices minus credit notes. Payments received are allocations. Actual cost is material, labour, and other job costs. Operational gross profit is commercial value minus actual cost. Invoiced gross profit is invoiced net minus actual cost. Those labels are kept separate.

A deposit invoice uses the deposit stored on the quotation and will not be created twice. A progress invoice is a percent or a fixed amount. A final invoice is the commercial value still uninvoiced. Issuing more than that needs a reason. Account hold and a credit limit also need a reason before a new invoice is issued. Existing jobs are not cancelled.

Payments are not deleted. A reversal keeps the row, reverses the allocations, and restores the invoice balance. Money that is not allocated stays as unallocated customer credit.

Customer outstanding is the sum of invoice balances. It is not an editable balance field. Ageing uses the due date: current, 1–30, 31–60, 61–90, and 90+ days. The operational VAT summary uses the invoice date and the credit-note date. It is not a VAT201 return.

Customer PDFs omit internal notes, cost, markup, and hold reasons.

## Upgrading a Phase 4 database

Back up the database first. Export it from the host panel and keep that file off the server. Confirm the export contains `users`, `quotes`, `jobs`, and `stock_movements` before you change anything.

Then import `database/migrations/005_finance.sql` once. It adds invoices, payments, credit notes, variations, payment terms, permissions, and bank settings. It does not drop earlier rows and it does not change password hashes. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `005` on a database created from the current `schema.sql`.

## Phase 6 reports, alerts, and administration

Reports read quotations, jobs, invoices, stock, and purchases. They do not keep a second copy of those transactions.

Count conversion is accepted decided quotes divided by accepted plus declined. Draft, ready, and sent quotes are not treated as lost. Value conversion uses the same statuses with quote totals. Gross profit is commercial value minus actual job cost. Gross margin is that profit divided by commercial value. Cash collected is payments received in the period and is not labelled revenue. Waste rate is waste quantity divided by production quantity plus waste quantity. Waste cost uses the cost stored when the waste was recorded. Debtor ageing uses the invoice due date. Average days to payment is the paid date minus the invoice date.

KPI targets live in `kpi_targets` and can be changed under Administration. A difference from target is shown without a pass or fail label.

Notifications, reminders, and automation rules are internal. Marking a quote sent creates one follow-up reminder for the assigned salesperson. Running the check again does not create another. Email is not sent. The communication log on a customer records a phone, email, or WhatsApp note. It does not connect to WhatsApp.

Scheduled work is `php cron/run.php` from the command line. On Xneelo, schedule that command hourly. The `cron` folder denies web access, and the script exits unless it is run from PHP CLI. It creates alerts, delivers scheduled report notices into the notification centre, and deletes temporary files older than 14 days. It does not delete quotes, jobs, invoices, payments, stock movements, or audit history.

Backups are created from Administration → Backups and stored in `storage/backups`, which the web server does not serve. Download is limited to a user with `system.backup`. Restore is a manual import of that SQL file after you have a newer successful backup. There is no restore button.

The installable app caches only the CSS and JavaScript shell. Reports, invoices, customers, the portal, site surveys, and sign-in responses are not cached.

Application version is `App\Version::NUMBER`.

## Upgrading a Phase 5 database

Back up the database first. Then import `database/migrations/006_reporting_automation.sql` once. It adds reporting support tables, the Management role, and report permissions. It does not copy invoices or jobs into another ledger. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `006` on a database created from the current `schema.sql`.

## Phase 7 surveys, recipes, and the portal

A site survey can start from a customer, an opportunity, a quotation, a job, or on its own. The number is `SFS-2026-0001` (`survey_prefix`). Measurements and photos stay on the survey. A later quotation can be linked without replacing those records. The survey form is built for a phone: large fields, a camera file input, and short sections. The internal PDF lists the site, the measurements, the notes, and the photo captions. It does not embed the image files.

A recipe describes how a finished sign is made. Quantities come from a restricted formula. The formula may use `W`, `H`, `L`, `D`, `Q`, `AREA_M2`, and `PERIMETER_M`, plus the recipe's own numeric inputs. `AREA_M2` is the face area of one sign. Multiply by `Q` when the component is for the whole line. Allowed functions are `CEIL`, `FLOOR`, `ROUND`, `MAX`, `MIN`, and `ABS`. The parser does not call `eval()` and it rejects PHP, SQL, and shell text. Millimetres become metres, and minutes become hours, in `UnitConversionService`.

A printed ACM sign of 2400 mm by 1200 mm has a face of 2.88 m². Two of them need 5.76 m² of board before waste. Waste still comes from `WasteCalculationService`. The selling price of the assembled cost goes through `PricingService`, so markup is not calculated a second way. Roll yield still uses `MaterialConsumptionService`. A sheet count is a simple rectangular fit, and the user can override it. It is not a nesting layout. Vehicle graphics use a measured area. The recipe does not guess a vehicle's surface.

Saving a recipe writes a version. A quotation stores that version, the inputs, the component quantities, the cost, and the production route. Editing the recipe later does not change the quotation. Converting the accepted quotation builds the job item, the material requirements, the expected labour, and the production stages from that snapshot. The current recipe is not read again. Pack size can raise the purchase quantity above the job quantity. Suggested stock, offcuts, and rolls are shown on the job. Nothing is reserved or consumed until someone does that from the existing stock actions.

The customer portal is `/portal`. It uses portal users, not staff accounts. Administration → Portal access uses `portal.access_manage`. Searching portal contacts uses `portal.manage`. A magic link stores only the hash of a random token, expires, and a login link works once. Every portal query uses the customer id from the session. Internal cost, markup, profit, suppliers, waste, and internal notes are not on those pages. Quote acceptance and artwork approval need an unticked confirmation, store the statement text, and notify the assigned person. A change request does not edit the quote or replace the artwork file. The next artwork upload is a new revision. Customer job status comes from `portal_status_map`. Quality-control failures are not shown as customer status. Uploaded files use the same type checks as staff uploads and are stored outside the public folder. Email templates exist and stay inactive until a sender is configured. WhatsApp is a `wa.me` link with text to copy. It is not a WhatsApp Business connection.

`php tests/phase7.php` checks the formula engine, the 2.88 m² / 5.76 m² example, recipe versions on old and new quotations, job generation from the snapshot, stock suggestions that do not consume stock, a survey linked to a later quote, portal access between two customers, quote acceptance, artwork approval and change requests, and rejected uploads.

## Phase 8 scheduling and capacity

Phase 8 does not create a second task list. It puts a time and a resource on the jobs, job tasks, production stages, installations, and surveys that already exist.

Utilisation is scheduled productive minutes divided by available working minutes, times 100. Available minutes come from the work schedule for that resource, or the company default when the resource has none. Leave, maintenance, breakdowns, and calendar exceptions are removed. A closed calendar date contributes nothing. Over-capacity is the scheduled minutes that do not fit. The board does not squeeze them into the day.

A resource with concurrent capacity of 1 cannot be booked twice for the same time. That conflict cannot be overridden. Leave, a public holiday, hours outside the work schedule, an unfinished previous stage, missing artwork approval, and a material shortage can be overridden by someone with `schedule.override_conflict`, and the reason is stored. Planned work may stay on the board while materials are short. Confirming it, or starting it, warns until the shortage is resolved or overridden.

Recipe expected labour fills a stage estimate when the description matches the stage name. Completing the stage stores actual minutes beside that estimate. The estimate is left as it was.

The customer promised date and the internal target are separate. Moving a schedule entry does not change the promised date. The portal shows the promised date, and a schedule entry only when that entry is marked customer visible.

Machine hourly cost stays off the job while `machine_cost_in_job` is 0, because a recipe may already include the machine in labour or overhead. Vehicle travel uses `travel_rate_per_km`, stores that rate on the trip, and posts one travel cost. A completed subcontract posts its actual cost once. The order keeps the other-cost id so a second completion does not add it again.

A recurring template creates one follow-up for a due period. It does not create an invoice. Run it from Automation → Recurring jobs, or from `cron/run.php`.

`php tests/phase8.php` checks an overlapping printer, leave, 80% and 120% utilisation, a print-before-laminate dependency, a material shortage, a machine breakdown that does not move work, a shared vehicle, one monthly follow-up, a subcontract cost posted once, a customer date that survives an internal change, and schedule permissions on the action and the URL.

## Phase 10 leads and communications

A website enquiry, a phone call, and a walk-in all become a lead (`SFL-2026-0001`). The lead is kept after conversion. Conversion creates or links a customer, a contact, and an opportunity in one transaction. The opportunity keeps the first-touch source and campaign. Quotes, jobs, and invoices copy that attribution once and do not replace it. This is first-touch attribution, not multi-touch.

Public submissions use `POST /api/public/leads`. The notes in `docs/website-leads.md` show the fields. A honeypot, a minimum form time, a per-address hourly limit, and a one-day duplicate message check sit in front of the insert. The response does not include a database id.

Email delivery is `off`, `log`, or `smtp`. The SMTP password and the webhook secret are stored outside the normal settings form and are not shown again. WhatsApp is manual: a `wa.me` link is logged as prepared, not delivered. Automation can create reminders and drafts. It does not email a customer unless `automation_outbound_enabled` is `1`, and even then a rule does not send by itself.

Repeat customer rate is customers with more than one completed job, divided by customers with at least one completed job. Cost per lead is campaign budget divided by leads that are not spam. Unqualified leads stay in that count. Estimated customer acquisition cost is budget divided by accepted quotes, and only when both numbers exist.

`php tests/phase10.php` covers capture, phone matching, conversion, email success and failure, WhatsApp preparation, follow-up dedupe, attribution, unsubscribe, lead visibility, webhooks, templates, and the public rate limit.

## Phase 13 workflows, approvals, and assistance

Phase 13 adds structured configuration on top of the existing records. It does not add a scripting language. Conditions use a fixed comparison list. Actions use a fixed list and call the existing job, quote, purchasing, and payment services. A workflow cannot run PHP, SQL, or a shell command.

A business event is stored once, with a unique event id. The same event does not create a second approval, follow-up, or purchase request. If a workflow changes a status, and that change would run the same workflow again, the chain stops. Depth is limited to three. Test mode shows which conditions matched and which actions would run. It does not run them. Saving an active workflow keeps the previous definition, who changed it, and an optional reason.

Quote issue uses an approval policy. Margin below 25 percent needs management approval at any value. A total of at least R100,000 needs management approval. A total from R25,000 and below R100,000 needs the sales role. A smaller quote with a healthy margin does not. Escalation after the configured hours notifies someone. It does not approve the request. Delegation stores the original approver, the delegate, the dates, and a reason.

Custom fields are validated on the server. A form submission stays on the form version that was current at the time. Older QC, installation, and dispatch checklists are not rewritten.

Payment links take the amount from the invoice balance. A browser return does not record a payment. A provider webhook must match the signature, the reference, the currency, and the amount. The same provider event records one payment through the existing payment service. Accounting sync failure leaves the invoice issued and queues an integration issue. Payment and accounting issues are not retried automatically.

Assistance is off until a provider is configured. Draft text does not change a price, a status, or a balance, and it is not sent. Extraction is a proposal until a person confirms it, and confirmation still does not change a product cost or create a purchase order. A question about profitability is refused before any provider call when the user cannot open profitability. Document text is data. Instructions inside a document are not commands. Configuration export omits passwords, SMTP secrets, and payment secrets.

`php tests/phase13.php` covers the large-quote approval, the low-margin rule, the workflow loop, custom fields, form versions, payment webhooks, extraction, assistance boundaries, prompt injection, an offline accounting provider, and a secret-free export.

## Phase 14 mobile field work

Phase 14 is a phone and tablet layer on the records that already exist. It is not a second ERP, and it is not an offline copy of pricing, quotes, invoices, payments, stock, purchase orders, or approvals. Those stay on the server. A phone can keep a downloaded field pack and queue survey measurements, notes, photos, installation checklists, snags, signatures, delivery exceptions, time, and mileage. Sync sends them to `/api/v1/sync` with the signed-in session and the CSRF token. Each operation has an `operation_uuid`. The server stores that id and a repeat does not create a second survey line, photo, signature, snag, time row, or proof of delivery.

A field pack is the job, survey, or delivery the person is about to leave with: contact, address, approved artwork revision, measurements, instructions, checklist, and the item codes for that delivery. It does not include cost or margin. If the office replaces the approved artwork, changes the address, cancels the job, or changes the installation instructions hash, the pack is marked stale and completing the installation is blocked until the pack is downloaded again. A photo can still sync when the office only moved the target date. If the installer and the office both edit the same installation notes, the sync opens a conflict. The resolver shows the office text and the offline text, then keep server, apply offline, or a manual merge. The screen does not show the raw payload.

Photos are compressed in the browser before upload. The server accepts PNG and JPEG, rejects a file over the configured byte limit, and stores it under `storage/field-photos/`, outside the public folder. An annotation is a second file. The original path is not overwritten. There is no GD or Imagick resize on the server in this build. The client canvas does the reduction.

GPS is taken only when the person starts travel, arrives, signs, or completes, and only if the browser allows it. Denying location does not stop the step when the policy is optional. Sign-Forge does not record a route and does not track a person in the background. A trusted device is a name for sync and push. It does not skip the password. Revoking a device stops the next sync and clears IndexedDB when that response arrives. Until the phone contacts the server, the browser still holds the local drafts. IndexedDB encryption depends on the phone. This is not a claim of hardware-grade encryption, and the phone is not a backup. Cleanup never deletes a draft that has not synced.

The service worker cache is `signforge-shell-v3`. It holds the shell CSS, JavaScript, icons, and the offline page. It does not cache signed-in HTML or API responses. Install Sign-Forge from Chrome on Android with the Install app button or the browser menu. On iPhone, use Share, then Add to Home Screen. Some camera and background-sync behaviour differs on Safari. An Android package is optional later and should wrap this same site with Trusted Web Activity or Capacitor. Do not keep a second copy of the business rules in an app.

`cron/run.php` also closes old field packs (`field_packs_closed=`). `php tests/phase14.php` covers one survey sync, a duplicate operation, a photo retry, an instruction conflict, a safe photo merge, stale artwork, one signature, one proof of delivery, a blocked material issue, a blocked payment, a revoked device, a lost permission, quiet hours, and a storage limit.

## Upgrading a Phase 13 database

Import `database/migrations/014_mobile_offline_field.sql` once. Do not import `schema.sql` over a live database, and do not run this migration on a database created from the current `schema.sql`.

## Upgrading a Phase 12 database

Import `database/migrations/013_workflows_configurability_ai.sql` once. Do not import `schema.sql` over a live database, and do not run this migration on a database created from the current `schema.sql`.

## Phase 12 planning, forecasting, and integrations

Phase 12 reads the records already in the system. It does not create a second job, stock, invoice, or purchasing ledger. A forecast is labelled with its source, the calculation, the assumptions, and the time it was generated. Actual, committed, forecast, target, and budget figures are not added into one revenue number.

Weighted pipeline is the opportunity estimated value times the probability entered on that opportunity, divided by 100. A blank probability contributes nothing to the weighted total. The raw value is still shown. Historical quote conversion is context only. Quote age does not assign a probability.

Backlog is accepted commercial work that is not complete and not cancelled. For each open job, commercial value is `quoted_revenue_snapshot`. Completed scope is that value times completed item quantity divided by total item quantity. An item counts only when its production status is COMPLETE. Remaining backlog is commercial value minus completed scope. A job with no items stays fully in the backlog. Payments and invoiced amounts are shown beside it and are not subtracted. Periods are this week, next week, this month, next month, later, and unscheduled, from the job target date.

The potential invoice schedule uses unpaid quote deposits (basis DEPOSIT) and the remaining commercial value dated by the job target date (basis JOB COMPLETION). It does not create invoices. Issued invoice balances are contractual receivables, dated by the invoice due date. The historical cash scenario moves the displayed date by the customer’s median days to pay. It does not change the amount. Unissued work is forecast future cash, not a receivable. Approved purchase orders are a purchasing cash requirement, not an accounting payable. The cash screen says OPERATIONAL FORECAST — NOT BANK RECONCILIATION. There is no bank feed.

Material net requirement is time-phased. Available stock is on hand minus reserved. Incoming stock counts only when it arrives on or before that demand date. A later purchase order does not cover an earlier shortage. Safety stock is the product minimum level and is applied once at the end. Forecast demand is excluded. Net requirement = dated shortfalls + safety gap. Example: gross 120, available 50, on-time incoming 40, safety 10, net 40.

Order quantity is raised to the supplier minimum, then rounded up to the pack size. 37 with a minimum of 50 becomes 50. 23 with a pack of 10 becomes 30. Order-by date is the required date minus supplier lead time minus `planning_order_buffer_days` (default 2). Weekends are skipped when `planning_skip_weekends` is 1. A recommendation does not send a purchase order. A person reviews it and can create a purchase request.

Committed capacity is scheduled hours plus unscheduled committed hours. Remaining committed capacity is available minus that total, and not below zero. Forecast pipeline hours are shown separately and are not subtracted. A shortfall is labelled PROJECTED BOTTLENECK.

Operational budget variance is actual minus target. R500,000 targeted and R460,000 actual is −R40,000 and −8%. A scenario stores a copy of the calculation. A 10% material increase changes the scenario cost only. The product cost and the issued quote stay as they are.

Snapshots in `forecast_snapshots` are appended. An older forecast is not overwritten. Accuracy is actual minus the stored forecast.

CSV import previews customers before it writes. An email or phone match is an error. The same company name is a warning and is not merged. The versioned API is `/api/v1/`. A client sends `Authorization: Bearer {identifier}.{secret}`. Only the SHA-256 of the secret is stored. A missing credential is 401. A missing scope is 403. Responses are `success`, `data`, `errors`, and `meta`. Outbound webhooks are signed with HMAC-SHA256 of `timestamp.body` in `X-SignForge-Signature`, with `X-SignForge-Event` and `X-SignForge-Timestamp`. Retries are 1 minute, 5 minutes, 30 minutes, and 2 hours, then the delivery fails. A failed webhook or a failed accounting sync does not roll back the internal invoice. `accounting_provider` empty means no sync. The value `unavailable` records SYNC FAILED for tests. There is no live Sage, Xero, or QuickBooks connection.

`business_locations` and optional location columns prepare a workshop, warehouse, or branch. Historical rows are not forced onto a location. This is not multi-company accounting.

`php tests/phase12.php` covers the weighted pipeline, backlog, MRP, a late purchase order, MOQ, pack size, capacity, contractual cash, budget variance, a scenario that leaves live costs alone, import preview, API 401/403/200, webhook retry, accounting failure, and a salesperson denied the cash screen.

## Upgrading a Phase 11 database

Back up the database first. Then import `database/migrations/012_planning_forecasting_integrations.sql` once. It adds planning, forecast, budget, API, webhook, and integration tables, and optional location columns. It does not delete jobs, stock, or invoices. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `012` on a database created from the current `schema.sql`.

## Phase 11 workshop execution and documents

Phase 11 does not create a second job, stock, or installation system. A scan resolves a token or a printed code back to the job, production item, roll, sheet, offcut, package, or dispatch that already exists.

A QR code contains only a random token. The database stores the SHA-256 hash of that token. The address is `/scan/{token}`. The code does not contain a database id, a cost, or a password. Opening it requires a signed-in user with `workshop.scan`. A tracking code such as `SFJ-2026-0042`, `ROL-2026-0041`, or `PI-2026-0042-01-01` can be typed or scanned with a keyboard wedge. The label also draws that code as Code 128. Phone cameras use the browser barcode detector when the device provides one.

A job item is `NONE`, `BATCH`, or `INDIVIDUAL`. Stickers stay one batch. Individual pieces stop at `individual_tracking_cap` (200) and become one batch above that. A partial completion of 6 out of 10 leaves the job item open.

The workshop job card omits selling price, margin, and supplier cost. A user with `costing.view` can open the costing view. Generating a new card marks the previous card superseded. An approved artwork change does the same. Signed delivery notes and proof of delivery are not overwritten.

Material issue uses the existing stock movement and job usage, once, keyed by an idempotency key. The wrong product is refused unless someone with `production.override_material` records a reason. A usable offcut becomes an inventory item with its own code and label. Customer-supplied material is still tracked, and its job cost is zero.

A failed quality check blocks dispatch until it is reworked or an authorised user records an override. Scanning the same piece into a dispatch twice does not add the quantity again. An item from another job is refused. Proof of delivery stores the signer, the statement version, and the server time. A second signature does not replace the first.

A critical snag blocks job completion. Someone with `jobs.complete` can override it with a reason, and that reason is audited. Completion does not create the final invoice. Accounts are notified of the remaining invoiceable amount and of reservations that are still open.

The workshop floor is `/workshop`. It polls `/workshop/board.json`. A shared kiosk at `/workshop/kiosk` asks for a PIN or a badge token. The PIN is hashed. An administrator badge is refused. Kiosk mode cannot open costing, product costs, users, or settings.

First-pass yield is items whose first quality check passed, divided by items inspected, times 100. Reprint rate is reprinted quantity divided by quantity completed on production items, times 100. Neither figure ranks an employee.

`php tests/phase11.php` covers a tampered token, the wrong material, one 4 m issue on a 20 m roll, an 800 × 500 offcut, a partial 6 of 10, a failed check that blocks dispatch, an incomplete dispatch, a duplicate scan, an immutable proof of delivery, a superseded job card, a snag that blocks completion, and workshop and installer permissions.

## Upgrading a Phase 10 database

Back up the database first. Then import `database/migrations/011_workshop_documents_tracking.sql` once. It adds tracking, production items, dispatch, signatures, and document tables. It does not delete jobs or rewrite stock balances. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `011` on a database created from the current `schema.sql`.

## Upgrading a Phase 9 database

Back up the database first. Then import `database/migrations/010_communications_leads.sql` once. It adds leads, campaigns, consent, and permissions. It does not delete customers or rewrite quote totals. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `010` on a database created from the current `schema.sql`.

## Phase 9 estimating and yield

An estimate is internal. A quote remains the customer document. Historical quote prices are not rewritten when a recipe, a cost, or a recommendation changes.

Sheet yield is a grid. Usable size is the sheet minus the edge margin on every side. Parts per side are `floor((usable + kerf) / (part + kerf))`. Both orientations are tried when rotation is allowed. The better count wins. A tie keeps the original orientation. The screen says best layout found. Mixed rectangles use a first-fit shelf pack with the same label. It is not a perfect layout.

Roll yield counts how many graphics fit across the usable width, including spacing, then the rows and the linear metres. Both orientations are compared unless the product is direction sensitive. The shorter length wins. Several roll widths are shown together. The user chooses. A predicted remnant is not written to inventory.

An offcut fits only when both sides are large enough. Area alone is not enough. Score is `1000000000 - leftover square millimetres`, plus 10000 for the same location, minus 1 if the part is rotated. Nothing is consumed automatically.

Gross margin sell price is `cost / (1 - margin)`. Markup sell price is `cost x (1 + markup)`. They are labelled separately. Variance percent is `(actual - estimated) / estimated x 100`. A zero estimate has no percent. Actual below estimate is favourable for cost. The summed difference on the pricing screen is cost estimate variance, not an accounting loss.

Recommendations use the median. Every sample value stays visible, including outliers. Fewer than `minimum_sample_size` completed values is insufficient history. Accepting a recommendation stores a new recipe version. Old quote snapshots keep their version.

`php tests/phase9.php` checks the sheet and roll examples, offcut dimensions, a 40% margin, a discount, labour statistics, a recipe version, quote risk, a fleet setup charged once, separate installation components, and rejected cost, formula, and permission changes.

## Upgrading a Phase 8 database

Back up the database first. Then import `database/migrations/009_estimating_intelligence.sql` once. It adds estimates, yield settings, and permissions. It does not change quote totals. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `009` on a database created from the current `schema.sql`.

## Upgrading a Phase 7 database

Back up the database first. Export it from the host panel and keep that file off the server. Confirm the export contains `users`, `quotes`, `jobs`, and `invoices` before you change anything.

Then import `database/migrations/008_resource_planning.sql` once. It adds resources, schedules, and the new permissions. Existing jobs, tasks, and installations stay where they are. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `008` on a database created from the current `schema.sql`.

## Upgrading a Phase 6 database

Back up the database first. Export it from the host panel and keep that file off the server. Confirm the export contains `users`, `quotes`, `jobs`, and `invoices` before you change anything.

Then import `database/migrations/007_signage_automation_portal.sql` once. It adds surveys, recipes, portal access, and the new permissions. Existing attachments stay internal. Existing quotations are not recalculated. Do not import `schema.sql` on that database.

A brand-new database uses `schema.sql` and `seed.sql` only. Do not also run `007` on a database created from the current `schema.sql`.

## Cron on Xneelo

In the hosting control panel, add a cron job that runs every hour:

```bash
php /usr/www/users/USERNAME/public_html/cron/run.php
```

Use the real path to this project. Do not point the cron URL at the website. Confirm `storage` and `storage/backups` are writable by PHP. The same job also flags overdue snags. Signatures are stored in `storage/signatures` and generated documents in `storage/documents`. Both folders must stay outside the public web root and remain writable by PHP.

## Restore

1. Create a fresh backup and confirm the history row says SUCCESS and the file is larger than zero.
2. Download that file while signed in as an administrator.
3. On a copy of the database, import the SQL file with the mysql client.
4. Check a known customer, quote, and invoice on that copy before considering the live database.
