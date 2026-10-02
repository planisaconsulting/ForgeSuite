# Serial and lot tracking

`products.tracking_mode` is `NONE`, `SERIAL`, `LOT`, or `BATCH`. The mode is stored on the product. Receipt still records a serial or a lot only when the receiving form sends one.

## Serials

`inventory_serials.serial_number` is unique. The manufacturer serial is a separate column. Receiving the same internal serial again is refused before a second goods receipt is posted. A database unique key remains if two receipts race.

## Lots

`inventory_lots` stores the lot or batch code, the goods receipt, quantity received, and quantity remaining. `inventory_lot_uses` records a job or a customer asset and a quantity. Remaining quantity does not go below zero.

A lot trace lists the receipt and the use rows in both directions: from the lot to jobs and assets, and from a use row back to the lot. It is an internal list. It does not notify customers or create a regulatory recall.

The Phase 5 test records uses against job ids and asset ids supplied by the test. It does not create those jobs and assets.
