# Source of truth

One record owns each fact. Other screens read it. They do not keep a second copy that can drift.

| Fact | Authoritative record |
| --- | --- |
| Customer | `customers` |
| Contact | `customer_contacts` |
| Site | Project site on the project. There is no second customer-site address book. An asset stores the project site plus a location description. |
| Product | `products`. Cost history is a new row, not an overwrite of the old cost. |
| Selling price | Calculated by `PricingService` from the product cost and the pricing level. A saved quote line keeps the snapshot from that save. |
| Recipe | The recipe version used when the quote or job was built. A later recipe edit does not rewrite it. |
| Specification | The specification version stored on the quote, job, and asset that used it. |
| Estimate | The estimate revision and its snapshot. |
| Quote | The quote revision. Acceptance and totals are the saved document, not today’s price list. |
| Artwork | The artwork revision. Approval names that revision. A later revision does not keep the old approval. |
| Production file | The current approved production file. A superseded file is not the file to produce. |
| Job | `jobs`. Actual cost is refreshed by `JobCostingService` from material, labour, and other-cost rows. |
| Production release | The release snapshot. A later dimension or artwork change keeps the old release and asks for a new one. |
| Inventory | The signed `stock_movements` ledger. There is no `products.stock_quantity`. |
| Supplier price | `supplier_products` and the contract price. A purchase order stores the price it was given. |
| Purchase order | The purchase-order document. It is not stock. A confirmed goods receipt is what adds quantity. |
| Project | Project commercial value from accepted project lines and approved changes. Job actual cost is included through the job totals. It is not added again as a direct project cost. |
| Invoice balance | Invoice total less recorded payment allocations and issued or applied credit notes. Draft and cancelled invoices are not open debt. |
| Payment | `payments` and `payment_allocations`. Cash collected is not revenue. |
| Asset | `customer_assets`, created when a person confirms an eligible installed item. |
| Service | The service request plus the service job. Warranty work can be zero to the customer and still carry internal cost. |
| Shipment | `shipments` and fulfilment quantities. Dispatched is not delivered. Delivered is not installed. |
| Expense | `expenses` and `expense_allocations`. An approved job expense posts one `job_other_costs` row. A second approval does not post again. |

## Calculations that stay in services

Pricing, VAT, margin, stock balance, job cost, project totals, and invoice balance are calculated in services. Controllers and views display the result. The browser preview of a price is the server’s answer.

## What this pass checked

- No `FLOAT` or `DOUBLE` columns in the working database.
- No `products.stock_quantity` column.
- Unique indexes on quote, job, invoice, purchase-order, project, asset, expense, and shipment numbers.
- Issued invoice `balance_due` matched allocations and credits. Mismatches: 0.
- No duplicate expense cost posting for one allocation.
- No duplicate logistics cost for the same source.
- Twenty concurrent quote number requests returned twenty different numbers. The same check, twenty at a time, passed for jobs, invoices, purchase orders, projects, assets, expenses, and RFQs.
- Empty database through migrations `001` to `025`: 402 tables, including `expenses`.
- Empty database through `schema.sql` and `seed.sql`, after the seed-line fix: 402 tables, 382 permissions, one administrator.

## Parallel logic left in place

Phase 8 field expenses remain a capture table. They are not the Phase 10 expense record and they are not reimbursed from that table. Job cost still lands in `job_other_costs`. That split is intentional. It was not folded into one table in this pass because merging it would be a new migration and a behaviour change.
