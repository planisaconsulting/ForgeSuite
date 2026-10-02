# Receiving

Goods receipt numbers stay `SFGRN-YYYY-####`. A purchase order can be received only when it is `ORDERED` or `PARTIALLY_RECEIVED`.

## Confirm

`ReceivingControlService::confirm` posts stock through the existing receive path. The receipt status is `CONFIRMED`. A draft on the phone does not post stock. `/m/receiving` collects the purchase order, quantity, and location, then opens the purchasing screen. It does not confirm the receipt by itself.

Send `idempotency_key` when a phone might retry. The same key returns the original receipt and does not post the quantity again. `goods_receipts.idempotency_key` is unique. Several receipts may omit the key.

## Partial, over, and under

Receiving 60 of 100 leaves 40 outstanding and the order `PARTIALLY_RECEIVED`. The order is not complete.

Receiving more than the outstanding quantity is refused unless `over_delivery` is `ACCEPT` or `ACCEPT_PARTIAL`. `ACCEPT` takes the extra. `ACCEPT_PARTIAL` caps the receipt at the outstanding quantity. There is no silent increase.

Under-delivery records the counted quantity. The rest stays outstanding.

## Damage and quarantine

`damaged_quantity` with a quarantine location transfers that quantity to the quarantine location and writes a `receiving_exceptions` row of type `DAMAGED` and status `QUARANTINED`. Other exception types are `WRONG_PRODUCT`, `WRONG_QUANTITY`, `WRONG_COLOUR`, `WRONG_SIZE`, `QUALITY_FAILURE`, `MISSING_DOCUMENT`, and `OTHER`. Statuses are `OPEN`, `ACCEPTED_WITH_NOTE`, `QUARANTINED`, `RETURN_TO_SUPPLIER`, and `RESOLVED`.

Global available stock excludes quarantine locations. The 8 good sheets stay at the receiving location. The 2 damaged sheets do not.

## Supplier returns

Return numbers are `SFRTN-YYYY-####` (`supplier_return_prefix`). `SFSR` is already used for service requests.

A return posts `SUPPLIER_RETURN`. The original receipt movement stays. The return can store a credit reference and a credit value. That is not an accounts-payable document.

## Stock counts

A count snapshots expected quantity at one location. Enter the physical quantity, then approve. The difference is a `STOCK_COUNT_CORRECTION` movement. Expected 20 and physical 18 posts `-2.0000`. Older movements stay.

A correction that would take the location below zero is refused unless someone with `inventory.override` records a reason on a stock adjustment. A normal shortage inside the on-hand quantity does not need that override.

Blind counts and cycle-count selection are not on a screen in this release. Approval stays online.
