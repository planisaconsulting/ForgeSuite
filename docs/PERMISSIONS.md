# Permissions

v1.1.0 has 270 permission codes. ADMIN holds all 228 that are granted in the seed and is also allowed through in code. The other roles, and how many codes they hold:

| Role | Codes |
| --- | --- |
| MANAGEMENT | 129 |
| SALES | 88 |
| ACCOUNTS | 66 |
| PRODUCTION | 49 |
| INSTALLER | 34 |
| DISPATCH | 21 |
| DESIGN | 18 |
| MARKETING | 11 |

Project codes are `projects.view`, `projects.view_assigned`, `projects.create`, `projects.edit`, `projects.archive`, `projects.complete`, `projects.view_financials`, `projects.manage_team`, `projects.manage_sites`, `projects.import_sites`, `projects.bulk_create_jobs`, `projects.manage_milestones`, `projects.manage_risks`, `projects.manage_issues`, `projects.manage_budget`, `projects.manage_changes`, `projects.generate_handover`, and `projects.view_reports`.

SALES can create projects and see commercial figures. ACCOUNTS can see project financials and reports. DESIGN, PRODUCTION, and INSTALLER only open projects they are assigned to, and they cannot open financials. MANAGEMENT receives the project codes. ADMIN is allowed through in code.

Asset and service codes are `assets.view`, `assets.create`, `assets.edit`, `assets.archive`, `assets.view_costs`, `assets.manage_components`, `assets.manage_warranties`, `assets.generate_labels`, `assets.import`, `service_requests.view`, `service_requests.create`, `service_requests.assign`, `service_requests.manage`, `service_jobs.manage`, `service.view_costs`, `warranty_claims.view`, `warranty_claims.manage`, `inspections.perform`, `inspections.manage`, and `service_reports.generate`. Asset maintenance plans reuse the existing `maintenance.manage` code. INSTALLER can see assets, see service requests, and record inspections. INSTALLER cannot see asset cost, service cost, supplier cost, or project financials. A portal login can open only assets for that customer account.

There is no separate SURVEYOR, FINANCE, WORKSHOP, or PURCHASING role. Survey work sits with SALES and INSTALLER. Finance sits with ACCOUNTS. Workshop sits with PRODUCTION. Purchasing sits with the roles that have `purchasing.*`. The customer portal is a different login, not a staff role.

Financial screens that must stay limited: costing, margins, supplier cost, payments, credit notes, discount overrides, stock valuation, cash forecast, and financial export. A salesperson who can see a quote total cannot automatically see gross profit.

Direct URLs use the same permission as the button. A workshop user who opens a costing URL is refused. A portal user who changes a quote id to another customer’s quote is refused.

Change a role from Administration → Roles. The change is audited. Do not edit the permission tables in SQL during normal operation.
