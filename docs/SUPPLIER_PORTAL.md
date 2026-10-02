# Supplier portal

The supplier portal is `/supplier/{token}`. It is not the customer portal and it is not a staff login.

The link carries a 64-character hex token. The database stores the SHA-256 hash. The raw token is returned once, when the invitation is created. A sequential id is not an access key.

## What a supplier can open

The token opens that invitation’s RFQ, that supplier’s quotations, and that supplier’s purchase orders. Another supplier’s quotation, internal comparison, margins, customer prices, unrelated jobs, and internal notes are not on this page.

An unknown token, a revoked invitation, and an expired invitation are refused. Opening an expired link marks the invitation `EXPIRED`.

## What a supplier can do

On an open RFQ the supplier can enter a unit price, available quantity, lead time, brand, product code, MOQ, pack size, delivery charge, and notes, and can mark a line as an alternative. They can upload a quotation PDF.

On their purchase order they can confirm a quantity and a delivery date, or request a change. A change request does not amend the purchase order. Sign-Forge reviews it.

Marking goods dispatched records a dispatch date and a delivery note. That is not a goods receipt and it does not increase stock.

## Files

Uploads are limited to 5 MB. Extensions `php`, `phtml`, `exe`, `sh`, `js`, `html`, and `htm` are refused. A file named `.pdf` must start with `%PDF`. The stored name is random and lives under `storage/supplier`.

The response form uses large fields so it can be completed on a phone. The token is the credential, so the form does not use the staff CSRF cookie.
