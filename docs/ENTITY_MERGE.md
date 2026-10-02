# Customer merge

Merge is customer-specific. There is no generic tool that rewrites arbitrary tables from a form.

Preview shows both names, fields that differ, and how many quotes, jobs, invoices, contacts, assets, and related rows each customer has. Nothing is moved until a person with `entity_merge.execute` confirms.

Execute moves `customer_id` on the known customer tables onto the target and sets the source `active` to 0 with `merged_into_id` pointing at the target. The source row is not deleted. Invoice rows are moved, not copied, so the invoice total does not double.

If a unique key would be broken, the transaction stops and nothing is changed. A second catalogue or another one-per-customer row on both sides is that case.

The merge is written to `entity_merges` and to the audit log as `ENTITY_MERGED`.
