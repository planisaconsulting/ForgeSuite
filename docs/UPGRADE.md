# Upgrade

From a Phase 14 database to v1.0.0:

1. Back up the database and files.
2. Record the current application version from System health.
3. Upload the v1.0.0 files. Leave `config.local.php` in place.
4. Import `database/migrations/015_production_hardening.sql` once. It only adds `idx_login_events_ip_created`. It does not rewrite customers, stock, or invoices.
5. If the import says the index already exists, stop. Do not run it again.
6. Confirm `/health` and a staff login.

Do not import `schema.sql` over a live database.

A fresh install uses `schema.sql` and `seed.sql` only. Do not also run migrations 001–015.

There is no automatic reverse for 015. Dropping the index is safe if you must go back, and it does not delete login history. See `docs/ROLLBACK.md`.
