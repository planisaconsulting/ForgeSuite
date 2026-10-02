# Expense approvals

Approval uses the expense category rules and the existing permission checks.

- The person who submitted the expense cannot approve it when the category requires separation. That includes an administrator.
- An amount above the category threshold needs `expenses.view_all` as well as `expenses.approve`. The seeded threshold is R500.
- A missing required receipt blocks approval.
- A cost that is already on a goods receipt, purchase order, contractor work order, logistics cost, or stock movement is not posted again.
- Rejection needs a reason. No job cost is written.
- Reversal needs `expenses.reverse`. It posts the opposite amount and leaves the original row.

Offline sync can save a draft expense and a receipt. `EXPENSE_APPROVE` is refused offline.

Approving an expense notifies through the existing notification table. It does not send a bank payment.

Audit actions include `EXPENSE_CREATED`, `EXPENSE_SUBMITTED`, `RECEIPT_ADDED`, `RECEIPT_EXTRACTED`, `RECEIPT_CONFIRMED`, `EXPENSE_APPROVED`, `EXPENSE_REJECTED`, `EXPENSE_REVERSED`, `REIMBURSEMENT_BATCH_CREATED`, and `REIMBURSEMENT_BATCH_EXPORTED`.
