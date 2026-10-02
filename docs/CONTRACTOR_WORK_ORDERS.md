# Contractor work orders

A work order number is `SFCWO-YYYY-####`. Types include installation, fabrication, electrical, printing, painting, crane, transport, repair, and site survey.

Statuses run from draft and sent through accepted or declined, in progress, blocked, submitted complete, review required, and approved complete. Decline does not cancel the job. Submitted complete is not approved complete. Approval does not pay the contractor, issue a customer invoice, or close the job.

The agreed cost is copied onto the work order. A later change to the contractor rate does not rewrite it. Approving an actual cost posts one `job_other_costs` row with reference `CWO-` plus the work order number. Approving again does not post a second row. The variance is actual minus agreed.

A contractor invoice file is an operational record. It does not post accounts payable.

Material that Sign-Forge supplies is transferred to a stock location of type `EXTERNAL_CONTRACTOR`. A return is a transfer back. Consumption is a stock movement. Unused sheets do not disappear.

Outsourced output is received with accepted and rejected quantities and a QC status of pending, pass, or fail. Rework stores a reason, a cost, and a delay. It does not increase the production quantity by itself.

Service work can store a customer charge, an internal cost, and a contractor recovery as three amounts, and can point at the original work order.
