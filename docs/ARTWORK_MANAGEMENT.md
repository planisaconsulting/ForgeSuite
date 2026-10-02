# Artwork management

Artwork stays on `job_artworks`. A proof, a source file, and a production file are different records.

Numbers are `SFAW-YYYY-####` from `NumberingService` and the `artwork_prefix` setting. Older proofs that were uploaded before this phase keep their job artwork row and do not receive a number until a new artwork is created in the workspace.

An artwork can point at a job, a job item, a quote, a project, a project site, a catalogue item, an installed asset, or a customer order. A project master is `master_artwork_id`. Site artwork is a separate row. Approving one site does not approve another.

## Status

`DRAFT`, `IN_DESIGN`, `INTERNAL_REVIEW`, `CUSTOMER_REVIEW`, `CHANGES_REQUESTED`, `APPROVED`, `PRODUCTION_PREP`, `APPROVED_FOR_PRODUCTION`, `SUPERSEDED`, `ARCHIVED`.

The older portal values `SENT_FOR_APPROVAL` still work on proofs that were sent before this phase.

## Revisions

`artwork_revisions` stores `R1`, `R2`, `R3`. The reason is `INITIAL`, `CUSTOMER_CHANGE`, `INTERNAL_CORRECTION`, `SITE_MEASUREMENT`, `PRODUCTION_CORRECTION`, `COMMERCIAL_VARIATION`, `REORDER_UPDATE`, or `OTHER`.

The change class is `CUSTOMER_VISIBLE`, `PRODUCTION_ONLY`, or `ADMINISTRATIVE`.

A customer-visible change clears the current customer approval. The earlier approval row stays. A production-only change, such as bleed, keeps the customer approval when `artwork_production_only_reapproval` is `NO`. The production file still needs its own approval.

Once a revision is `SENT`, `APPROVED`, or `SUPERSEDED`, a replacement file is refused. Upload it as a new revision.

Two revisions taken one after another receive different numbers because the artwork row is locked. The test does not start two PHP processes.

## Workspace

`/artwork` is the design queue. `/artwork/{id}` has Overview, Revisions, Proofing, Files, Production files, Approvals, and Activity.

Dates are separate: internal design due, customer requested date, and production required date. Priority is `NORMAL`, `HIGH`, or `URGENT`, and a change is audited.

A block reason can be `MISSING_LOGO`, `LOW_RESOLUTION`, `MISSING_INFORMATION`, `WAITING_CUSTOMER`, `WAITING_MEASUREMENTS`, `WAITING_BRAND_GUIDE`, `TECHNICAL_QUERY`, or `OTHER`.

Checkout records who is editing. It is not source control.

## Library and brand

The customer library is `customer_artwork_library`, with categories such as `LOGO`, `APPROVED_SIGN`, and `VEHICLE_BRANDING`. Reuse is `REUSABLE`, `ONE_TIME`, `SITE_SPECIFIC`, `VEHICLE_SPECIFIC`, or `EXPIRED`.

A new primary logo versions `customer_brand_assets` and marks the previous row `SUPERSEDED`. It does not rewrite the artwork. The workspace shows: “Brand asset has changed since this artwork was approved.”

A reorder uses `CustomerHubService::assessReorder`. Approved artwork that has not changed is reused. Superseded artwork is not.

## Sign kind

`sign_kind` can record a vehicle, channel-letter, or lightbox job, and `vehicle_template_id` can point at the Phase 3 template. This does not run the estimator and it does not approve each vehicle view as a separate gate. Finished size, scale (`1:1`, `1:2`, `1:5`, `1:10`, `CUSTOM`), bleed, safe area, cut contour, and tiling notes are stored on the artwork. They do not replace RIP software.

Site variables (branch name, phone, address, hours, manager, site code) are stored as JSON. There is no variable-data print engine.

## Queue counts

The project count is the number of artwork rows in each status. It is not a score of the designer.
