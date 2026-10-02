# Logistics security

Customer tracking uses a random token. The database stores the SHA-256 hash. The link expires and can be revoked. An expired or revoked token returns nothing. The public page does not show courier cost, the courier account, internal notes, or another customer's shipment.

The customer deliveries page lists only that portal customer's shipments and omits courier cost.

Contractor queries filter on the signed-in contractor. A contractor cannot open another company's work order or an unlinked production file.

Dispatch confirmation, stock movement, contractor approval, cost approval, and job completion stay online. The field sync queue rejects `DISPATCH_CONFIRM`, `CONTRACTOR_APPROVE`, `COST_APPROVE`, and `JOB_COMPLETE`. Checklist and signature drafts reuse the existing sync operation id, so a replay does not store a second signature.

A courier webhook is refused when the shared secret does not match. A repeated external event id does not create a second tracking row.

Package labels and installation packs do not include selling price, margin, or internal notes. Notes are stored as entered and escaped when shown.

These checks run in `tests/v11_phase8.php`. The API routes were not called over HTTP.
