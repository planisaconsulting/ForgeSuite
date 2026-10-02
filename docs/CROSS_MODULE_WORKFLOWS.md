# Cross-module workflows

The operational path is lead, estimate, quote, job, artwork, production release, procurement, production, logistics or installation, invoice and payment, then asset or service. A project can group sites and jobs. It is not required.

Practical next steps that this phase adds:

- From a job, capture an expense or a trip instead of typing the same cost into a second ledger.
- From My work, open the expense, task, or installation that is waiting.
- From search, open the numbered record when the role may see it.

## Source of truth

| Topic | Record |
| --- | --- |
| Customer | `customers`. A merged source points at the surviving customer. |
| Price | Quote line snapshot. Estimating does not rewrite it. |
| Inventory | Stock movements. An expense is not a goods receipt. |
| Artwork approval | The approved revision and production file. A superseded file is not current. |
| Production release | `production_releases`. |
| Job cost | Material usage, time, and `job_other_costs`, including one row per approved expense allocation or trip share. |
| Invoice balance | Invoice and payment allocation. An expense is not a payment. |
| Project progress | Project sites, milestones, and job status. |
| Asset | `customer_assets`, reached from the customer or the service job. |
| Supplier price | Approved supplier or contract price. |
| Expense | `expenses`, with allocations and cost postings. |

## Status words

Use the module status. Do not rename a domain status just to share a word.

- Expense: draft, submitted, review required, approved, rejected, cancelled.
- Reimbursement: not applicable, not submitted, pending, exported, reimbursed externally.
- Installation and shipment keep their own statuses. Delivered is not installed. Installed is not job complete.
- Quote draft, sent, accepted, and converted stay on the quote. Intake cannot set sent or accepted.

## Dates

Requested date is what the customer asked for. Required date is the operational due date. Promised date is a commitment a person has made. Scheduled date is the planned start. Actual date is when the work happened. The calendar shows the scheduled or due date from the owning record.

## Money and stock words

Commercial value and quoted value are not cash. Invoiced, paid, and outstanding stay on the invoice. Estimated cost, committed cost, and actual cost are different totals. Gross profit is quoted or commercial value minus actual cost. Margin is that profit divided by the value. Markup is not that ratio.

On hand, reserved, available, incoming, required, and shortage stay on inventory. An expense does not change them.

## Customer-facing words

Portal and iCalendar text use the job number and the work type. They do not show cost, margin, or internal notes.

## What this phase does not replace

Payroll, a general ledger, bank feeds, automatic reimbursement, employee monitoring, and two-way calendar sync are out of scope. Accounting export remains a file for an external system.
