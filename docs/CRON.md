# Cron

Run once an hour from the server, not from a browser:

```
php /path/to/signforge/cron/run.php
```

The `cron/` folder refuses HTTP.

The job records `last_cron_started_at`, then `last_cron_status` of `SUCCESS` or `FAILED`, `last_cron_at`, and the duration. System health shows the last time and status. A failure is also written to the application log. The error stored in settings is truncated and redacted.

The run sends alerts, follow-ups, scheduled report rows, webhook retries, approval reminders, integration retries, field-pack cleanup, internal warranty-expiry notices, and maintenance-due notices. It also counts released jobs past their target date and prints `production_overdue`. It counts ordered purchase orders past their expected date and prints `late_pos`. Those counts do not message the customer or the supplier. A maintenance plan can also open a draft service request when that plan allows it. The run does not invoice the customer for maintenance and does not email the customer because a warranty is expiring. It does not send customer email unless outbound automation is explicitly enabled. It does not post payments.

If System health still says the cron has not run after the schedule should have fired, the host job is missing or PHP cannot read `config.local.php`.
