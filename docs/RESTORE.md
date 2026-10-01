# Restore

1. Use a clean database name. Do not restore over the live database until you have decided the live copy is unusable.
2. Create the empty database.
3. Import the SQL dump: `mysql database_name < backup.sql`.
4. Copy `storage/uploads`, `storage/documents`, `storage/signatures`, `storage/quotes`, and `storage/field-photos` back into place.
5. Point `config.local.php` at the restored database, with `app.debug` false.
6. Open `/health`, then sign in.
7. Open a customer, a quote PDF, a job, an invoice, a signature or delivery document, and a stock item.
8. Check foreign keys are present, the login index from migration 015 is present, and file paths on those documents exist on disk.

v1.0.0 restore drill, 1 October 2026, on the development database:

- Source: `mysqldump --single-transaction` of the working database, 7.5 MB, about 0.2 seconds.
- Destination: a new database `signforge_v1_restore`, import about 1.4 seconds, then the database was dropped.
- Counts after import matched the source: 233 users, 1,343 customers, 502 quotes, 329 jobs, 298 invoices, 116 payments, 614 stock movements, 22 signatures, 17 proofs of delivery.
- 465 foreign keys. The login-address index was present.
- The application itself was not switched onto that database. The check was a SQL restore and row validation, then deletion of the copy.

Repeat this drill on the company server before go-live, against that server’s backup, and sign the result in the production checklist.
