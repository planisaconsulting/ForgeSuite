# Customer pricing

Catalogue pricing modes are `STANDARD_CURRENT`, `CUSTOMER_PRICE_LEVEL`, `FIXED_AGREED_PRICE`, `FORMULA_BASED`, and `QUOTE_REQUIRED`. This phase prices an order from an active fixed agreed price.

## Versions

`customer_catalogue_prices` keeps a date range. Saving a later price closes the previous open row on the day before the new start. The old amount is not updated.

An order stores `unit_price_ex_vat`, VAT, and the line total at submission. A later catalogue price does not change that row.

## Expired prices

If no active row covers the order date, the order is refused with a quote. `expired_price_policy` is `QUOTE_REQUIRED`. The expired amount is not used.

## VAT

Ex VAT, VAT, and including VAT use `default_vat_percent` and the existing decimal helpers. The portal price array returns all three.

## Margin

`marginAlert` compares the agreed sell price with a current cost. Below `catalogue_margin_alert_percent` (default 15) it warns inside the business. The customer catalogue payload does not include cost or margin. The agreed price is not changed.

Example: sell R850, cost R790, margin about 7.06 percent. That is a warning. It is not a new customer price.
