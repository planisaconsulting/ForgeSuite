# Logistics

v1.1 Phase 8 adds shipments on top of the existing dispatch, package, and fulfilment records. It does not replace them.

A shipment number is `SFSHP-YYYY-####`. Types are courier, own delivery, customer collection, installation, third-party delivery, and other.

Dispatched, collected, delivered, and installed are different events. Booking a courier stores the waybill and does not mark the goods delivered. A failed delivery opens a logistics exception and does not complete the fulfilment line.

A shipment can hold part of a job. Fifty of one hundred can leave while forty stay outstanding. The quantity cannot exceed the QC-passed good quantity on the job item.

Packages keep the existing package code. The label shows the shipment, customer, destination, package sequence, handling note, and a Code 39 barcode. It does not show a price.

The logistics desk is `/logistics`. Reports are `/logistics/reports`.
