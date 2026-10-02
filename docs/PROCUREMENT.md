# Procurement

Purchasing → Workbench is the internal procurement desk. The cards are open purchase requests, open RFQs, invitations still sent, quotations submitted, late purchase orders, open or quarantined receiving exceptions, and open supplier returns.

On-time delivery, shown only to someone who can see quotations or purchase orders, is:

receipts on or before the purchase order expected date, divided by receipts that have an expected date and status `CONFIRMED`.

Those figures stay separate. There is no single supplier score.

## Contract prices

`supplier_contract_prices` keeps a date range. Saving a new active price expires the previous active row from the new start date. The old row is not overwritten.

When a quoted price differs from the active contract, comparison shows the difference and the percent. Example: contract R500, quote R550, difference R50. The quote is not rejected.

Supplier price history keeps a source of `RFQ`, `CONTRACT`, `PO`, `INVOICE`, or `MANUAL`. A short change is not labelled as permanent inflation.

## Purchase orders

Award creates draft orders with the existing `PurchasingService`. A line that came from a supplier quotation uses that unit price (`cost_source` `SUPPLIER_QUOTATION`). Other lines still use the supplier product price or the product cost.

`po_approval_amount` defaults to 50000. The amount is stored. Award and send do not call `ApprovalService` in this release. An authorised user still moves a draft through pending approval, approved, and ordered with the existing status path.

A supplier confirmation stores the confirmed quantity, date, and supplier reference. A change request is a separate row. Commercial terms on the order are not replaced by that request.

## Recommendations and consolidation

A recommendation can start from an MRP shortage, minimum stock, a released job, a project, or a manual request. The screen shows required quantity, available, reserved, incoming, net shortage, a suggested order quantity, and likely suppliers from supplier products, past purchases, and contract prices. Nothing is sent automatically.

Order quantity considers MOQ and pack size and shows the rounding. A person confirms it.

Demand with the same specification, required date, and brand restriction can be consolidated. The source quantities stay on the RFQ item. Incompatible dates or brands are refused.

## Project cost

A purchase order and a goods receipt do not add the order total onto `jobs.actual_material_cost`. Job material cost still comes from recorded material usage. Adding the purchase-order total on top of that would count the material twice.

## Cash purchases and invoice checks

`procurement_cash_purchases` records a local cash buy (bolts, silicone, cable) with a reason. It is not a supplier invoice and it does not post to a ledger.

`supplier_invoice_checks` can store a supplier invoice reference next to a purchase order and a goods receipt. It does not post accounts payable. There is no three-way match screen in this release.

## Events

Services emit `RFQ_CREATED`, `RFQ_SENT`, `RFQ_RESPONSE_RECEIVED`, `RFQ_AWARDED`, `PO_CONFIRMED`, `PO_CHANGE_REQUESTED`, `GOODS_RECEIVED`, `RECEIVING_EXCEPTION_CREATED`, `SUPPLIER_RETURN_CREATED`, and `STOCK_PUT_AWAY`. The hourly cron prints `late_pos`. It does not email the supplier.
