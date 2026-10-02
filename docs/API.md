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

Specification reads use scope `specifications.read`:

- `GET /api/v1/specifications` returns approved specifications (code, version, name, estimator type, status)
- `GET /api/v1/vehicle-templates` returns templates, including whether each one is verified

Estimator calculations use scope `estimators.use`:

- `POST /api/v1/estimators/vehicle-wrap`
- `POST /api/v1/estimators/channel-letter`
- `POST /api/v1/estimators/lightbox`
- `POST /api/v1/estimators/pylon`
- `POST /api/v1/estimators/panel-frame`

The body is JSON. The client must have `created_by` set to a staff user, because estimator permissions are that user's. The call calculates and does not save. Cost and installation detail are included only when the client also has `estimators.financials`. These routes were not exercised over HTTP in the Phase 3 test run. The same calculations are covered by `tests/v11_phase3.php`.

Production reads use scope `production.read`:

- `GET /api/v1/jobs/{id}/production-readiness`
- `GET /api/v1/jobs/{id}/production-releases`
- `GET /api/v1/production-releases/{id}`
- `GET /api/v1/production-queue`
- `GET /api/v1/fulfilment`
- `GET /api/v1/fulfilment/{id}`

Stage actions use scope `production.execute`:

- `POST /api/v1/production-stages/{id}/start`
- `POST /api/v1/production-stages/{id}/pause`
- `POST /api/v1/production-stages/{id}/complete`

The body is JSON. The acting user is `api_clients.created_by`. A repeated idempotency key does not start or complete the stage twice. The release GET does not return the snapshot, so cost inside that snapshot is not sent on this route. These routes were not exercised over HTTP. The same rules are covered by `tests/v11_phase4.php`.

Procurement reads use scope `procurement.read`:

- `GET /api/v1/procurement/rfqs`
- `GET /api/v1/procurement/rfqs/{id}`
- `GET /api/v1/procurement/rfqs/{id}/responses`
- `GET /api/v1/procurement/purchase-recommendations`

Responses are the internal comparison. They are not the supplier portal. The recommendations route returns the pack-size suggestion for a shortage of 13 and a pack of 5. Contract prices, locations, serials, lots, and receiving are not separate API routes in this release. These routes were not exercised over HTTP. The same RFQ, award, and receipt rules are covered by `tests/v11_phase5.php`. The supplier portal is `/supplier/{token}` and does not accept a staff API token.

Customer hub reads use the portal session, not a staff bearer token:

- `GET /api/v1/portal/catalogues`
- `GET /api/v1/portal/orders`

No portal session returns 401. The lists are limited to that portal user’s customer. These routes were not called over HTTP. `tests/v11_phase6.php` covers catalogue isolation, site restriction, and order ownership in process.

Artwork reads use scope `artwork.read`:

- `GET /api/v1/artwork`
- `GET /api/v1/artwork/{id}`

`GET /api/v1/portal/artwork` uses the portal session and returns that customer’s library. No portal session returns 401. These routes were not called over HTTP. `tests/v11_phase7.php` covers revision, proof, annotation, and production-file rules in process. Creating a revision or approving a proof is done in the workspace and the portal, which call `ArtworkProofingService`.

Do not send `Access-Control-Allow-Origin: *` in front of these routes. The application does not.

Rate limits apply to public leads, portal login, staff login, and API authentication failures. A very large JSON body is rejected by PHP’s post limit before it is parsed into business records.
