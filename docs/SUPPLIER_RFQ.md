# Supplier RFQs

A purchase request, a request for quotation, a supplier quotation, and a purchase order are different records. Sending an RFQ does not create a purchase order. A quotation does not put stock on hand.

RFQ numbers are `SFRFQ-YYYY-####` from `NumberingService` (`rfq_prefix`). Supplier quotation numbers are `SFVQ-YYYY-####` (`supplier_quote_prefix`).

## Status

An RFQ moves through `DRAFT`, `READY`, `SENT`, `PARTIALLY_RESPONDED`, `RESPONDED`, `UNDER_REVIEW`, `AWARDED`, `CLOSED`, `CANCELLED`, and `EXPIRED`.

An invitation is `DRAFT`, `SENT`, `VIEWED`, `RESPONDED`, `DECLINED`, `EXPIRED`, or `REVOKED`.

A supplier quotation is `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `ACCEPTED`, `PARTIALLY_ACCEPTED`, `REJECTED`, `EXPIRED`, or `WITHDRAWN`.

## What an item keeps

Each RFQ line stores the product, description, specification, quantity, unit, preferred brand, whether an equivalent is allowed, and the required date. Source rows can point at a job, a project, a material requirement, a production release, a purchase request, or a manual entry. Compatible demand can sit on one line. Lines with different required dates, or different brand restrictions, are not merged.

## Award

Comparison lists supplier, product, brand, available quantity, unit price, line total, delivery, lead time, validity, MOQ, and the contract-price difference when an active contract exists. Landed cost on that screen is the line total plus the delivery amount stored on that quotation. Unknown charges are not invented.

The lowest price is not selected. A person chooses the supplier, the quantity, and a reason: `BEST_PRICE`, `BEST_AVAILABILITY`, `FASTEST_DELIVERY`, `CONTRACT_SUPPLIER`, `QUALITY`, `CUSTOMER_REQUIREMENT`, `SPLIT_SUPPLY`, or `OTHER`.

A split award is allowed. Each supplier receives one draft purchase order through the existing purchase-order service. The award does not send the order and does not mark it approved.

An alternative product is stored with `technical_status` `REVIEW_REQUIRED`. It cannot be awarded until someone accepts the alternative. If a job id is supplied, `ProductionChangeImpactService` records a material change. That can require a new release when the job is already released.

## Late responses

`rfq_late_response` is `FLAG` by default. A response after the deadline is stored and the note says it is late. Set the setting to `BLOCK` to refuse a late response. A late response is not deleted.

## Pack quantity

`suggestOrderQuantity` rounds the shortage up to the supplier pack and explains the rounding. Example: 13 sheets with a pack of 5 suggests 15. A person still confirms the order. If the purchase order is expected after the date the job needs the material, the requirement stays `LATE_FOR_REQUIREMENT`. Incoming stock does not clear that shortage.
