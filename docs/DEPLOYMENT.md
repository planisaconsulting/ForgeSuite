# Deployment

Use this order on Xneelo or any similar PHP host. Do not replace single files by hand during a release.

1. Back up the database and the `storage/` tree. See `docs/BACKUP.md`.
2. Turn on maintenance mode by setting `maintenance_mode` to `1`. An administrator who is already signed in can still open the site and turn it off. Everyone else sees a short holding page. `/login` and `/health` stay available.
3. Upload the release. `deploy/upload.py` sends the tree over SFTP and skips `config.local.php`, logs, backups, and captured photos. Production config stays on the server.
4. If `vendor/` is not already present, run `composer install --no-dev` on the server. The only required package is Dompdf.
5. Import new SQL files in `database/migrations/` that are not already applied. v1.0.0 adds `015_production_hardening.sql` on a database that already has 001–014. A database created from the current `schema.sql` already has that index. Do not run 015 again.
6. Confirm `storage/` is writable and not served as PHP.
7. Open `/health`. The body is `{"status":"ok"}` or `{"status":"degraded"}`. It does not list versions or paths.
8. Sign in, open a customer, a quote PDF, and system health.
9. Set `maintenance_mode` back to `0`.
10. Read the application log for new errors.

Production must use HTTPS. When `app.env` is `production`, a plain HTTP request on a public host is redirected. `127.0.0.1` and `localhost` are left alone so local PHP still works.

The release package excludes `.git`, `config.local.php`, logs, backups, temporary uploads, and field photos. Tests may remain on the server; the `tests/` folder is blocked by its own `.htaccess`.
