# Administrator

1. Create each person and give them a role. Hiding a menu is not the permission. The server checks the role.
2. Change the seeded administrator password before any real customer data is entered.
3. Set company name, VAT, numbering prefixes, and timezone in settings.
4. Turn `maintenance_mode` on before an upgrade and off after the smoke test.
5. Take a database backup and a copy of `storage/` before the upgrade. A backup counts only after you have restored it somewhere else and checked a customer, a quote, a job, an invoice, and a file.
6. Schedule `php /path/to/signforge/cron/run.php` every hour. System health shows the last success, the duration, the last failure, and the next expected hour.
7. Keep an encrypted copy of the backup off the server.

See `docs/ADMIN_GUIDE.md`, `docs/CRON_JOBS.md`, and `docs/release/GO_LIVE_CHECKLIST.md`.
