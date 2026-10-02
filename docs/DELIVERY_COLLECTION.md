# Delivery and collection

Own delivery uses a run: vehicle, driver or team, date, and a manual stop order. There is no route optimiser. The manifest lists the stops, customers, addresses, packages, contacts, and the customer note.

Stop statuses are planned, en route, arrived, delivered, failed, and skipped. One stop can succeed while another fails. A failed stop uses a reason such as nobody available, wrong address, access denied, damaged, refused, or other. That stop does not complete the fulfilment.

Proof of delivery stores the recipient, a signature reference, the server time, an optional photo path, and an optional event location from that action. It does not track a person continuously.

Customer collection moves through ready, customer notified, awaiting collection, and collected. The handover stores who collected, a contact, an optional vehicle registration, and the server time. Staff verify the package by scanning its existing package code. A package from another job or another site is refused.
