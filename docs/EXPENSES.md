# Expenses

An expense is an operational cost record. It is not a payment, a wage, or a general-ledger entry.

Numbers are `SFEXP-YYYY-####`. Categories are configurable. The seeded set is materials, tools, consumables, parking, tolls, fuel, travel, accommodation, meals, courier, subcontract, site cost, vehicle, office, and other.

Each category can require a receipt, require approval, set an amount that needs a manager, block self-approval, and say whether a personal payment is reimbursable. VAT on a receipt stays `REVIEW` until a person confirms it. The system does not decide that the VAT is recoverable.

Payment method is operational only: personal card, company card, cash, EFT, account, or other. A company card is not reimbursable. Cash and a personal card can be, when the category says so.

Status is `DRAFT`, `SUBMITTED`, `REVIEW_REQUIRED`, `APPROVED`, `REJECTED`, or `CANCELLED`. Reimbursement status is separate: `NOT_APPLICABLE`, `NOT_SUBMITTED`, `PENDING`, `READY_FOR_EXPORT`, `EXPORTED`, or `REIMBURSED_EXTERNALLY`. The last of those is a note that an outside process reported payment. This module does not pay it.

Allocate a cost to a job, a job item, a project, a project site, a service job, an asset, an installation, a site survey, or general operations. A split must add up to the expense amount. Percentage and equal splits show the calculation. The last share takes the remaining cents.

An approved job allocation posts one `job_other_costs` row and refreshes job actual cost. A project-only allocation posts `project_direct_costs`. A job allocation is not also posted as a project direct cost, because project actual cost already includes the job. General operations are not attached to a customer asset.

If the expense is marked as already represented by a goods receipt, purchase order, contractor work order, logistics cost, or stock movement, approval does not post it again. The record stays for review.

A rejected expense posts nothing and stays in history. An approved expense is not edited in place. Reversal adds a negative cost row and keeps the original.

Receipt text can propose a merchant, date, total, and VAT. Those values stay proposed until a person confirms them. The proposal does not approve the expense or post a ledger line.

A matching receipt hash, or the same person, amount, date, and merchant, is flagged. Nothing is deleted.

Reimbursement batches are `SFREB-YYYY-####`. Export is a CSV for accounts. Employee bank details are not stored here.

See `docs/EXPENSE_APPROVALS.md` and `docs/MILEAGE_TRIPS.md`.
