# Warehouse locations

Locations stay in `stock_locations`. Phase 5 adds `parent_id` and `location_type`. There is not a separate table for each physical level.

Types are `WAREHOUSE`, `WORKSHOP`, `VEHICLE`, `INSTALLATION`, `ZONE`, `AISLE`, `RACK`, `SHELF`, `BIN`, `QUARANTINE`, `OFFCUT`, and `OTHER`. A code is unique. A warehouse can be only a warehouse. A bin can hang under a shelf, a rack, a zone, or the warehouse. Example: main warehouse, then vinyl, then `WH1-VIN-A-02-04`.

Open Inventory → Locations to add a location. Put-away suggests a bin from `putaway_rules` when a rule exists for the product. Confirming put-away posts `TRANSFER_OUT` and `TRANSFER_IN`. Stock does not move until a person confirms.

A transfer scans from location, item, quantity, and to location, then confirms. Both movements stay on the ledger. A tracked roll, sheet, or offcut keeps `inventory_items.stock_location_id`.

Quarantine is a location type. Quantity at a quarantine location is left out of the global on-hand figure, so it is not available for normal production. Looking at that location still shows the quantity.

An offcut needs a physical place, for example `OFFCUT-ACM-B3`. Search by size still uses the offcut dimensions. The location is the place to collect it.

Location QR images are not drawn in this release. The location code is what receiving and transfer record.
