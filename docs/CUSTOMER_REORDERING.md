# Customer reordering

Reorder does not copy the old price, material, or artwork approval.

`assessReorder` returns one of:

- `READY_TO_ORDER` when the product is active, the specification and material are still current, the artwork is the approved revision, the customer is active, and the price is valid
- `REVIEW_REQUIRED` when the material or specification is no longer current, or the artwork is superseded
- `QUOTE_REQUIRED` when the agreed price is no longer valid
- `UNAVAILABLE` when the product or the customer is not active

If the customer asks to change the artwork, the previous approval is not reused. The normal artwork workflow still applies. This phase does not draw pin annotations on the proof.

The same check is used before a catalogue line is accepted at an old price. See `docs/CUSTOMER_PRICING.md`.
