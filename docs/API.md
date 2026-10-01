# API

The versioned API is `/api/v1/`.

Send `Authorization: Bearer {identifier}.{secret}`. Only the hash of the secret is stored. No credential returns 401. A credential without the scope returns 403. Responses use `success`, `data`, `errors`, and `meta`.

Browser field sync is `POST /api/v1/sync` with the staff session and `X-CSRF-Token`. It is not a bearer token stored in JavaScript. Each operation has an `operation_uuid`. A repeat does not create a second business row.

`GET /health` needs no login and returns `{"status":"ok"}` or `{"status":"degraded"}`.

Do not send `Access-Control-Allow-Origin: *` in front of these routes. The application does not.

Rate limits apply to public leads, portal login, staff login, and API authentication failures. A very large JSON body is rejected by PHP’s post limit before it is parsed into business records.
