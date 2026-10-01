# API

The versioned API is `/api/v1/`.

Send `Authorization: Bearer {identifier}.{secret}`. Only the hash of the secret is stored. No credential returns 401. A credential without the scope returns 403. Responses use `success`, `data`, `errors`, and `meta`.

Browser field sync is `POST /api/v1/sync` with the staff session and `X-CSRF-Token`. It is not a bearer token stored in JavaScript. Each operation has an `operation_uuid`. A repeat does not create a second business row.

`GET /health` needs no login and returns `{"status":"ok"}` or `{"status":"degraded"}`.

Project reads use scope `projects.read`:

- `GET /api/v1/projects/{id}`
- `GET /api/v1/projects/{id}/sites`
- `GET /api/v1/projects/{id}/milestones`
- `GET /api/v1/projects/{id}/jobs`
- `GET /api/v1/projects/{id}/summary`
- `GET /api/v1/project-sites/{id}`

`summary` includes commercial value, cost, margin, invoiced, and cash collected only when the client also has `projects.financials`. The same rate limit and API log apply.

Asset and service reads use scope `assets.read` or `service.read`:

- `GET /api/v1/assets`
- `GET /api/v1/assets/{id}`
- `GET /api/v1/assets/{id}/components`
- `GET /api/v1/assets/{id}/warranties`
- `GET /api/v1/assets/{id}/service-history`
- `GET /api/v1/service-requests`
- `GET /api/v1/service-requests/{id}`
- `GET /api/v1/warranty-claims`
- `GET /api/v1/maintenance/due`

Original commercial value and original internal cost are included only when the client also has `assets.financials`. The asset token is generated on the server. The browser does not invent it.

Do not send `Access-Control-Allow-Origin: *` in front of these routes. The application does not.

Rate limits apply to public leads, portal login, staff login, and API authentication failures. A very large JSON body is rejected by PHP’s post limit before it is parsed into business records.
