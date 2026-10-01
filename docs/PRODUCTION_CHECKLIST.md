# Production checklist

Complete this on the company server before real jobs are entered.

- [ ] Domain points at `public/`
- [ ] HTTPS works and plain HTTP redirects
- [ ] PHP 8.2+ with PDO MySQL
- [ ] `app.env` is `production` and `app.debug` is false
- [ ] Database user can use only this database
- [ ] `schema.sql` and `seed.sql` imported, or migrations applied on the existing database
- [ ] Migration 015 applied once if this database came from Phase 14
- [ ] Storage folders exist and are not web-executable
- [ ] Administrator password changed
- [ ] Roles checked against `docs/PERMISSIONS.md`
- [ ] Company name, logo, VAT number, and numbering prefixes set
- [ ] Products and pricing levels entered
- [ ] Opening stock posted as `OPENING_BALANCE` movements and reconciled
- [ ] Opening debtors imported only through the agreed process and reconciled
- [ ] Email tested, including a failed send that does not mark the document sent
- [ ] Quote and invoice PDFs checked on A4
- [ ] Cron hourly, and System health shows a success
- [ ] Backup taken and copied off the server
- [ ] Restore tested on a copy. See `docs/RESTORE.md`
- [ ] `/health` returns `ok` and does not show a version or a path
- [ ] PWA install tried on one Android phone
- [ ] Logs reviewed with debug off
- [ ] Pilot jobs chosen. See the release report

Go-live data freeze, if you are leaving spreadsheets: pick a Friday afternoon, stop new rows in the old sheet, import, reconcile, and start in Sign-Forge on Monday. Do not run two permanent systems. A short parallel check of invoices and stock is enough, then close the sheet.
