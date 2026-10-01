# Troubleshooting

## Cannot log in

The message is the same for a wrong password and a disabled user. Wait one minute after five tries in the same browser. Twenty failures from one address in fifteen minutes also pause web logins. An administrator can set a new password from Users. The person must change it on the next sign-in.

## Email not sending

Check System health for the email mode. `off` and `log` do not deliver. A failed SMTP attempt leaves the quote or invoice as it was. Resend from the communication record. Do not mark it sent yourself in the database.

## PDF not generating

Dompdf must be in `vendor/`. A quote or invoice that opens in the browser but downloads an empty file usually means the storage folder is not writable, or the company logo path is missing. The totals on the PDF come from the saved document, not from a live recalculation of today’s prices.

## Upload failed

The file type or size was refused, or `storage/` is not writable. Photos over the field limit are rejected with a clear code. The original field photo is not overwritten by an annotation.

## Stock mismatch

Compare the stock movement ledger with the balance. Opening plus in minus out is the balance. Do not edit the balance column. A correction is a stock-count movement. Negative stock is refused unless that item’s policy explicitly allows it.

## Cron not running

System health shows the last start, status, and finish. If those stay empty, the host cron command is missing. The command is in `docs/CRON.md`.

## Offline data not syncing

Open Sync centre. A revoked device will not sync. A stale artwork pack blocks installation complete until you download the pack again. A signature stays “waiting to sync” until the server accepts it. Unsynced drafts are not deleted by cleanup.

## Integration failed

The invoice or stock movement already saved stays saved. Read the integration issue, fix the connector, and retry from that queue. Do not create a second payment for a webhook that already succeeded.

## PWA not updating

An update banner appears when the service worker changes. Refresh after sync. If drafts are waiting, the refresh is held back so the phone does not drop them.
