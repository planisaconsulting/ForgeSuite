# Data quality

The existing data-quality list still points at customers without contact details, products without a supplier cost, jobs without a target date, invoices without a due date, opportunities without a next action, and negative stock. Those rows are not corrected automatically.

Phase 10 adds a stored check for a released job whose production file is superseded. The severity is `BLOCKING`. The row links to the job. Scanning again does not insert a second issue for the same file.

An expense that is submitted without a required receipt stays `REVIEW_REQUIRED` until the receipt is attached. That is an expense rule, not an automatic edit of the amount.

Severity is `INFO`, `WARNING`, or `BLOCKING`. The safe action is to open the record. Ambiguous business data is not rewritten.
