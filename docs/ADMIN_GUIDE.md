# Administrator guide

## Users and permissions

Create each person with a role and a password of at least 10 characters. New users must change the password. Disable a leaver instead of deleting them. Roles and the codes they hold are in `docs/PERMISSIONS.md`.

## Pricing, products, and stock

Products store the current cost and sell rules. A quote copies those figures onto the line. Changing the product later does not change the saved quote. Opening stock is an `OPENING_BALANCE` movement. A stock count posts the difference as a correction movement. It does not rewrite old issues.

## Numbering

Prefixes live in settings. The next number is a locked counter, so two people saving at once do not get the same quote, job, invoice, purchase order, asset (`asset_prefix`, SFA), service request (`service_request_prefix`, SFSR), warranty claim (`warranty_claim_prefix`, SFWC), or service agreement (`service_agreement_prefix`, SFM) number. `asset_label_contact` is the wording on the asset label. `warranty_alert_days` is the internal expiring window used on the asset screen. The cron also notifies at 90, 30, and 7 days.

## Templates and documents

Customer PDFs use the company details and VAT number from settings. Check a quote, an invoice, a credit note, a statement, a delivery note, and a site survey after you change the logo.

## Workflows and approvals

A workflow can notify, create a task, or call an existing service. It cannot run SQL or a shell command. Test mode shows what would happen and does not do it. Low-margin and large quotes follow the approval policy. An approval is not granted by an email link alone.

## Integrations, backups, devices

Connectors, API clients, and mail settings are under Administration. Take a backup before a deployment and copy it off the server. Revoke a lost phone under Mobile and devices. Trust does not skip the password.

## Projects

Project types, milestone types, delay reasons, risk categories, and templates are loaded by the v1.1 migration. A template change does not rewrite milestones already copied onto a project.

`project_prefix` defaults to SFP. `project_closeout_block_critical_snags` defaults to 1, so a critical snag blocks operational completion. `project_closeout_block_open_invoices` defaults to 0, so an unpaid invoice is a warning, not a block. `project_date_reason_required` defaults to 0.

Project changes use the existing approval engine when a policy exists for entity PROJECT and action CHANGE. With no policy, a user who has `projects.manage_changes` can approve the change.

## Sign specifications

Specifications live under Estimating. Create a draft, add materials, labour formulas, and compatibility rules, then send it for approval. Only a user with `specifications.approve` can approve it. Approving a new version supersedes the previous approved version and does not change quotes or jobs already linked to the old one.

Labour formulas use FormulaService. `eval`, `exec`, and raw SQL are rejected. Allowed names are `W`, `H`, `D`, `Q`, `AREA`, `PERIMETER`, `FACE_AREA`, `RETURN_DEPTH`, `VECTOR_AREA`, `VECTOR_PERIMETER`, `SIDES`, and `MODULE_WATTS`.

LED and power-supply profiles store wattage, spacing or modules per square metre, voltage, and the recommended maximum load percent. Do not put 80 percent in a formula. Put it on the profile.

Geometry uploads are limited by `geometry_max_bytes` (262144) and `geometry_max_paths` (200). Wrap hours and complexity factors are the `wrap_hours_*` and `wrap_factor_*` settings. A dimension above `dimension_warn_mm` (8000) warns and is not rewritten.

Vehicle templates record a source: manual, customer measured, supplier template, licensed library, or other. Leave `verified` off until someone has checked the sizes. Do not import a licensed template library without a licence. This release does not ship one.

## Production release

`release_prefix` defaults to SFR. Release policies are `DEFAULT`, `VEHICLE` (job type `VEHICLE_WRAP`), and `CHANNEL` (job type `CHANNEL_LETTER`). Change a severity on `production_release_policy_checks` only when you mean to change what can stop a release. `BLOCK_NO_OVERRIDE` cannot be waived from the screen.

Channel letters store `approval_required`. The release still uses the check severities. It does not open a separate approval policy by itself.

The hourly cron includes `production_overdue`, which is a count of released jobs past their internal target date. It does not email the customer.

## System health

Administration → System health shows the release, PHP, database, storage, last cron, last backup, failed automations, and failed field sync. It does not show passwords.

## Maintenance mode

Set `maintenance_mode` to `1` during a deploy. Staff see a holding page. An administrator who is already signed in can still work and turn it off. `/health` stays public and minimal.
