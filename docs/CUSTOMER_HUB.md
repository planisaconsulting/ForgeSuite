# Customer hub

The customer hub is the existing portal at `/portal`. It is not a second customer record and it is not a shop.

A portal order is an intake. Submitting it does not release production, consume stock, create a purchase order, approve artwork, or issue an invoice. Sign-Forge reviews it. A requested date is not a confirmed date.

## Navigation

Dashboard, Request a quote, Order signage, Assets, and Statement. A link is hidden when that portal user’s role cannot use it.

The dashboard still lists quotes, jobs, artwork, invoices, documents, and assets. Empty artwork says “No artwork is waiting for your approval.”

## Order types

Catalogue orders are `customer_orders`, numbered `SFCO-YYYY-####`. Quote requests are `customer_quote_requests`. Service problems still use the Phase 2 service request. A new branch is a `PENDING` site request. It does not create a project site until someone inside Sign-Forge accepts it. A matching branch code or address is warned and is still not created automatically.

## What the customer sees

Projects show factual site counts: installed, in production, awaiting approval, scheduled. Cost, margin, and supplier data are not on that page.

Jobs keep the existing customer status labels. Release overrides, internal blockers, and the bill of materials are not added to the portal.

Sales → Portal requests counts new quote requests, submitted orders, orders waiting for information, and cancellation requests.

## Cancellation

The customer can ask to cancel. The job status is not changed. If a staff user who can request a production change is recorded, `ProductionChangeImpactService` files the request as a review. It does not cancel the job.
