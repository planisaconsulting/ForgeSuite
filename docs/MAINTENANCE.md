# Maintenance

Asset maintenance plans are separate from workshop equipment downtime. Equipment downtime still uses the existing resource maintenance screen.

A maintenance plan has a name, an optional asset type, an interval in months and/or days, a checklist name, an optional recipe for a service kit, and whether a draft service request should be opened when the work is due. `auto_request` is off on the seeded plans.

An asset can have no plan, one plan, or several. The next date is the last completion plus the interval. Someone with `maintenance.manage` can override that date. The asset’s next service date is the earliest active plan.

Due states are upcoming, due within seven days, and overdue. The maintenance screen can look 30, 60, 90, or 180 days ahead.

The hourly cron notifies management when a plan is due. If the plan allows it, and there is not already an open maintenance request for that asset, a draft service request is created. The cron does not create an invoice and does not message the customer.

A service kit is an existing recipe. The plan can point at it. Nothing is reserved or purchased until a normal stock or purchasing step is used.

Completing the visit stores the completion date and moves the next date forward. Inspections copy their checklist onto the visit, so a later edit of the plan text does not rewrite the old report.
