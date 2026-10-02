# v1.1.0 release acceptance

Application: Sign-Forge ERP

Version: 1.1.0

Release date: 2 October 2026

Commit: tag `v1.1.0` on branch `cursor/release-v1.1.0-c8ce`. The pre-hardening point is tag `v1.1.0-pre-hardening` at `4172d3e`.

Database migration level: `025_v1_1_operational_refinement.sql`. No migration 026.

Environment: PHP 8.3.6, MariaDB 10.11.14, development. Not a company production host.

## What was proved

- Automated regression before the release edits: fail 0.
- Fresh install from `schema.sql` and `seed.sql` after one seed-line fix: 402 tables, 382 permissions, one administrator.
- Migrations `001` through `025` on an empty database: 402 tables, the same count as the live database and as `schema.sql`.
- Issued invoice balances, expense postings, and logistics sources had no duplicates in the working database.
- Twenty concurrent numbers were unique for quotes and for the other document types listed in the baseline.
- `/health` returned `{"status":"ok"}`. Cron exited 0.
- A 98 MB dump restored in about 9 seconds. Counts and invoice and quote sums matched. The side database was dropped.
- No `FLOAT` money columns. Stock has no quantity column on the product.

## What was not proved

- A business user did not sign UAT.
- Five to ten genuine jobs were not piloted.
- Firefox, Edge, Safari, and phone layouts were not walked.
- PDFs and labels were not read by eye.
- SMTP was not sent.
- The API was not called over HTTP.
- The restore did not boot a second web server. File bytes were archived, not replayed through the application.
- This environment was not deployed to a public host.

## Open defects

None at blocker or critical. The seed-line and log-rotation defects are fixed and listed in `docs/release/SUPPORT_LOG.md`.

## Release decision

READY WITH DOCUMENTED NON-CRITICAL LIMITATIONS

The software build is consistent, the automated suites passed, and a backup was restored and checked. It is not a switch-on for the company’s live books until the go-live checklist is done on that server, the administrator password is changed, and a short pilot of real jobs has been watched. Those steps are operational. They are not an open code defect in this tree.

## Sign-off

| Role | Result | Date |
| --- | --- | --- |
| Prepared by | v1.1 Phase 11 release pass | 2 October 2026 |
| Technical review | Automated suites, install, upgrade chain, integrity queries, and restore comparison passed in this environment | 2 October 2026 |
| Business / UAT review | Not signed | |
| Release approval | Not signed by the business. The technical decision above is ready with the limitations in `V1_1_KNOWN_LIMITATIONS.md` | 2 October 2026 |
