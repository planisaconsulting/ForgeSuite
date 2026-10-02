# Go-live checklist

Use this on the company server. Items marked here as done were proved on the development copy on 2 October 2026. They are not a substitute for the same check on the host that will take real customers.

## Host

- [ ] Domain points at `public/`
- [ ] HTTPS is on, and HTTP redirects to it
- [ ] `app.env` is `production` and `app.debug` is false
- [ ] `config.local.php` or `SF_*` variables hold the live database password and are not in git
- [ ] The administrator password from the seed file has been changed
- [ ] Directory listing is off. `storage/` is not under the document root
- [x] Development copy: `/health` returns `{"status":"ok"}` and does not show a stack trace

## Data

- [ ] Database backup taken immediately before the cutover
- [x] Development copy: a 98 MB dump restored into a side database and the counts matched. The copy was then dropped
- [ ] Off-server encrypted copy of that backup exists
- [ ] Uploads, documents, signatures, quote files, and field photos are in the file backup
- [ ] Opening stock will be posted as `OPENING_BALANCE` movements, not a quantity typed onto the product
- [ ] Opening invoices, if migrated, are real invoice rows. No general-ledger journal is invented
- [ ] Demo and test customers are removed or archived with the application’s archive path, not a hand-written delete

## Operations

- [ ] Company name, VAT rate, currency format, and timezone checked
- [ ] Number prefixes checked
- [ ] Products, prices, and suppliers checked
- [ ] Users exist for sales, design, workshop, purchasing, finance, installers, and management
- [ ] Hourly cron runs `php /path/to/signforge/cron/run.php` and system health shows a success
- [ ] Email mode is what the company intends. Core saves must still succeed if the message fails
- [ ] Maintenance mode is understood: set `maintenance_mode` to `1` during the upload, then back to `0`
- [ ] Portal, supplier, and contractor accounts tried from outside the office network
- [ ] API clients, if any, use hashed secrets that can expire and be revoked

## After the first sign-in

- [ ] Search a customer
- [ ] Open a quote and its PDF
- [ ] Open a job
- [ ] Check a stock balance from the movement ledger
- [ ] Open an invoice balance
- [ ] Upload and download one file
- [ ] Remove or archive the smoke-test records

## First days

- [ ] Read the error log and the cron status each day
- [ ] Compare new invoices, payments, stock movements, and expenses with the previous records
- [ ] Write surprises in `docs/release/SUPPORT_LOG.md`
