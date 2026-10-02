# Backup

A database dump is not a full recovery.

Take all of these:

- Database: `mysqldump --single-transaction`, or Administration → Backups inside Sign-Forge, which writes a SQL file under `storage/backups/`. That folder is denied to the web.
- Application files: the code tree, excluding `storage/` runtime data if you back that up separately.
- Uploaded files: `storage/uploads` (including `storage/uploads/artwork`), `storage/documents`, `storage/signatures`, `storage/quotes`, and `storage/field-photos`. Artwork proofs, production files, proof-of-delivery photos, and contractor photos are in that tree. A database dump without those files loses the bytes the shipment, signature, and work-order rows point at. The dump itself must include shipment records, proof of delivery, signatures, contractor work, tracking history, and logistics exceptions.
- Configuration: `config.local.php` and mail or integration secrets. Store those in a password manager, not in the database dump folder on the same disk.

Copy each backup off the server. The host panel backup is a second copy, not the only copy. Sign-Forge does not send backups to a named cloud vendor.

Suggested retention, already used by the in-app prune when a newer success exists: 7 daily, 4 weekly, 6 monthly. Change `backup_keep_daily`, `backup_keep_weekly`, and `backup_keep_monthly` if the business needs longer.

Encrypt the off-server copy if it leaves your control. You keep the key. A backup you cannot decrypt is not a backup.

Do not treat a phone’s offline field pack as a backup. The server is the record after sync.

The in-app backup is validated only after a restore. See `docs/RESTORE.md` and the restore note in `docs/V1_RELEASE_REPORT.md`.
