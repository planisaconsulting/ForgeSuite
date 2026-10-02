# Customer portal security

Portal sign-in stays on `portal_user_id`. It is never written into the staff `user_id`. Login is rate limited. The session id is regenerated on sign-in. Portal posts use the staff CSRF field.

## Who can see what

Every catalogue, order, project, artwork, and document read uses the customer id on the signed-in portal user. A customer id posted in a form is not the owner of the order.

A user with `site_scope` `SELECTED` can use only the project sites granted on `portal_user_sites`. A site that belongs to another customer is denied even if the id is guessed.

Artwork and attachments keep the existing portal queries. An attachment is visible only when its visibility is `CUSTOMER_VISIBLE` or `CUSTOMER_UPLOADED` and the quote, job, invoice, or customer row belongs to that customer. `INTERNAL` is denied.

Portal messages with visibility `INTERNAL` are not returned to the customer. Notes are stored as typed and escaped with `htmlspecialchars` when shown.

## Files

Executables and a `.pdf` that does not start with `%PDF` are refused before the existing attachment check. Stored names stay random. Downloads go through the portal attachment query, not a public path.

## Portal roles

`ADMIN`, `BUYER`, `MARKETING`, `PROJECT_MANAGER`, `SITE_MANAGER`, `ACCOUNTS`, `VIEW_ONLY`, and `CUSTOM`. Existing portal users remain `ADMIN`, so current quote and artwork approval still works. `CUSTOM` uses the comma list in `custom_permissions`.

These codes are not staff permissions. Staff use `customer_hub.view`, `customer_catalogues.manage`, and `customer_orders.review`.

## API

`GET /api/v1/portal/catalogues` and `GET /api/v1/portal/orders` require a portal session. A staff API token is not enough. They were not called over HTTP. `tests/v11_phase6.php` covers the same ownership rules in process.

Password reset email and a second factor are not added here. Do not build a custom one-time-password store for this portal.
