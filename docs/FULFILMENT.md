# Fulfilment

Fulfilment is per job item, not only per job. One job can install two pylons and collect fifty boards.

## Types and status

Types are `INSTALLATION`, `DELIVERY`, `COLLECTION`, `COURIER`, `DIGITAL`, `NO_FULFILMENT`, and `OTHER`.

Status is `NOT_READY`, `READY`, `SCHEDULED`, `PARTIALLY_FULFILLED`, `FULFILLED`, `FAILED`, or `CANCELLED`.

Packing status is `NOT_REQUIRED`, `REQUIRED`, `IN_PROGRESS`, or `PACKED`. Existing package records are not duplicated by this table.

Each row stores quantity, fulfilled quantity, an optional project site, address, contact name, required date, scheduled date, instructions, and, for collection, who collected it and when. Courier rows can store the existing carrier and waybill fields. There is no carrier API.

## Quantity

Fulfilled quantity on a line cannot pass that line’s quantity. Across every fulfilment line for one job item, fulfilled quantity cannot pass `job_items.good_quantity`.

Produced good quantity of 80 cannot be fulfilled as 100. Collecting 60 of 100 leaves the line `PARTIALLY_FULFILLED` with 40 still outstanding. The job is not complete while any fulfilment row is open.

If the job has no fulfilment rows, completion follows the previous job rules.

## Installation, delivery, and collection

An `INSTALLATION` row is the requirement. Scheduling the visit still uses the existing installation records.

`DELIVERY` uses the existing dispatch and delivery records. The fulfilment row is the quantity and destination.

`COLLECTION` records the person and the time when the quantity is fulfilled.

A project can send quantities to more than one site. Each row keeps its own quantity, destination, and status.

Changing delivery to installation after release is a production change with field `FULFILMENT_TYPE`. That requires a new release review. It does not silently change the released pack.

## Assets

Creating an asset from a job item is refused while that item has a fulfilment row that is not `FULFILLED` or `CANCELLED`. Do not create the asset before the installation or collection that the fulfilment row requires.

## Events

Partial fulfilment emits `FULFILMENT_READY`. A line that reaches its quantity emits `FULFILMENT_COMPLETED`. Neither event sends a customer message.
