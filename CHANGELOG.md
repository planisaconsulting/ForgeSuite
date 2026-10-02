# Changelog

## 1.1.0

v1.1 Phase 1 adds projects and multi-site rollouts above the existing job.

A project can hold many sites, and a site can hold many jobs. Jobs still own artwork, materials, production, stock, installation, and actual cost. One-off jobs stay valid with no project.

Project numbers are `SFP-YYYY-####`. Commercial value comes from accepted project lines and approved changes. Allocations of a contract do not get added again. Payments stay cash collected. Health is calculated separately from status and includes the reasons.

v1.1 Phase 2 adds customer assets (`SFA`), warranties, warranty claims (`SFWC`), and service requests (`SFSR`). Service work stays on the existing job number. Jobs that are not `STANDARD` stay out of project commercial value and project actual cost. A product creates an asset only when that product is marked to do so, and only when a person confirms it.

v1.1 Phase 3 adds sign specifications and manufacturing estimators for vehicle wrap, channel letters, lightboxes, pylons, and panels. Quantities, yield, load, labour, and selling price are calculated on the server with the existing pricing and yield services. A specification version stays on the quote, job, and asset that used it. The estimators do not certify structure or electrical design.

v1.1 Phase 4 adds release to production and line-item fulfilment. An accepted quote is not a release. A release does not start the stage and does not consume stock. The release snapshot keeps the specification version, artwork revision, and bill of materials from that moment. A later size or artwork change keeps the old release and asks for a new one. A phone number does not.

v1.1 Phase 5 adds supplier RFQs (`SFRFQ`), supplier quotations (`SFVQ`), a token supplier portal, split awards into draft purchase orders, contract prices, quarantine, supplier returns (`SFRTN`), and hierarchical stock locations. The cheapest quotation is not awarded. A purchase order is not stock. A confirmed goods receipt is what makes quantity available. Quarantine stock is excluded from that available figure.

## 1.0.0

Sign-Forge ERP v1.0.0 is the first release of the signage operations system built through phases 1 to 15.

It covers customers and leads, estimating and quotations, jobs and artwork, inventory and purchasing, production and workshop scanning, scheduling, installations and dispatch, invoices, payments and credit notes, the customer portal, planning and approvals, and mobile field work with a controlled offline sync.

v1.0.0 adds production hardening on that base: a public health check that does not reveal the server, an error reference instead of a database message, maintenance mode with an administrator bypass, HTTPS redirect in production, browser security headers, a login limit by address, cron start and finish status, and the release documents under `docs/`.

Money stays on DECIMAL. Stock stays on the movement ledger. A phone is not a second set of books.
