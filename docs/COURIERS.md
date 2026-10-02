# Couriers

A courier record stores the name, account reference, contact, an https tracking template, and an integration mode: `MANUAL`, `LINK_ONLY`, or `API`.

Sign-Forge works without a courier API. Manual mode records the waybill, tracking number, collection date, costs, and status that a person enters. `CourierProviderInterface` names `createShipment`, `getLabel`, `getTracking`, `cancelShipment`, and `getQuote` for a later provider. The manual provider does not call a network service and does not invent a carrier response.

The customer tracking link is built only from the courier template. `javascript:` and plain `http` templates are rejected.

Estimated courier cost, actual courier cost, and the customer delivery charge are three amounts. The difference is an internal figure. It is not on the customer tracking page.

A courier webhook is accepted only when `courier_webhook_secret` matches. The same external event id is stored once.
