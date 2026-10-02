# Artwork approvals

Approval is a row in `artwork_approvals`. It stores the artwork, the revision, the proof, the approver, the statement, the statement version, the time, the IP address, and the user agent when the browser sends one.

`customer_approved` on the artwork row is the current state only. It is not the history. When a later customer-visible revision is created, that flag is cleared and the old approval row remains.

The statement comes from `artwork_approval_statement`. The customer must tick it before the portal or a share link will approve.

## What approval does not do

Approving R3 does not approve R4.

Approving the proof does not approve the production file.

Approving one site in a rollout does not approve the other sites. There is no single click that approves every site variant.

A production-only revision does not ask the customer again while `artwork_production_only_reapproval` is `NO`. Production still has to approve the production file.

## Internal review

A designer can request internal review. A user with `artwork.approve_internal` can mark that review approved. That is not the customer’s approval, and it does not release the job.

The checklist on a revision is `DIMENSIONS`, `SPELLING`, `CONTACT_DETAILS`, `LOGO`, `COLOUR`, `BLEED`, `RESOLUTION`, `CUT_PATH`, and `SPECIFICATION`. A person confirms each item. The system does not mark them passed from a CDR file.

## Colour and physical samples

`colour_ack_required` records that screen colour is not the printed colour. It does not measure colour.

A physical sample (`VINYL_SWATCH` and the other kinds) uses `REQUIRED`, `PREPARED`, `CUSTOMER_REVIEW`, `APPROVED`, or `REJECTED`. While the artwork status is `REQUIRED`, `PREPARED`, or `CUSTOMER_REVIEW`, release readiness adds `PHYSICAL_SAMPLE` as `BLOCK`. Jobs that do not require a sample are `NOT_APPLICABLE` and are not blocked by this check.

## Evidence

The approval row is the evidence: revision label, proof id, statement, name, and time. A separate approval PDF is not generated. The proof file and the approval row are what a restore must keep together.
