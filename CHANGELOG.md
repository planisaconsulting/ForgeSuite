# Changelog

## 1.0.0

Sign-Forge ERP v1.0.0 is the first release of the signage operations system built through phases 1 to 15.

It covers customers and leads, estimating and quotations, jobs and artwork, inventory and purchasing, production and workshop scanning, scheduling, installations and dispatch, invoices, payments and credit notes, the customer portal, planning and approvals, and mobile field work with a controlled offline sync.

v1.0.0 adds production hardening on that base: a public health check that does not reveal the server, an error reference instead of a database message, maintenance mode with an administrator bypass, HTTPS redirect in production, browser security headers, a login limit by address, cron start and finish status, and the release documents under `docs/`.

Money stays on DECIMAL. Stock stays on the movement ledger. A phone is not a second set of books.
