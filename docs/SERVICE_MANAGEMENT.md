# Service management

A service request is `SFSR-YYYY-####`. Sources include phone, email, WhatsApp, the customer portal, the asset QR page, an inspection, and an internal note. Priority starts at normal unless someone chooses otherwise.

The request links the customer, the site, and the asset when there is one. If a warranty date covers the reported date, the request is marked a warranty candidate. No claim is opened and nothing is approved until a person does it.

## Jobs and quotes

Service work uses the existing job. The number stays `SFJ-YYYY-####`. `job_type` can be `STANDARD`, `SERVICE`, `WARRANTY`, `MAINTENANCE`, `INSPECTION`, `REPAIR`, `REMOVAL`, or `REPLACEMENT`. Existing jobs remain `STANDARD`.

Classification is paid, warranty, goodwill, internal, or maintenance contract. Goodwill needs a written reason and is not labelled warranty. If an approval policy exists for goodwill, the job waits. Urgency does not skip permissions, stock, or invoices.

A service quote is a normal quote linked to the request and the asset. Accepting it converts that quote into the service job. A warranty repair can have a customer charge of zero and still record material, labour, and travel. Revenue is the quote or the invoice. It is not copied from job cost.

A replacement part is consumed through the stock ledger on that service job. The same operation id does not consume twice. If the part replaces a component, the old component stays as `REPLACED` and the new one is `ACTIVE`.

## Reports and the field phone

The service report shows the request, job, asset, site, problem, work, parts, sign-off, recommendations, and the next service date. It omits internal cost unless the reader has a cost permission.

My service jobs on the phone shows the customer, site, asset, problem, warranty dates, and history. It does not show sales margin, supplier cost, customer debt, or project financials. An offline inspection syncs once. If the technician has lost `inspections.perform` before reconnecting, the server refuses the update.

Response hours are reported to first response and to resolution. A target is shown only when the customer’s service agreement stores `response_target_hours`. That target is the agreement’s figure. It is not called a contractual SLA unless the agreement says so.

Service agreements record included work and billing notes. They do not raise subscription invoices.
