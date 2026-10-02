# iCalendar feeds

A feed is a random token stored as a SHA-256 hash. The raw token is shown once. It is not a user id or a session id.

Scopes are my calendar, team, installations, deliveries, project, and company. Team and company need `calendar.view_company`. Detail is `BASIC` or `STANDARD`.

Basic text is the work type and the job number. It does not include quote value, cost, or margin. Standard can add the job title and the site address. It still omits money.

The file is a `VCALENDAR` with `VEVENT`, a stable `UID` such as `sf-installation-12@signforge.local`, `DTSTART`, `DTEND`, `SUMMARY`, and `STATUS`. Times use the company timezone, default `Africa/Johannesburg`, with a `VTIMEZONE` of SAST (+02:00). An event with no time is all-day. A cancelled installation is `STATUS:CANCELLED`.

The same UID is used after a date change, so a calendar client updates the event instead of adding a second one.

Revoking the token makes the next request return 403. The public address is `/calendar/feed/{token}`. It is rate limited with the API limit.

A customer download for an installation includes only that customer’s event and the same basic wording. Another customer’s id is refused.

`CalendarProviderInterface` is the extension point. The only provider is iCalendar.
