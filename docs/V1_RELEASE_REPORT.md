# v1.0.0 release report

- Version: Sign-Forge ERP 1.0.0
- Candidate date: 1 October 2026
- Application environment used for the drill: local PHP 8.3.6, MySQL, `app.env=local`, debug on. Production configuration in `config.example.php` is `production` and debug off.
- Database: MySQL, reached through the application user
- Schema: migrations 001–015. 015 is the login-event address index only
- Automated tests: parent suites for phases 1–14 passed, and `php tests/phase15.php` passed
- UAT: checklist written in `docs/UAT_CHECKLIST.md`. A business user has not signed the boxes. That is a go-live step, not a code defect
- Security: session cookie HttpOnly and SameSite Lax; CSRF on browser posts; prepared statements; hashed passwords; public health without internals; error reference `ERR-`; address login limit; headers including CSP. See `docs/SECURITY.md`
- Performance: customer, quote, and job list queries on 1,343 customers, 502 quotes, and 329 jobs finished together in under two seconds. A company-name lookup plan returned in about 1 ms. A 100,000-row synthetic set was not loaded into this database
- Backup: `mysqldump --single-transaction`, 7.5 MB, about 0.2 seconds
- Restore: imported into `signforge_v1_restore` in about 1.4 seconds. Counts matched (233 users, 1,343 customers, 502 quotes, 329 jobs, 298 invoices, 116 payments, 614 stock movements, 22 signatures, 17 proofs of delivery). 465 foreign keys. Login index present. The copy was then dropped
- Browser: local Chrome-compatible checks of `/login` (200) and `/health` (200, body `{"status":"ok"}`, security headers present, `X-Powered-By` removed after the header change). A full Firefox, Edge, and Safari matrix was not run here
- Mobile and PWA: manifest, service worker, and Phase 14 protocol tests passed. A physical Android phone, tablet, and iPhone were not in this environment
- Integrations: provider calls stay off unless configured. Existing phase 12 and 13 tests cover 401/403, webhook signature failure, and duplicate payment
- Known limitations: `docs/KNOWN_LIMITATIONS.md`
- Outstanding medium and low items: `docs/V1_1_BACKLOG.md`
- Deployment steps: `docs/DEPLOYMENT.md`
- Rollback: `docs/ROLLBACK.md`. Migration 015 is an index add. It is not a data rewrite

## Sign-off

| Check | Status |
| --- | --- |
| Technical ready | Code, tests, and health endpoint passed in this environment |
| Data ready | Opening balances are a go-live task. The software posts `OPENING_BALANCE` movements |
| Finance ready | Existing finance tests passed. A company UAT sign-off is still open |
| Inventory ready | Ledger tests passed. Opening stock must be reconciled on the company data |
| Users ready | Roles exist. Each person still needs an account and a password change |
| Backup ready | Dump demonstrated |
| Restore verified | SQL restore demonstrated and the copy removed |
| Security reviewed | Controls in `docs/SECURITY.md`. Not a legal certificate |
| Documentation ready | `docs/` set for install, deploy, backup, restore, security, and UAT |
| Training ready | User guide, admin guide, and Help screen |
| Management acceptance | Waiting on the business. The software candidate is ready to pilot |

Recommendation: ready for a controlled pilot. Not a switch of every historical spreadsheet in one night until opening stock, opening debtors, email, and the company restore drill are signed.
