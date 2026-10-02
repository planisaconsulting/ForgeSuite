# v1.1.0 baseline

Recorded 2 October 2026, before the release fixes, then updated with the results of those fixes.

## Commit and version

- Application: Sign-Forge ERP
- Version constant: `1.1.0` (`app/Version.php`). It was already `1.1.0` through the v1.1 development phases.
- Pre-hardening tag: `v1.1.0-pre-hardening` on commit `4172d3e` (Phase 10, “Add expenses, mileage, and calendar feeds for v1.1.”)
- Release branch: `cursor/release-v1.1.0-c8ce`
- Database migration level at the start of this phase: `025_v1_1_operational_refinement.sql` already applied. This phase does not add migration 026.

## Environment

- PHP 8.3.6
- MariaDB 10.11.14
- Web check: `http://127.0.0.1:8741/health` returned `{"status":"ok"}` with `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and a Content-Security-Policy. `Set-Cookie` was `HttpOnly` and `SameSite=Lax`. `Secure` was absent because this listener is HTTP. Production configuration sets `Secure` when HTTPS is on.
- `config.example.php` is `production` with debug off. The local overlay used for these tests is `env=local` and debug on. That file is not committed. Production must not copy it.

## Automated tests before release edits

31 suites, fail 0, about 18.6 seconds:

`acceptance`, `calculations`, `finance_flow`, `finance_math`, `inventory_flow`, `inventory_math`, `jobs_flow`, `operations`, `phase7` through `phase15`, `quotes`, `reporting`, `reporting_flow`, `sales_flow`, `v11_phase2` through `v11_phase10`, `v11_projects`.

Argument-only security helpers (`*_security.php`, `phase12_api.php`) and the older HTTP scripts were not launched without their arguments. The parent suites already call the security checks they need. `tests/v11_phase11.php` was added after this baseline and is part of the release retest.

## Known warnings at baseline

- Cron had not yet been run in this session. A later run succeeded and printed `webhook_retries=50` for items already due. That is the retry queue, not a failed cron.
- The application log was 17 KB. Nothing had rotated it.

## Defect found after the baseline

`database/seed.sql` line 1168 was a sentence, not a SQL comment. `schema.sql` loaded. `seed.sql` stopped. A fresh install was not usable until that line was commented. The migration chain `001` through `025` on an empty database did not hit that line and completed.

## Performance baseline

Timed on the working database after the suites, not on a synthetic 500,000-movement set:

| Query | Time |
| --- | --- |
| Customers with a company name prefix, 25 rows | 0.9 ms |
| Latest 25 quotes | 0.1 ms |
| Latest 25 jobs | 0.1 ms |
| Latest 25 invoices | 0.6 ms |
| Latest 25 expenses | 0.6 ms |
| Latest 25 assets | 0.6 ms |
| Stock summed by product, 25 groups | 0.7 ms |

Row counts at that moment: 4,143 customers, 14,879 quotes, 14,452 jobs, 737 invoices, 1,581 stock movements, 26,520 assets, 832 expenses. A 10,000-customer / 500,000-movement set was not loaded.

## Backup taken before edits

Database dump `storage/backups/signforge-20261002-052750.sql`, 94 MB, backup id 40. Uploads were still on disk (about 3.2 MB) plus documents, signatures, field photos, and quote files. A later dump, after the test data that the suites added, is the one that was restored and compared. See `docs/RESTORE.md`.
