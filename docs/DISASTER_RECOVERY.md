# Disaster recovery

Practical targets from the v1.0.0 drill, not a contract:

- Recovery point: the last successful off-server backup. If backups run nightly and the in-app backup also runs daily, expect to lose up to one day of entries made after that copy. A host snapshot can shorten that. Sign-Forge does not promise a five-minute point.
- Recovery time: on the v1.0 drill, a 7.5 MB database came back in under two seconds plus file copy. On 2 October 2026 a 98 MB v1.1 dump imported in about 9 seconds and the row counts matched. A larger company database and a full `storage/` copy will take longer. Plan for a few hours of host, DNS, and file restore, not a guarantee of minutes.
- The 2 October dump was restored into a side database on the same server, checked, and dropped. A copy on the same disk is not off-server recovery. Keep an encrypted copy off the server.
- Responsible role: the administrator who holds the database password, the `config.local.php` values, and the DNS login. Sign-Forge does not store that person’s name in the application.
- Xneelo: create the empty database in the panel, import the dump, set the cron command, and point the domain at `public/` with HTTPS. The application user often cannot create a second database. The panel, or an administrator account, does that.

## Server failure

Put the code and `storage/` on a new host, create the database, restore the dump, set `config.local.php`, point DNS, and confirm HTTPS. Run the checks in `docs/RESTORE.md`.

## Database corruption

Stop writes with maintenance mode. Restore the last good dump into a new database. Do not keep writing to the damaged one.

## Accidental deletion

Business records are archived, cancelled, or reversed. If a row was removed outside the application, restore that table from backup into a side database and copy the missing rows back with care for numbering and foreign keys.

## Compromised credentials

Change the database password, the administrator passwords, SMTP and integration secrets, and API client secrets. Revoke mobile devices under Mobile and devices. Sign every staff user out by resetting their password, which forces a new login. Read `login_events` and the audit log.

## Failed deployment

Follow `docs/ROLLBACK.md`. Restore the database only if the new migration cannot be kept.

## Lost phone

Revoke the device. The next sync is refused and the phone clears its local database when it reaches the server. Until then the phone still holds what it downloaded. Change the user’s password if the phone was unlocked.

## Integration compromise

Disable the connector, rotate its secret, and read `integration` issues. Issued invoices and stock movements already saved stay valid. A failed provider does not roll those back.

## Incident notes

Record what happened, what data was involved, who was told, and what was restored. Technical controls are not a POPIA certification.
