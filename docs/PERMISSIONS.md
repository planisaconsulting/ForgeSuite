# Permissions

v1.1.0 has 365 permission codes. ADMIN holds all 323 that are granted in the seed and is also allowed through in code. The other roles, and how many codes they hold:

| Role | Codes |
| --- | --- |
| MANAGEMENT | 224 |
| SALES | 107 |
| ACCOUNTS | 77 |
| PRODUCTION | 57 |
| DESIGN | 40 |
| INSTALLER | 39 |
| DISPATCH | 29 |
| MARKETING | 11 |

Project codes are `projects.view`, `projects.view_assigned`, `projects.create`, `projects.edit`, `projects.archive`, `projects.complete`, `projects.view_financials`, `projects.manage_team`, `projects.manage_sites`, `projects.import_sites`, `projects.bulk_create_jobs`, `projects.manage_milestones`, `projects.manage_risks`, `projects.manage_issues`, `projects.manage_budget`, `projects.manage_changes`, `projects.generate_handover`, and `projects.view_reports`.

SALES can create projects and see commercial figures. ACCOUNTS can see project financials and reports. DESIGN, PRODUCTION, and INSTALLER only open projects they are assigned to, and they cannot open financials. MANAGEMENT receives the project codes. ADMIN is allowed through in code.

Asset and service codes are `assets.view`, `assets.create`, `assets.edit`, `assets.archive`, `assets.view_costs`, `assets.manage_components`, `assets.manage_warranties`, `assets.generate_labels`, `assets.import`, `service_requests.view`, `service_requests.create`, `service_requests.assign`, `service_requests.manage`, `service_jobs.manage`, `service.view_costs`, `warranty_claims.view`, `warranty_claims.manage`, `inspections.perform`, `inspections.manage`, and `service_reports.generate`. Asset maintenance plans reuse the existing `maintenance.manage` code. INSTALLER can see assets, see service requests, and record inspections. INSTALLER cannot see asset cost, service cost, supplier cost, or project financials. A portal login can open only assets for that customer account.

Signage codes are `specifications.view`, `specifications.create`, `specifications.edit`, `specifications.approve`, `specifications.archive`, `estimators.use`, `estimators.override`, `estimators.view_costs`, `estimators.technical_review`, `vehicle_templates.view`, `vehicle_templates.manage`, `geometry.upload`, and `electrical_profiles.manage`.

SALES can view specifications, run an approved estimator, and view vehicle templates. SALES cannot edit or approve a specification and cannot override a hard rule. DESIGN can create and edit specifications, run estimators, override a calculation, complete technical review, manage vehicle templates, and upload geometry. DESIGN cannot approve a specification. PRODUCTION and INSTALLER can view the specification on a job. ACCOUNTS can view specifications and estimator costs. MANAGEMENT and ADMIN hold the signage codes, including approval and electrical profiles. There is no separate ESTIMATOR role. DESIGN is the advanced-estimator role.

Production-control codes are `production.release.view`, `production.release.request`, `production.release.approve`, `production.release.override`, `production.release.cancel`, `production.change.request`, `production.change.approve`, `production.queue.view`, `production.supervise`, `production.stage.start`, `production.stage.complete`, `production.stage.block`, `production.rework.manage`, `production.qc.disposition`, `fulfilment.manage`, and `production.view_costs`.

SALES and DESIGN can see release state. They cannot release a job. PRODUCTION can open the released queue and start, complete, block, or rework a stage. PRODUCTION cannot approve a release or override a block. INSTALLER and DISPATCH can see release state and record fulfilment. ACCOUNTS can see production cost on the release screen. MANAGEMENT holds every production-control code, including release, override, and supervision. There is no separate PRODUCTION MANAGER or WORKSHOP role. MANAGEMENT releases. PRODUCTION executes released stages. Purchasing still uses `purchasing.create`. A shortage does not send a purchase order.

Procurement codes are `procurement.rfq.view`, `procurement.rfq.create`, `procurement.rfq.send`, `procurement.rfq.award`, `procurement.quotes.view`, `procurement.quotes.compare`, `procurement.po.approve`, `procurement.contract_prices.view`, `procurement.contract_prices.manage`, `procurement.supplier_portal.manage`, `inventory.locations.manage`, `inventory.putaway`, `inventory.serial.manage`, `inventory.lot.manage`, `inventory.count.create`, `inventory.count.perform`, `inventory.count.approve`, `receiving.perform`, `receiving.exceptions.manage`, and `supplier_returns.manage`. `inventory.transfer` already existed and was not added again.

