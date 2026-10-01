# Installation

Sign-Forge ERP v1.0.0 runs on PHP 8.2+ and MySQL 8 or MariaDB 10.4+. The document root is the `public` folder.

1. Create an empty database and a user that can use only that database.
2. Copy `app/config/config.example.php` to `app/config/config.local.php`, or set the `SF_*` variables in `.env.example`.
3. Set `app.env` to `production` and `app.debug` to false on a company server. Local development may use `local` and debug on.
4. Import `database/schema.sql`, then `database/seed.sql`. That creates roles, permissions, and settings. It does not create fake customers.
5. Point the web server at `public/`. Confirm `https://your-domain/login` loads.
6. Sign in with the administrator created by the seed, then change that password.
7. Set the company name, VAT number, numbering prefixes, and logo in settings.
8. Add the hourly cron from `docs/CRON.md`.
9. Create the first backup and follow `docs/RESTORE.md` on a copy before go-live.

`database/demo.sql` is optional and only for a training copy. Do not import it into the company database.

Opening stock is a stock movement of type `OPENING_BALANCE`, recorded from the inventory screen or a controlled import. Do not type a quantity into a balance column.
