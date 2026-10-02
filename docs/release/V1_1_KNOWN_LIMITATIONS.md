# v1.1.0 known limitations

These are the limits that remain after the release pass. They are not a promise to build them in v1.2.

- No general ledger, payroll, bank feed, accounts-payable payment run, or automatic reimbursement. An expense export is a CSV. `REIMBURSED_EXTERNALLY` records a report from outside. It does not pay anyone. Employee bank details are not stored.
- No live Sage, Xero, QuickBooks, or PayFast connection unless that connector is configured later. A browser return from a payment page does not mark an invoice paid.
- No live language-model API. Sales intake uses deterministic reading plus a scripted wording provider. A price written in the message does not become the quote price. Photographs are not measured.
- No live courier HTTP API, no route optimiser, and no continuous GPS. Courier mode can store a waybill. It does not collect the goods.
- The estimators do not certify structure, wind load, electrical installation, fire performance, or foundations. Channel-letter and pylon results are manufacturing quantities.
- iCalendar is a subscribe link. There is no Google or Microsoft two-way sync. An outside calendar cannot move an installation. The timezone block uses a fixed SAST offset of `+0200` even if the company zone name is something else.
- No CorelDRAW plugin, RIP, or variable-data engine. CDR, AI, EPS, and PSD files are not inspected.
- Customer, supplier, and contractor portals do not send the invite email from this build. The user copies the link. Password reset email is not self-service.
- The operational inbox lists expense approvals and overdue tasks. Quote review, artwork, purchase orders, and contractor completion stay in their own screens.
- Phase 8 field expenses stay a separate capture table. They are not reimbursed from the Phase 10 expense record.
- Customer merge moves known `customer_id` links and stops on a unique collision. It does not offer a field-by-field picker.
- API routes were exercised in process by the phase suites. They were not called over HTTP in this pass, because the JSON helper ends the request.
- Performance was measured on the working database (about 4,100 customers, 14,900 quotes, 14,500 jobs, 26,500 assets, 1,600 stock movements). A 10,000-customer and 500,000-movement set was not loaded.
- Numbering locks were tested with twenty processes. Two processes racing past a serial check can still meet the unique key. The unique key then rejects the second write.
- The restore drill compared a SQL copy on the same server and then dropped it. It did not boot a second web server on that copy. Receipt and artwork bytes live in `storage/` and have to be in the file backup.
- A business user has not signed the UAT boxes. Five to ten genuine Sign-Forge jobs were not run as a pilot in this environment. There is no company production server here to deploy to.
- Chrome-compatible checks of `/login` and `/health` were run. A full Firefox, Edge, Safari, and phone matrix was not.
- Physical label printers, a workshop TV, and an iPhone Home Screen were not in this environment.
- Content-Security-Policy still allows inline scripts because existing screens use them.
- The local listener is HTTP, so the session cookie is not marked `Secure` here. Production HTTPS sets that flag.