MANAGEMENT holds every procurement code. ACCOUNTS can view RFQs, quotations, and contract prices, and can confirm a receipt. PRODUCTION, DESIGN, INSTALLER, and DISPATCH do not receive quotation comparison. A workshop user who can see a job cannot open the quote comparison. Supplier portal access is the invitation token. It does not reuse `suppliers.edit` or `procurement.supplier_portal.manage`.

Staff customer-hub codes are `customer_hub.view`, `customer_catalogues.manage`, and `customer_orders.review`. MANAGEMENT holds all three. SALES can view the portal inbox and review an order. SALES cannot edit a catalogue. PRODUCTION cannot review a portal order.

Artwork codes are `artwork.view`, `artwork.create`, `artwork.edit`, `artwork.assign`, `artwork.revise`, `artwork.internal_review`, `artwork.send_proof`, `artwork.comment_internal`, `artwork.approve_internal`, `artwork.production_file.create`, `artwork.production_file.approve`, `artwork.production_file.supersede`, `artwork.physical_proof.manage`, `artwork.files.download_source`, and `artwork.admin`. `artwork.upload` and `artwork.approve_record` were already there and were not added again.

MANAGEMENT holds every new artwork code. DESIGN can create and revise artwork, send a proof, comment internally, prepare a production file, and download a source file. DESIGN cannot approve a production file and cannot release a job. PRODUCTION can view artwork so the workshop can open the current approved file. PRODUCTION cannot download source files. SALES can view, create, and send a proof. ACCOUNTS can view. Portal codes `portal.artwork.view`, `portal.artwork.comment`, `portal.artwork.approve`, and `portal.artwork.upload` sit on the portal user role, not in this table.

Logistics and contractor codes are `logistics.view`, `logistics.shipment.create`, `logistics.shipment.dispatch`, `logistics.shipment.manage`, `logistics.delivery.manage`, `logistics.collection.manage`, `logistics.exceptions.manage`, `installation.schedule`, `installation.execute`, `contractor.manage`, `contractor.assign`, `contractor.work_order.create`, `contractor.work_order.approve`, `contractor.cost.view`, `contractor.cost.approve`, and `contractor.portal.manage`. `installation.signoff` was already there and was not added again.

MANAGEMENT holds every logistics and contractor code. DISPATCH can view logistics, create and dispatch a shipment, manage deliveries and collections, and manage exceptions. DISPATCH cannot manage the shipment record after the fact and cannot see contractor cost. INSTALLER can view logistics, execute an installation, and record sign-off. PRODUCTION and SALES can view logistics. ACCOUNTS can view logistics and contractor cost, and cannot approve that cost. A contractor login is not a staff role. Codes `contractor.work.view`, `contractor.work.accept`, `contractor.work.update`, `contractor.photos.upload`, `contractor.documents.view`, and `contractor.completion.submit` sit on the contractor user, not in this table.

Sales intake codes are `sales_intake.view`, `sales_intake.create`, `sales_intake.assign`, `sales_intake.review`, `sales_intake.confirm_customer`, `sales_intake.confirm_requirements`, `sales_intake.match_product`, `sales_intake.create_estimate`, `sales_intake.create_quote`, `sales_intake.ai_analyse`, `sales_intake.view_ai_audit`, and `sales_intake.admin`.

MANAGEMENT holds every sales-intake code, including provider-cost audit. SALES can capture, review, confirm, match, estimate, quote, and analyse. SALES cannot assign another person’s queue, administer intake, or see provider cost. ACCOUNTS can view the inbox. DESIGN, PRODUCTION, INSTALLER, and DISPATCH do not receive these codes. An intake assigned to someone else stays closed to a salesperson who lacks `sales_intake.admin`.

Portal user roles (`ADMIN`, `BUYER`, `ACCOUNTS`, `VIEW_ONLY`, and the others in `docs/CUSTOMER_PORTAL_SECURITY.md`) are not rows in this permission table. A workshop login does not become a customer login. A contractor login does not become a staff login.

There is no separate SURVEYOR, FINANCE, WORKSHOP, or PURCHASING role. Survey work sits with SALES and INSTALLER. Finance sits with ACCOUNTS. Workshop sits with PRODUCTION. Purchasing sits with the roles that have `purchasing.*`. The customer portal is a different login, not a staff role.

Financial screens that must stay limited: costing, margins, supplier cost, payments, credit notes, discount overrides, stock valuation, cash forecast, and financial export. A salesperson who can see a quote total cannot automatically see gross profit.

Direct URLs use the same permission as the button. A workshop user who opens a costing URL is refused. A portal user who changes a quote id to another customer’s quote is refused.

Change a role from Administration → Roles. The change is audited. Do not edit the permission tables in SQL during normal operation.
