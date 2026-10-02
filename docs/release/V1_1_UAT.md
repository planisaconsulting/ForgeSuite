# v1.1.0 UAT record

Date: 2 October 2026. Environment: development PHP 8.3.6 and MariaDB 10.11.14. No business user signed these rows.

Automated rows were executed. Manual rows were not. A manual row is not a pass.

## Automated

| ID | Scenario | Role | Preconditions | Steps | Expected | Actual | Result | Defect | Retest |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A-01 | v1.0 and v1.1 regression | System | Working database, dev server for acceptance | 31 suites listed in the baseline | fail 0 | fail 0 in about 18.6 seconds, before the seed fix | PASS | | Release suite includes `tests/v11_phase11.php` |
| A-02 | Fresh install | Installer | Empty database | Import `schema.sql` then `seed.sql` | Tables, permissions, administrator | First attempt stopped on a bad seed line. After the comment fix, 402 tables, 382 permissions, one administrator | PASS | Seed line, fixed | CLEAN_OK |
| A-03 | Upgrade chain | Installer | Empty database | Migrations `001` through `025` | No manual repair | All 25 files applied. 402 tables, including `expenses` | PASS | | |
| A-04 | Invoice balance | Finance | Issued invoices in the working database | Compare `balance_due` with allocations and credits | No mismatch | 0 mismatches | PASS | | |
| A-05 | Expense posted once | Finance | Expense postings | Duplicate allocation groups | None | 0 | PASS | | `v11_phase10` also posts R120 once |
| A-06 | Logistics source once | Logistics | Logistics costs | Duplicate source groups | None | 0 | PASS | | |
| A-07 | Numbering | Sales | Live sequences | 20 parallel quote numbers, then 20 for job, invoice, PO, project, asset, expense, RFQ | All unique | All unique, no errors | PASS | | |
| A-08 | Money types | Finance | Information schema | Float or double columns | None | 0 | PASS | | |
| A-09 | Stock ledger shape | Inventory | Products table | `stock_quantity` column | Absent | Absent | PASS | | |
| A-10 | Health and cron | Admin | Dev server | `GET /health`, then `php cron/run.php` | `{"status":"ok"}`, cron exit 0 | Both | PASS | | `webhook_retries=50` were already due |
| A-11 | Restore | Admin | Latest dump | Import into a side database and compare counts | Counts and totals match, then drop the copy | Users, customers, quotes, jobs, invoices, payments, stock, projects, assets, shipments, expenses, and attachments matched. Quote and invoice sums matched | PASS | | |

Phase suites `v11_phase2` through `v11_phase10` cover the sales, project, asset, estimator, release, procurement, portal, artwork, logistics, intake, and expense examples those phases defined. They passed in A-01. This record does not repeat each assertion.

## Not run

| ID | Scenario | Role | Preconditions | Steps | Expected | Actual | Result | Defect | Retest |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| M-01 | Quote a 10-off 1200 × 800 ACM job on screen, including PDF and acceptance | Sales | Company products and VAT | Use the quote screen | Snapshot unchanged after a later cost change | Not performed on screen | NOT RUN | | |
| M-02 | Ten-site project on screen | Management | Sites and jobs | Open project financials | No double count | Not performed on screen. Service tests passed earlier | NOT RUN | | |
| M-03 | Installer phone: checklist, photos, signature, offline, then sync | Installer | A device and a lost network | Field pack | One set of records | Not performed on a phone. `phase14` passed in process | NOT RUN | | |
| M-04 | Customer, supplier, and contractor portals from clean external accounts | Those users | Accounts outside the office | Sign in and open only their records | No other party’s data | Not performed in a browser | NOT RUN | | |
| M-05 | Visual PDF and label check | Each role | Generated documents | Read branding, page breaks, currency, customer-safe fields | Readable | Not inspected by eye | NOT RUN | | |
| M-06 | Firefox, Edge, Safari, and 360 / 390 / 768 / 1024 / 1440 layouts | Each role | Those browsers | Walk the main path | Usable | Not run | NOT RUN | | |
| M-07 | SMTP for quote, portal, reset, RFQ, and contractor mail | Admin | A real SMTP account | Send and fail a message | The business row stays saved | Not run. Email is not configured here | NOT RUN | | |
| M-08 | Five to ten genuine jobs | The company | Live work | Quote through invoice | The team can run tomorrow’s work | Not run. This environment has test data only | NOT RUN | | |
| M-09 | Company sign-off | Management | The checklist | Sign `GO_LIVE_CHECKLIST.md` | Accepted | Open | NOT RUN | | |

No blocker or critical defect is left open. The two defects found in this pass are in `SUPPORT_LOG.md` and are fixed in this tree.
