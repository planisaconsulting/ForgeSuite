# Production change management

A released job is not edited as if it were still a draft. `ProductionChangeImpactService` classifies the field that changed.

| Impact | Fields |
| --- | --- |
| NO_PRODUCTION_IMPACT | `INTERNAL_NOTE`, `SALES_CONTACT`, `PHONE`, `CUSTOMER_PHONE`, `SALESPERSON_NOTE` |
| RE_RELEASE_REQUIRED | `QUANTITY`, `DIMENSIONS`, `WIDTH_MM`, `HEIGHT_MM`, `ARTWORK_REVISION`, `SPECIFICATION`, `BOM`, `FULFILMENT`, `FULFILMENT_TYPE`, `MATERIAL` |
| REVIEW_REQUIRED | any other field name |

A phone number or an internal sales note does not change the release status.

A dimension change, a new artwork revision, a specification change, a bill of materials change, or a fulfilment-type change marks the current `RELEASED` row `REVIEW_REQUIRED` and sets preparation to `RELEASE_BLOCKED`. The old release row stays. Workshop scanning of that release number shows `SUPERSEDED RELEASE — DO NOT PRODUCE` only after a newer release has superseded it. Until then the status is review required, and the pack for that release is not the authority to produce against a newer revision.

The next release uses `new_version`. It becomes R2 (or the next version), copies a new snapshot, and marks the previous released and review-required rows `SUPERSEDED`.

## Change record

`production_changes` stores the source, reason, requested change, and impact.

Source is `CUSTOMER`, `DESIGN`, `PRODUCTION`, `SITE`, `SUPPLIER`, `MANAGEMENT`, or `OTHER`.

Artwork, material, schedule, and cost notes can be stored on the same row. This release records artwork impact when the field is `ARTWORK_REVISION`, and material impact when the field is `BOM` or `MATERIAL`.

A customer change that alters commercial scope should use the existing quote variation. `production_changes.variation_id` can point at that variation. This release does not create the variation for you. Changing a dimension here does not reprice the accepted quote.

## Who can record a change

`production.change.request` or `production.release.approve`. The audit log records the release, the override, and the production-critical change. The business event is `PRODUCTION_CHANGE_REQUESTED`. A re-release requirement also emits `PRODUCTION_RELEASE_BLOCKED`.

## What is locked

The release screen tells the user that a released job needs a production change before a critical field is treated as current. Direct SQL and the older job edit screens are not all intercepted. Record the change in Production change so the release status moves to review.
