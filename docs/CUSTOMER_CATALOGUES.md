# Customer catalogues

A catalogue belongs to one customer. Example: ABC Retail — approved signage. Another customer cannot open it.

Statuses are `DRAFT`, `ACTIVE`, `SUSPENDED`, `EXPIRED`, and `ARCHIVED`. A catalogue starts as a draft. A user with `customer_catalogues.manage` activates it. That is the internal approval step. There is no separate approval policy call.

## Items

An item can point at a product and a specification. It stores the customer’s own code, such as `ABC-SIGN-017`, and a separate internal code. Locked configuration (material, size, branding) is JSON. The customer is expected to fill only the allowed variables, such as a name, a title, or a site.

Availability shown to the customer is a label: `AVAILABLE`, `LEAD_TIME`, `MADE_TO_ORDER`, or `QUOTE_REQUIRED`. Exact warehouse quantities are not sent to the portal.

## Bundles and templates

`customer_catalogue_bundles` point at catalogue items. They do not copy products. Pricing can be the sum of the items or a fixed bundle price.

A portal user who can order can save an order template, such as a new-store pack. The template stores the item list. It does not place the order.

## Discontinued items

Set the item status away from `ACTIVE`. An inactive item cannot be ordered. Nothing is substituted in its place.
