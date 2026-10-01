# Security

This describes the controls in v1.0.0. It is not a legal certification.

## Authentication

Staff and portal passwords use `password_hash()` and `password_verify()` (PHP default, currently bcrypt). They are not stored as MD5, SHA1, or plaintext. Workshop PINs are hashed the same way.

A failed staff login looks the same for an unknown email, a bad password, and a disabled account. Five failures in one browser session pause that browser for 60 seconds. Twenty failures from the same address in 15 minutes pause further web logins from that address. Command-line tests are not counted in the address limit.

There is no self-service reset email. An administrator sets a new password and the user must change it. That gap is listed in `docs/V1_1_BACKLOG.md`.

The session cookie is `SFSESSID`, HttpOnly, SameSite Lax, and Secure when the request is HTTPS. The session id is regenerated on login. Remember-me uses a separate cookie and does not store the password.

## Authorisation

Hiding a menu item is not the control. Routes carry a permission code. ADMIN is allowed through missing permission rows so an incomplete seed cannot lock the administrator out. Costs, margins, supplier prices, payments, credit notes, stock valuation, and cash forecasts use their own permission codes.

Portal queries use the customer id in the portal session. A numeric id in the URL is not enough.

API clients use `Authorization: Bearer`. The secret is stored as a hash. A missing credential is 401. A missing scope is 403. Browser sync uses the staff session and CSRF, not a long-lived token in JavaScript.

## CSRF, XSS, SQL

State-changing browser forms use a CSRF token. The sync API accepts the same token in `X-CSRF-Token`.

Pages escape text with `e()`. Offline notes are escaped again when the office opens them. Uploaded SVG is not rendered inline as HTML.

Queries use PDO prepared statements. Sort and filter identifiers are whitelisted in the services that build those clauses.

## Files

Uploads are checked for type and size, stored under `storage/` with generated names, and denied by `.htaccess`. Signatures and field photos are outside `public/`.

## Headers and errors

Responses send `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and a Content-Security-Policy that allows the existing local scripts and inline styles. `X-Powered-By` is removed. Production `display_errors` is off. An unexpected exception shows “Something went wrong” and a reference such as `ERR-1A2B3C4D`. The log line has that reference, the exception class, the user id, and the route. Password-like fragments are redacted. The stack is not shown unless debug is on.

`/health` returns only `ok` or `degraded`.

CORS is not opened with `Access-Control-Allow-Origin: *`.

## Privacy

Customer data is limited to people who have the module permission. Field packs omit cost and margin. Push titles do not include amounts. Logs must not contain passwords, session ids, or API secrets. This review does not by itself make the business POPIA-compliant. Purpose, retention, operator agreements, and incident duties stay with the company.
