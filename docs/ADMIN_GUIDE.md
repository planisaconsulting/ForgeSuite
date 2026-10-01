# Administrator guide

## Users and permissions

Create each person with a role and a password of at least 10 characters. New users must change the password. Disable a leaver instead of deleting them. Roles and the codes they hold are in `docs/PERMISSIONS.md`.

## Pricing, products, and stock

Products store the current cost and sell rules. A quote copies those figures onto the line. Changing the product later does not change the saved quote. Opening stock is an `OPENING_BALANCE` movement. A stock count posts the difference as a correction movement. It does not rewrite old issues.

## Numbering

Prefixes live in settings. The next number is a locked counter, so two people saving at once do not get the same quote, job, invoice, or purchase order number.

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

## System health

Administration → System health shows the release, PHP, database, storage, last cron, last backup, failed automations, and failed field sync. It does not show passwords.

## Maintenance mode

Set `maintenance_mode` to `1` during a deploy. Staff see a holding page. An administrator who is already signed in can still work and turn it off. `/health` stays public and minimal.
