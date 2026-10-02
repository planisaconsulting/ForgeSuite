# User guide

Sign-Forge ERP v1.1.0 is the desk for sales, workshop, installation, and accounts. Prices on an accepted quote stay as they were accepted. Accepting a quote does not release it to production. Stock changes only through a movement. A payment does not disappear when it is reversed; the reversal is a new record.

## How to create a customer

Open Customers, choose business or individual, and save the name, phone, and address you will actually use. Add a contact if someone else approves quotes.

## How to create a quote

Open the customer or the opportunity, start a quote, and add lines. The server calculates area, VAT, and totals. Send it when the lines are right. A revision keeps the previous total under the same quote number.

## How to turn a quote into a job

The customer accepts the quote. Convert that acceptance. The job keeps the accepted prices. A declined quote, with a lost reason, does not create a job.

## How to issue material

Open the workshop scan while the tablet is online. Scan the roll, sheet, or item the job expects. Confirm the issue. A second submit of the same issue does not consume the material twice. If the network is down, the tablet can show a downloaded job card. It does not change the stock balance.

## How to complete an installation

On the phone, open My work, download the field pack, and check the approved artwork revision. Travel, arrive, complete the checklist, add photos, and capture the signature. If you are offline, the signature waits until sync. The office does not treat it as signed until that sync succeeds.

## How to run a multi-site project

Open Projects and create a project for the customer, or start one from the customer page. Add sites one by one or paste a spreadsheet on Import. A repeated site code stops the import so you do not get a partial list.

Create draft jobs from the sites when the package is agreed. Those jobs stay New until someone reviews them. They do not consume stock or raise invoices. Artwork, production, and installation still happen on each job.

The overview shows how many sites are complete, which are late, the next milestone, open snags, and, if you are allowed, commercial value, actual cost, gross profit, margin, invoiced, and cash collected. Moving the current target keeps the original date and stores the reason.

## How to record an installed sign

Open Assets after the job item is complete. If the product is marked to create a customer asset, the job can suggest one. Confirm it. A box of stickers does not become a list of assets. Twenty identical signs can be one asset with quantity 20, or twenty assets if you need to track each one.

The asset page shows the site, components, warranties, and service history. Print the label when the sign needs a QR code. Scanning it as staff opens the asset. Scanning it without a login opens a short report-a-problem page.

## How to log a service call

Open Service, or use Report a problem on the asset. Choose the problem and a priority. Normal is the usual choice. If a warranty date covers the day of the report, the request is marked as a candidate. It is not approved. From the request you can open a quote and a service job. Warranty work can be charged at zero and still record the parts, hours, and travel you used.

## How to estimate a manufactured sign

Open Advanced estimating. Choose vehicle wrap, channel letters, lightbox, pylon, or panel and frame. Pick an approved specification when one applies. Enter sizes in mm, cm, or m. What-if shows the quantities without saving. Calculate and save keeps a draft bill of materials.

Open How was this calculated on the result. Each row shows the input, the method, and the quantity. A schematic is an estimating sketch, not a fabrication drawing. Warnings name the problem, for example an open vector path or a material the specification excludes.

If the calculation asks for technical review, finish that review before adding it to a quote. The customer quote shows the description, size, quantity, and price. Cost, waste, and margin stay on the internal calculation. After the quote is accepted, the job Technical tab keeps the specification version that was accepted.

## How to release a job to production

Accepting a quote creates the job. It does not put the job on the workshop queue. Open the job, then Release. The checks name what is missing: artwork, size, specification, materials, route, files, date, or where the finished sign is going.

A warning, such as a material shortage, stays on the screen. A block stops the release. You cannot tick a missing artwork approval into a pass. Someone with release permission records the release. The number looks like `SFR-2026-0001`. The workshop pack shows that number, the artwork revision, and the time it was generated.

If the customer later changes a size or approves a new artwork revision, record a production change. The first release is kept. The new pack is the one to produce. A phone number change does not do this.

Released work is under Workshop → Released work. Today board is the large display. Unreleased jobs are counted there and are not listed as work to start.

One job can install some lines and collect others. Record fulfilment on the item. The job is not finished while a delivery, collection, or installation quantity is still outstanding.

## How to buy material

Open Purchasing → Workbench. A shortage is not a purchase order. Create an RFQ, invite the suppliers you choose, and wait for their prices. The comparison shows price, stock, and lead time. It does not pick the cheapest.

Award the quantities you want. Two suppliers can share one requirement. Each award creates a draft purchase order. Approve it and send it with the existing purchase-order screen. Stock increases only when you confirm the goods receipt.

If two sheets arrive damaged, quarantine them and return them. The good quantity is what production can use. A supplier link (`/supplier/…`) is for that supplier only.

## How a customer orders from the portal

Open the customer portal. Request a quote when the sign still needs measuring or artwork. Order signage only from a catalogue Sign-Forge has activated, and enter the customer purchase order when you have one.

Submit does not start production. The sales desk reviews the order. A requested date is not a promise. Reordering an old sign checks that the material, specification, artwork, and price are still valid. If they are not, the order waits for review instead of copying the old one.

To report a problem on an installed sign, open the asset and use Report a problem. That is the same service request the office already uses.

## How to approve artwork

Open the proof from the portal or from the proof link. The page says PROOF and is not the file the workshop prints. Place a comment on the proof, or approve that revision after you read the statement. Approving R3 does not approve a later revision. If the phone number changes, you will be asked again. A bleed correction may not ask you again. The workshop still uses a separate production file.

The artwork library lists artwork Sign-Forge has marked for your account. An older logo stays on file when a new primary logo is uploaded.

## How to record a payment

Open Payments, enter the amount and the date, and allocate it to invoices. If the payment is larger than the invoice, the invoice is paid and the rest stays as customer credit. It does not make the invoice balance negative.

Help in the user menu repeats the short version of these steps.
