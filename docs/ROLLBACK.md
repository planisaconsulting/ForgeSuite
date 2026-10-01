# Rollback

Application files can be replaced with the previous upload. Keep that copy off the server or in the host file history before you deploy.

Database changes are not automatically reversible.

- Migration 015 adds an index. Removing that index does not remove rows. The application still runs without it; login limiting by address is slower.
- Earlier migrations create tables and columns the current code expects. Restoring an older code tree onto a newer database can fail. Restoring an older database onto newer code can fail.
- If a migration was destructive, the recovery path is the backup, not a down script. v1.0.0 does not ship a destructive migration.

Failed deployment:

1. Turn maintenance mode on if the site still boots.
2. Put the previous application files back.
3. Read the migration error before you touch the database.
4. Restore the database only when the new migration changed data you cannot keep, or when the new code cannot run on the old schema and you are returning to the old code.
5. Sign in, open one customer, one quote, and one invoice, and compare a stock balance with the pre-deploy note.

Do not claim a one-click database rollback. The tested path is a full restore. See `docs/RESTORE.md`.
