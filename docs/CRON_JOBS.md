# Cron jobs

Sign-Forge has one scheduled command. It is not a queue worker and it does not stay running.

| Command | Frequency | Purpose | Expected runtime | Failure |
| --- | --- | --- | --- | --- |
| `php /path/to/signforge/cron/run.php` | Hourly. On Xneelo, one PHP cron entry. | Alerts, lead follow-ups, overdue snags, schedule alerts, recurring follow-ups, scheduled report rows, outbound webhook retries, approval reminders, integration retries, field-pack cleanup, project milestone notices, internal warranty notices, maintenance-due notices, production and late-PO counts, temporary-file cleanup, and application-log rotation. | The 2 October 2026 run finished in well under a second and printed `production_overdue=0` and `late_pos=0`. A large due-webhook queue can take longer. | The process sets `last_cron_status` to `FAILED`, stores a redacted `last_cron_error`, writes the application log, prints `cron_failed`, and exits 1. It does not pretend the run succeeded. |

`cron/.htaccess` refuses HTTP. The command must be the host clock, not a browser.

System health shows the last success time, the status, the duration in seconds, the last failure text, and a next expected time one hour after the last start. That next time describes the hourly schedule. It does not start the job.

The run does not issue invoices, post payments, send supplier email, or email a customer because a warranty is expiring. Customer email stays off unless outbound automation is explicitly enabled.

Detail is also in `docs/CRON.md`.
