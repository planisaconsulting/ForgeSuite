# Production release

An accepted quote creates a job. It does not authorise manufacture.

Customer acceptance is a commercial event. Production release is an operational event. Starting a stage is a third event. Releasing a job does not consume stock.

## Preparation status

`jobs.preparation_status` is separate from the job status used by sales and installation.

| Status | Meaning |
| --- | --- |
| NOT_READY | The job exists and has not been released. New jobs start here, including jobs converted from an accepted quote. |
| PREPARING | Work is being prepared. The release screen does not set this by itself. |
| READY_FOR_RELEASE | Checks have no block. A person still has to release the job. |
| RELEASE_BLOCKED | A release was blocked, or a later change requires review. |
| RELEASED | A current release authorises production. Work may still be waiting for its schedule. |
| IN_PRODUCTION | A released stage has started. |
| PRODUCTION_COMPLETE | Required production stages are complete. Fulfilment can still be open. |

The older job status (`NEW`, `IN_PRODUCTION`, `COMPLETED`, and the rest) is unchanged.

## Release record

Each release is a row in `production_releases`. The number is `SFR-YYYY-####` from `NumberingService` and the `release_prefix` setting.

Status values are `DRAFT`, `REVIEW_REQUIRED`, `BLOCKED`, `READY`, `RELEASED`, `SUPERSEDED`, and `CANCELLED`.

A job can be released more than once. The first successful release is version 1. A later release is version 2. Version 1 is kept and marked `SUPERSEDED`. It is not rewritten.

The release stores `snapshot_json` at the moment of release: job items, quantities, dimensions, artwork revision facts, the technical snapshot (specification code and version), material requirements, the production stages copied onto the job, production files, fulfilment lines, target and promised dates, and the check results. A later specification version does not change that snapshot.

## Checks

`ReleaseReadinessService` evaluates these codes:

`JOB_VALID`, `CUSTOMER_CONFIRMED`, `SCOPE_CONFIRMED`, `QUANTITIES_CONFIRMED`, `DIMENSIONS_CONFIRMED`, `ARTWORK_APPROVED`, `ARTWORK_REVISION`, `SPECIFICATION_CONFIRMED`, `TECHNICAL_REVIEW`, `BOM_GENERATED`, `MATERIAL_REQUIREMENTS`, `MATERIAL_AVAILABILITY`, `SHORTAGES_IDENTIFIED`, `PRODUCTION_ROUTE`, `PRODUCTION_FILES`, `QC_CHECKLIST`, `TARGET_DATE`, `FULFILMENT_METHOD`, `LOCATION_CONFIRMED`, `SPECIAL_INSTRUCTIONS`.

Each result is `PASS`, `WARNING`, `BLOCK`, or `NOT_APPLICABLE`.

A check that does not apply is `NOT_APPLICABLE`. Example: artwork is not required, or no specification is linked, or no print/CNC/router stage needs a file.

A `BLOCK` stops release. A `WARNING` is shown and does not stop a single-job release. Bulk release skips warnings unless the caller asks to include them.

Missing customer artwork approval is a block on the default policy. A material shortage is a warning on the default policy and stays visible after release. An outstanding technical review (`sign_calculations.technical_review_required` with status `REVIEW`, or `engineering_review` on the job snapshot) is a block that cannot be overridden.

## Policy

Policies live in `production_release_policies` and `production_release_policy_checks`.

Resolution order: a specification code match, then a job type match, then `DEFAULT`. A specific policy overlays the default severities for the codes it defines.

| Policy | Applies to | Notable severity |
| --- | --- | --- |
| DEFAULT | every job that has no closer match | Artwork and technical review are `BLOCK_NO_OVERRIDE`. Material shortage, missing route, missing target, and missing location are warnings. |
| VEHICLE | job type `VEHICLE_WRAP` | Artwork and dimensions cannot be overridden. Material and fulfilment are warnings. |
| CHANNEL | job type `CHANNEL_LETTER` | Specification, artwork, and technical review cannot be overridden. Material shortage can be overridden with permission. `approval_required` is stored as 1. |

Severity is `BLOCK_NO_OVERRIDE`, `BLOCK_OVERRIDE_WITH_PERMISSION`, `WARNING`, or `INFORMATIONAL`.

An override is a row in `production_release_overrides`. It records the check, the original result `BLOCK`, the user, the reason, and the time. The check row stays `BLOCK`. A `BLOCK_NO_OVERRIDE` check cannot be overridden, even by someone with `production.release.override`.

## How to release

Open the job, then Release, or open `/production/jobs/{id}/release`. The screen shows the job, customer, preparation status, and each check.

Release to production needs `production.release.approve`. The server records the user and time. Leave the item blank to release the quantity still unreleased on every line. Enter an item id and quantity to release part of one line.

A second submit with the same idempotency key returns the same release and does not reserve stock again. Two requests without that key also return the existing `RELEASED` row unless `new_version` is set.

## Partial release and batches

`production_release_items` stores the quantity released for each job item. Released quantity is the sum of items on releases whose status is `RELEASED`.

Item release status is `NOT_RELEASED`, `PARTIALLY_RELEASED`, `RELEASED`, or `PRODUCTION_COMPLETE`.

Releasing 60 of 100 leaves 40 unreleased. A later release that would take the cumulative total above the job item quantity is refused. The message is that the release is more than the authorised quantity.

A batch is another release of a further quantity. There is no separate batch document.

## Bulk release

`ProductionReleaseService::bulk` evaluates a list of job ids. It reports ready, warning, and blocked jobs, and releases only the ready jobs. A blocked job is not released with the others. Warnings are not released unless `includeWarnings` is true.

There is no project-wave screen. Pass the job ids for the wave. The same method is what a rollout uses.

## Reservation

Optional reservation uses the existing `StockMovementService::reserve`. The reservation is linked with `stock_reservations.source_release_id`. On-hand quantity is the sum of stock movements and does not change. A repeated release key does not insert a second reservation.

Incoming purchase-order quantity is shown with its expected date. It is not treated as physically available.

## Production pack

`/production/releases/{id}/pack` renders the snapshot. It shows the release number and version, the artwork revision known at release, and the generated time. The page says it is internal and does not print customer debt, margin, markup, or supplier cost.

A superseded release shows `SUPERSEDED — DO NOT PRODUCE.`

`/production/scan/{number}` opens that release. A superseded number shows the same warning and the current released number.

## MRP

Firm demand is jobs whose preparation status is `RELEASED`, `IN_PRODUCTION`, or `PRODUCTION_COMPLETE`. Other open jobs are planned demand. Purchase recommendations merge both and tag `demand_class` so a job is counted once.
