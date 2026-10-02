# Permission matrix

Stored grants. The ADMIN role is also allowed every permission in code, even when a grant row is missing. Customer, supplier, and contractor portals use their own accounts. They are not these staff roles.

| Module | Permission | ADMIN | MANAGEMENT | SALES | DESIGN | PRODUCTION | ACCOUNTS | INSTALLER | DISPATCH | MARKETING |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| admin | `audit.view` | Y | Y |  |  |  |  |  |  |  |
| admin | `automations.manage` | Y |  |  |  |  |  |  |  |  |
| admin | `automations.view` | Y | Y |  |  |  |  |  |  |  |
| admin | `integrations.manage` | Y |  |  |  |  |  |  |  |  |
| admin | `integrations.view` | Y | Y |  |  |  |  |  |  |  |
| admin | `notifications.manage` | Y | Y |  |  |  |  |  |  |  |
| admin | `portal.access_manage` | Y |  | Y |  |  |  |  |  |  |
| admin | `portal.manage` | Y |  |  |  |  |  |  |  |  |
| admin | `settings.manage` | Y |  |  |  |  |  |  |  |  |
| admin | `system.backup` | Y |  |  |  |  |  |  |  |  |
| admin | `system.health` | Y |  |  |  |  |  |  |  |  |
| admin | `system.logs` | Y |  |  |  |  |  |  |  |  |
| admin | `users.manage` | Y |  |  |  |  |  |  |  |  |
| ai | `ai.admin` | Y |  |  |  |  |  |  |  |  |
| ai | `ai.communication_draft` | Y | Y | Y |  |  |  |  |  |  |
| ai | `ai.document_extract` | Y |  |  |  |  |  |  |  |  |
| ai | `ai.summary` | Y | Y | Y |  |  |  |  |  |  |
| ai | `ai.use` | Y | Y | Y |  |  |  |  |  |  |
| approvals | `approvals.decide` | Y | Y |  |  |  |  |  |  |  |
| approvals | `approvals.request` | Y | Y | Y |  |  | Y |  |  |  |
| approvals | `approvals.view` | Y | Y | Y |  |  | Y |  |  |  |
| assets | `assets.archive` | Y | Y |  |  |  |  |  |  |  |
| assets | `assets.create` | Y | Y | Y |  |  |  |  |  |  |
| assets | `assets.edit` | Y | Y |  |  |  |  |  |  |  |
| assets | `assets.generate_labels` | Y | Y |  |  |  |  |  |  |  |
| assets | `assets.import` | Y | Y |  |  |  |  |  |  |  |
| assets | `assets.manage_components` | Y | Y |  |  |  |  |  |  |  |
| assets | `assets.manage_warranties` | Y | Y |  |  |  |  |  |  |  |
| assets | `assets.view` | Y | Y | Y | Y | Y | Y | Y |  |  |
| assets | `assets.view_costs` | Y | Y |  |  |  | Y |  |  |  |
| automation | `recipes.create` | Y |  |  |  |  |  |  |  |  |
| automation | `recipes.deactivate` | Y |  |  |  |  |  |  |  |  |
| automation | `recipes.edit` | Y |  |  |  |  |  |  |  |  |
| automation | `recipes.test` | Y | Y | Y |  |  |  |  |  |  |
| automation | `recipes.view` | Y | Y | Y | Y | Y |  |  |  |  |
| automation | `recipes.view_cost` | Y | Y | Y |  |  |  |  |  |  |
| automation | `workflows.manage` | Y |  |  |  |  |  |  |  |  |
| automation | `workflows.test` | Y | Y |  |  |  |  |  |  |  |
| automation | `workflows.view` | Y | Y | Y |  |  |  |  |  |  |
| calendar | `calendar.manage_feed` | Y | Y |  |  |  |  |  |  |  |
| calendar | `calendar.view_company` | Y | Y |  |  |  |  |  |  |  |
| configuration | `configuration.manage` | Y |  |  |  |  |  |  |  |  |
| configuration | `custom_fields.manage` | Y |  |  |  |  |  |  |  |  |
| configuration | `custom_forms.manage` | Y |  |  |  |  |  |  |  |  |
| configuration | `feature_flags.manage` | Y |  |  |  |  |  |  |  |  |
| crm | `activities.manage` | Y |  | Y |  |  |  |  |  |  |
| crm | `activities.view` | Y | Y | Y | Y |  |  |  |  |  |
| crm | `attachments.manage` | Y |  | Y | Y | Y |  | Y | Y |  |
| crm | `communication_templates.manage` | Y |  |  |  |  |  |  |  | Y |
| crm | `communication_templates.view` | Y | Y | Y |  |  |  |  |  | Y |
| crm | `communications.create` | Y |  | Y |  |  | Y |  |  |  |
| crm | `communications.send_email` | Y |  | Y |  |  | Y |  |  |  |
| crm | `communications.view` | Y | Y | Y | Y | Y | Y |  |  | Y |
| crm | `communications.whatsapp` | Y |  | Y |  |  |  |  |  |  |
| crm | `customers.manage` | Y |  | Y |  |  |  |  |  |  |
| crm | `customers.view` | Y | Y | Y | Y |  | Y | Y |  |  |
| crm | `leads.assign` | Y |  | Y |  |  |  |  |  |  |
| crm | `leads.convert` | Y |  | Y |  |  |  |  |  |  |
| crm | `leads.create` | Y |  | Y |  |  |  |  |  |  |
| crm | `leads.mark_lost` | Y |  | Y |  |  |  |  |  |  |
| crm | `leads.view` | Y | Y | Y |  |  |  |  |  | Y |
| crm | `site_surveys.complete` | Y |  | Y |  |  |  |  |  |  |
| crm | `site_surveys.create` | Y |  | Y |  |  |  | Y |  |  |
| crm | `site_surveys.edit` | Y |  | Y |  |  |  | Y |  |  |
| crm | `site_surveys.view` | Y | Y | Y | Y | Y |  | Y |  |  |
| dashboard | `dashboard.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| data_quality | `data_quality.manage` | Y | Y |  |  |  |  |  |  |  |
| entity_merge | `entity_merge.execute` | Y | Y |  |  |  |  |  |  |  |
| entity_merge | `entity_merge.preview` | Y | Y |  |  |  |  |  |  |  |
| estimating | `estimates.approve` | Y |  |  |  |  |  |  |  |  |
| estimating | `estimates.create` | Y |  | Y |  |  |  |  |  |  |
| estimating | `estimates.edit` | Y |  | Y |  |  |  |  |  |  |
| estimating | `estimates.view` | Y | Y | Y |  |  | Y |  |  |  |
| estimating | `estimates.view_cost` | Y | Y | Y |  |  | Y |  |  |  |
| estimating | `historical_costing.view` | Y | Y |  |  |  | Y |  |  |  |
| estimating | `pricing_intelligence.view` | Y | Y | Y |  |  |  |  |  |  |
| estimating | `pricing_recommendations.apply` | Y |  |  |  |  |  |  |  |  |
| estimating | `pricing_recommendations.review` | Y | Y |  |  |  |  |  |  |  |
| estimating | `quote_risk.override` | Y | Y |  |  |  |  |  |  |  |
| estimating | `yield.override` | Y |  | Y |  |  |  |  |  |  |
| estimating | `yield.view` | Y | Y | Y |  |  |  |  |  |  |
| expenses | `expenses.approve` | Y | Y |  |  |  | Y |  |  |  |
| expenses | `expenses.create` | Y | Y | Y |  |  |  | Y |  |  |
| expenses | `expenses.export` | Y | Y |  |  |  | Y |  |  |  |
| expenses | `expenses.reimbursement.manage` | Y | Y |  |  |  | Y |  |  |  |
| expenses | `expenses.reject` | Y | Y |  |  |  | Y |  |  |  |
| expenses | `expenses.reverse` | Y | Y |  |  |  | Y |  |  |  |
| expenses | `expenses.submit` | Y | Y | Y |  |  |  | Y |  |  |
| expenses | `expenses.view` | Y | Y | Y |  |  | Y | Y |  |  |
| expenses | `expenses.view_all` | Y | Y |  |  |  | Y |  |  |  |
| finance | `credit_notes.create` | Y |  |  |  |  | Y |  |  |  |
| finance | `credit_notes.issue` | Y |  |  |  |  | Y |  |  |  |
| finance | `credit_notes.view` | Y | Y |  |  |  | Y |  |  |  |
| finance | `debtors.view` | Y | Y |  |  |  | Y |  |  |  |
| finance | `finance.costing.view` | Y | Y |  |  |  | Y |  |  |  |
| finance | `finance.vat_report.view` | Y | Y |  |  |  | Y |  |  |  |
| finance | `invoices.cancel` | Y |  |  |  |  | Y |  |  |  |
| finance | `invoices.create` | Y |  |  |  |  | Y |  |  |  |
| finance | `invoices.edit_draft` | Y |  |  |  |  | Y |  |  |  |
| finance | `invoices.issue` | Y |  |  |  |  | Y |  |  |  |
| finance | `invoices.view` | Y | Y | Y |  |  | Y |  |  |  |
| finance | `payment_links.create` | Y |  |  |  |  | Y |  |  |  |
| finance | `payment_links.manage` | Y |  |  |  |  | Y |  |  |  |
| finance | `payments.allocate` | Y |  |  |  |  | Y |  |  |  |
| finance | `payments.record` | Y |  |  |  |  | Y |  |  |  |
| finance | `payments.reverse` | Y |  |  |  |  | Y |  |  |  |
| finance | `payments.view` | Y | Y | Y |  |  | Y |  |  |  |
| finance | `statements.generate` | Y |  |  |  |  | Y |  |  |  |
| finance | `statements.view` | Y | Y |  |  |  | Y |  |  |  |
| integrations | `api.manage` |  |  |  |  |  |  |  |  |  |
| integrations | `integration_logs.view` |  |  |  |  |  |  |  |  |  |
| integrations | `integrations.configure` | Y |  |  |  |  |  |  |  |  |
| integrations | `integrations.retry` | Y |  |  |  |  |  |  |  |  |
| integrations | `webhooks.manage` |  |  |  |  |  |  |  |  |  |
| inventory | `categories.manage` | Y |  |  |  |  |  |  |  |  |
| inventory | `inventory.adjust` | Y |  |  |  |  |  |  |  |  |
| inventory | `inventory.consume` | Y |  |  |  | Y |  |  |  |  |
| inventory | `inventory.count` | Y |  |  |  |  |  |  |  |  |
| inventory | `inventory.override` | Y |  |  |  |  |  |  |  |  |
| inventory | `inventory.receive` | Y |  |  |  |  |  |  |  |  |
| inventory | `inventory.transfer` | Y |  |  |  | Y |  |  |  |  |
| inventory | `inventory.view` | Y | Y | Y |  | Y | Y | Y |  |  |
| inventory | `inventory.view_cost` | Y |  |  |  |  | Y |  |  |  |
| inventory | `products.manage` | Y |  |  |  |  |  |  |  |  |
| inventory | `products.view` | Y |  | Y | Y | Y | Y |  |  |  |
| inventory | `suppliers.manage` | Y |  |  |  |  |  |  |  |  |
| inventory | `suppliers.view` | Y |  | Y |  | Y | Y |  |  |  |
| logistics | `contractor.assign` | Y | Y |  |  |  |  |  |  |  |
| logistics | `contractor.cost.approve` | Y | Y |  |  |  |  |  |  |  |
| logistics | `contractor.cost.view` | Y | Y |  |  |  | Y |  |  |  |
| logistics | `contractor.manage` | Y | Y |  |  |  |  |  |  |  |
| logistics | `contractor.portal.manage` | Y | Y |  |  |  |  |  |  |  |
| logistics | `contractor.work_order.approve` | Y | Y |  |  |  |  |  |  |  |
| logistics | `contractor.work_order.create` | Y | Y |  |  |  |  |  |  |  |
| logistics | `installation.execute` | Y | Y |  |  |  |  | Y |  |  |
| logistics | `installation.schedule` | Y | Y |  |  |  |  |  |  |  |
| logistics | `logistics.collection.manage` | Y | Y |  |  |  |  |  | Y |  |
| logistics | `logistics.delivery.manage` | Y | Y |  |  |  |  |  | Y |  |
| logistics | `logistics.exceptions.manage` | Y | Y |  |  |  |  |  | Y |  |
| logistics | `logistics.shipment.create` | Y | Y |  |  |  |  |  | Y |  |
| logistics | `logistics.shipment.dispatch` | Y | Y |  |  |  |  |  | Y |  |
| logistics | `logistics.shipment.manage` | Y | Y |  |  |  |  |  |  |  |
| logistics | `logistics.view` | Y | Y | Y |  | Y | Y | Y | Y |  |
| marketing | `bulk_communications.send` | Y |  |  |  |  |  |  |  | Y |
| marketing | `campaigns.manage` | Y |  |  |  |  |  |  |  | Y |
| marketing | `campaigns.view` | Y | Y |  |  |  |  |  |  | Y |
| marketing | `customer_retention.view` | Y | Y | Y |  |  |  |  |  | Y |
| marketing | `feedback.manage` | Y |  |  |  |  |  |  |  |  |
| marketing | `feedback.view` | Y | Y | Y |  |  |  |  |  | Y |
| marketing | `marketing_reports.view` | Y | Y |  |  |  |  |  |  | Y |
| mileage | `mileage.approve` | Y | Y |  |  |  |  |  |  |  |
| mileage | `mileage.create` | Y | Y | Y |  |  |  | Y |  |  |
| mobile | `delivery.mobile` | Y |  |  |  |  |  |  | Y |  |
| mobile | `device.manage_all` | Y | Y |  |  |  |  |  |  |  |
| mobile | `device.manage_own` | Y | Y | Y |  | Y |  | Y | Y |  |
| mobile | `device.view_own` | Y | Y | Y |  | Y |  | Y | Y |  |
| mobile | `field_pack.download` | Y |  | Y |  |  |  | Y | Y |  |
| mobile | `installation.mobile` | Y |  |  |  |  |  | Y |  |  |
| mobile | `mobile.use` | Y | Y | Y |  | Y |  | Y | Y |  |
| mobile | `offline.use` | Y |  | Y |  | Y |  | Y | Y |  |
| mobile | `push_notifications.use` | Y | Y | Y |  | Y |  | Y | Y |  |
| mobile | `site_survey.mobile` | Y |  | Y |  |  |  | Y |  |  |
| mobile | `sync_conflicts.resolve` | Y | Y |  |  |  |  |  |  |  |
| mobile | `sync_conflicts.view` | Y | Y |  |  |  |  | Y |  |  |
| mobile | `workshop.tablet` | Y |  |  |  | Y |  |  |  |  |
| operations | `artwork.admin` | Y | Y |  |  |  |  |  |  |  |
| operations | `artwork.approve_internal` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.approve_record` | Y |  | Y | Y |  |  |  |  |  |
| operations | `artwork.assign` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.comment_internal` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.create` | Y | Y | Y | Y |  |  |  |  |  |
| operations | `artwork.edit` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.files.download_source` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.internal_review` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.override_approval` | Y |  |  |  |  |  |  |  |  |
| operations | `artwork.physical_proof.manage` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.production_file.approve` | Y | Y |  |  |  |  |  |  |  |
| operations | `artwork.production_file.create` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.production_file.supersede` | Y | Y |  |  |  |  |  |  |  |
| operations | `artwork.revise` | Y | Y |  | Y |  |  |  |  |  |
| operations | `artwork.send_proof` | Y | Y | Y | Y |  |  |  |  |  |
| operations | `artwork.upload` | Y |  |  | Y |  |  |  |  |  |
| operations | `artwork.view` | Y | Y | Y | Y | Y | Y |  |  |  |
| operations | `capacity.view` | Y | Y | Y |  |  |  |  |  |  |
| operations | `costing.edit` | Y |  |  |  |  | Y |  |  |  |
| operations | `installations.complete` | Y |  |  |  |  |  | Y |  |  |
| operations | `installations.schedule` | Y |  |  |  |  |  |  |  |  |
| operations | `installations.view` | Y | Y | Y |  |  |  | Y |  |  |
| operations | `jobs.assign` | Y |  |  |  |  |  |  |  |  |
| operations | `jobs.change_status` | Y |  |  |  | Y |  |  |  |  |
| operations | `jobs.complete` | Y |  |  |  |  |  |  |  |  |
| operations | `jobs.create` | Y |  | Y |  |  |  |  |  |  |
| operations | `jobs.edit` | Y |  | Y |  |  |  |  |  |  |
| operations | `jobs.reopen` | Y |  |  |  |  |  |  |  |  |
| operations | `jobs.view` | Y | Y | Y | Y | Y | Y | Y | Y |  |
| operations | `machines.manage` | Y |  |  |  |  |  |  |  |  |
| operations | `maintenance.manage` | Y |  |  |  |  |  |  |  |  |
| operations | `maintenance.view` | Y | Y |  |  | Y |  |  |  |  |
| operations | `materials.record_usage` | Y |  |  |  | Y |  |  |  |  |
| operations | `materials.view` | Y |  |  |  | Y | Y |  |  |  |
| operations | `production.update` | Y |  |  |  | Y |  |  |  |  |
| operations | `production.view` | Y | Y |  | Y | Y |  |  |  |  |
| operations | `recurring_jobs.manage` | Y |  |  |  |  |  |  |  |  |
| operations | `recurring_jobs.view` | Y | Y |  |  |  |  |  |  |  |
| operations | `resource_cost.view` | Y | Y |  |  |  | Y |  |  |  |
| operations | `resources.manage` | Y |  |  |  |  |  |  |  |  |
| operations | `resources.view` | Y | Y |  |  | Y |  |  |  |  |
| operations | `schedule.manage` | Y | Y |  |  |  |  |  |  |  |
| operations | `schedule.override_conflict` | Y | Y |  |  |  |  |  |  |  |
| operations | `schedule.view` | Y | Y | Y | Y | Y |  | Y |  |  |
| operations | `staff_availability.manage` | Y |  |  |  |  |  |  |  |  |
| operations | `subcontractors.manage` | Y |  |  |  |  | Y |  |  |  |
| operations | `subcontractors.view` | Y | Y |  |  |  | Y |  |  |  |
| operations | `time.record` | Y |  |  | Y | Y |  | Y |  |  |
| operations | `time.view_all` | Y |  |  |  |  | Y |  |  |  |
| operations | `vehicles.manage` | Y |  |  |  |  |  |  |  |  |
| operations | `vehicles.view` | Y | Y |  |  |  |  | Y |  |  |
| planning | `budgets.manage` |  |  |  |  |  |  |  |  |  |
| planning | `budgets.view` |  | Y |  |  |  | Y |  |  |  |
| planning | `data_quality.view` | Y | Y |  |  |  | Y |  |  |  |
| planning | `exports.perform` |  | Y |  |  |  |  |  |  |  |
| planning | `forecast.capacity` |  | Y |  |  | Y |  |  |  |  |
| planning | `forecast.cash` |  | Y |  |  |  | Y |  |  |  |
| planning | `forecast.materials` |  | Y |  |  | Y |  |  |  |  |
| planning | `forecast.sales` |  | Y | Y |  |  |  |  |  |  |
| planning | `imports.perform` |  |  |  |  |  |  |  |  |  |
| planning | `mrp.manage` |  |  |  |  |  |  |  |  |  |
| planning | `mrp.view` |  | Y |  |  | Y |  |  |  |  |
| planning | `planning.view` |  | Y | Y |  |  | Y |  |  |  |
| planning | `purchase_recommendations.review` |  |  |  |  |  |  |  |  |  |
| planning | `scenarios.manage` |  |  |  |  |  |  |  |  |  |
| planning | `scenarios.view` |  | Y |  |  |  |  |  |  |  |
| planning | `targets.manage` |  |  |  |  |  |  |  |  |  |
| planning | `targets.view` |  | Y | Y |  |  | Y |  |  |  |
| pricing | `pricing.manage` | Y |  |  |  |  |  |  |  |  |
| pricing | `pricing.view` | Y |  |  |  |  | Y |  |  |  |
| procurement | `inventory.count.approve` | Y | Y |  |  |  |  |  |  |  |
| procurement | `inventory.count.create` | Y | Y |  |  |  |  |  |  |  |
| procurement | `inventory.count.perform` | Y | Y |  |  |  |  |  |  |  |
| procurement | `inventory.locations.manage` | Y | Y |  |  |  |  |  |  |  |
| procurement | `inventory.lot.manage` | Y | Y |  |  |  |  |  |  |  |
| procurement | `inventory.putaway` | Y | Y |  |  |  |  |  |  |  |
| procurement | `inventory.serial.manage` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.contract_prices.manage` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.contract_prices.view` | Y | Y |  |  |  | Y |  |  |  |
| procurement | `procurement.po.approve` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.quotes.compare` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.quotes.view` | Y | Y |  |  |  | Y |  |  |  |
| procurement | `procurement.rfq.award` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.rfq.create` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.rfq.send` | Y | Y |  |  |  |  |  |  |  |
| procurement | `procurement.rfq.view` | Y | Y |  |  |  | Y |  |  |  |
| procurement | `procurement.supplier_portal.manage` | Y | Y |  |  |  |  |  |  |  |
| procurement | `receiving.exceptions.manage` | Y | Y |  |  |  |  |  |  |  |
| procurement | `receiving.perform` | Y | Y |  |  |  | Y |  |  |  |
| procurement | `supplier_returns.manage` | Y | Y |  |  |  |  |  |  |  |
| production_control | `fulfilment.manage` | Y | Y |  |  |  |  | Y | Y |  |
| production_control | `production.change.approve` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.change.request` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.qc.disposition` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.queue.view` | Y | Y |  |  | Y |  |  |  |  |
| production_control | `production.release.approve` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.release.cancel` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.release.override` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.release.request` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.release.view` | Y | Y | Y | Y |  |  | Y | Y |  |
| production_control | `production.rework.manage` | Y | Y |  |  | Y |  |  |  |  |
| production_control | `production.stage.block` | Y | Y |  |  | Y |  |  |  |  |
| production_control | `production.stage.complete` | Y | Y |  |  | Y |  |  |  |  |
| production_control | `production.stage.start` | Y | Y |  |  | Y |  |  |  |  |
| production_control | `production.supervise` | Y | Y |  |  |  |  |  |  |  |
| production_control | `production.view_costs` | Y | Y |  |  |  | Y |  |  |  |
| projects | `projects.archive` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.bulk_create_jobs` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.complete` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.create` | Y | Y | Y |  |  |  |  |  |  |
| projects | `projects.edit` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.generate_handover` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.import_sites` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.manage_budget` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.manage_changes` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.manage_issues` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.manage_milestones` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.manage_risks` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.manage_sites` | Y | Y | Y |  |  |  |  |  |  |
| projects | `projects.manage_team` | Y | Y |  |  |  |  |  |  |  |
| projects | `projects.view` | Y | Y | Y |  |  | Y |  |  |  |
| projects | `projects.view_assigned` | Y | Y | Y | Y | Y | Y | Y |  |  |
| projects | `projects.view_financials` | Y | Y | Y |  |  | Y |  |  |  |
| projects | `projects.view_reports` | Y | Y |  |  |  | Y |  |  |  |
| purchasing | `purchasing.approve` | Y |  |  |  |  |  |  |  |  |
| purchasing | `purchasing.cancel` | Y |  |  |  |  |  |  |  |  |
| purchasing | `purchasing.create` | Y |  |  |  |  |  |  |  |  |
| purchasing | `purchasing.edit` | Y |  |  |  |  |  |  |  |  |
| purchasing | `purchasing.receive` | Y |  |  |  |  | Y |  |  |  |
| purchasing | `purchasing.view` | Y | Y |  |  |  | Y |  |  |  |
| purchasing | `supplier_prices.edit` | Y |  |  |  |  | Y |  |  |  |
| purchasing | `supplier_prices.view` | Y |  |  |  |  | Y |  |  |  |
| reports | `reports.executive` | Y | Y |  |  |  |  |  |  |  |
| reports | `reports.export` | Y | Y | Y |  |  | Y |  |  |  |
| reports | `reports.finance` | Y | Y |  |  |  | Y |  |  |  |
| reports | `reports.inventory` | Y | Y |  |  |  |  |  |  |  |
| reports | `reports.operations` | Y | Y |  |  | Y |  |  |  |  |
| reports | `reports.profitability` | Y | Y |  |  |  | Y |  |  |  |
| reports | `reports.sales` | Y | Y | Y |  |  |  |  |  |  |
| reviews | `review_queue.resolve` | Y | Y |  |  |  |  |  |  |  |
| reviews | `review_queue.view` | Y | Y |  |  | Y | Y |  |  |  |
| sales | `calculator.use` | Y |  | Y | Y |  |  |  |  |  |
| sales | `costing.view` | Y | Y | Y |  |  | Y |  |  |  |
| sales | `customer_catalogues.manage` | Y | Y |  |  |  |  |  |  |  |
| sales | `customer_hub.view` | Y | Y | Y |  |  |  |  |  |  |
| sales | `customer_orders.review` | Y | Y | Y |  |  |  |  |  |  |
| sales | `opportunities.manage` | Y |  | Y |  |  |  |  |  |  |
| sales | `opportunities.view` | Y | Y | Y |  |  |  |  |  |  |
| sales | `quotes.accept` | Y |  | Y |  |  |  |  |  |  |
| sales | `quotes.convert` | Y |  | Y |  |  |  |  |  |  |
| sales | `quotes.discount` | Y |  | Y |  |  |  |  |  |  |
| sales | `quotes.discount_below_cost` | Y |  |  |  |  |  |  |  |  |
| sales | `quotes.manage` | Y |  | Y |  |  |  |  |  |  |
| sales | `quotes.price_override` | Y |  | Y |  |  |  |  |  |  |
| sales | `quotes.view` | Y | Y | Y |  |  | Y |  |  |  |
| sales | `templates.manage` | Y |  | Y |  |  |  |  |  |  |
| sales | `templates.view` | Y | Y | Y | Y |  |  |  |  |  |
| sales_intake | `sales_intake.admin` | Y | Y |  |  |  |  |  |  |  |
| sales_intake | `sales_intake.ai_analyse` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.assign` | Y | Y |  |  |  |  |  |  |  |
| sales_intake | `sales_intake.confirm_customer` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.confirm_requirements` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.create` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.create_estimate` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.create_quote` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.match_product` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.review` | Y | Y | Y |  |  |  |  |  |  |
| sales_intake | `sales_intake.view` | Y | Y | Y |  |  | Y |  |  |  |
| sales_intake | `sales_intake.view_ai_audit` | Y | Y |  |  |  |  |  |  |  |
| service | `inspections.manage` | Y | Y |  |  |  |  |  |  |  |
| service | `inspections.perform` | Y | Y |  |  |  |  | Y |  |  |
| service | `service_jobs.manage` | Y | Y |  |  |  |  |  |  |  |
| service | `service_reports.generate` | Y | Y |  |  |  |  |  |  |  |
| service | `service_requests.assign` | Y | Y |  |  |  |  |  |  |  |
| service | `service_requests.create` | Y | Y | Y |  |  |  |  |  |  |
| service | `service_requests.manage` | Y | Y |  |  |  |  |  |  |  |
| service | `service_requests.view` | Y | Y | Y |  |  | Y | Y |  |  |
| service | `service.view_costs` | Y | Y |  |  |  | Y |  |  |  |
| service | `warranty_claims.manage` | Y | Y |  |  |  |  |  |  |  |
| service | `warranty_claims.view` | Y | Y | Y |  |  | Y |  |  |  |
| signage | `electrical_profiles.manage` | Y | Y |  |  |  |  |  |  |  |
| signage | `estimators.override` | Y | Y |  | Y |  |  |  |  |  |
| signage | `estimators.technical_review` | Y | Y |  | Y |  |  |  |  |  |
| signage | `estimators.use` | Y | Y | Y | Y |  |  |  |  |  |
| signage | `estimators.view_costs` | Y | Y |  |  |  | Y |  |  |  |
| signage | `geometry.upload` | Y | Y |  | Y |  |  |  |  |  |
| signage | `specifications.approve` | Y | Y |  |  |  |  |  |  |  |
| signage | `specifications.archive` | Y | Y |  |  |  |  |  |  |  |
| signage | `specifications.create` | Y | Y |  | Y |  |  |  |  |  |
| signage | `specifications.edit` | Y | Y |  | Y |  |  |  |  |  |
| signage | `specifications.view` | Y | Y | Y | Y | Y | Y | Y |  |  |
| signage | `vehicle_templates.manage` | Y | Y |  | Y |  |  |  |  |  |
| signage | `vehicle_templates.view` | Y | Y | Y | Y |  |  |  |  |  |
| trips | `trips.manage` | Y | Y |  |  |  |  |  |  |  |
| workshop | `delivery.signoff` |  |  |  |  |  |  |  | Y |  |
| workshop | `dispatch.complete` |  |  |  |  |  |  |  | Y |  |
| workshop | `dispatch.create` |  |  |  |  |  |  |  | Y |  |
| workshop | `dispatch.view` |  | Y | Y |  | Y | Y |  | Y |  |
| workshop | `documents.internal.view` |  | Y |  |  | Y | Y | Y | Y |  |
| workshop | `documents.templates.manage` |  |  |  |  |  |  |  |  |  |
| workshop | `installation.signoff` |  |  |  |  |  |  | Y |  |  |
| workshop | `kiosk.use` |  |  |  |  | Y |  | Y | Y |  |
| workshop | `labels.print` |  |  |  |  | Y |  | Y | Y |  |
| workshop | `labels.reprint` |  |  |  |  | Y |  |  | Y |  |
| workshop | `production.complete` |  |  |  |  | Y |  |  |  |  |
| workshop | `production.override_material` |  |  |  |  | Y |  |  |  |  |
| workshop | `production.pause` |  |  |  |  | Y |  |  |  |  |
| workshop | `production.reprint` |  |  |  |  | Y |  |  |  |  |
| workshop | `production.start` |  |  |  |  | Y |  |  |  |  |
| workshop | `qc.override` |  |  |  |  | Y |  |  |  |  |
| workshop | `qc.perform` |  |  |  |  | Y |  |  |  |  |
| workshop | `snags.manage` |  |  |  |  |  |  | Y |  |  |
| workshop | `snags.view` |  | Y | Y |  | Y |  | Y | Y |  |
| workshop | `tracking.traceability.view` |  | Y |  |  | Y |  |  |  |  |
| workshop | `workshop.scan` |  |  |  |  | Y |  | Y | Y |  |
| workshop | `workshop.view` |  | Y | Y |  | Y |  | Y | Y |  |

382 permissions.
