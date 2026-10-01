# Permissions

v1.0.0 has 232 permission codes. ADMIN holds all 190 that are granted in the seed and is also allowed through in code. The other roles, and how many codes they hold:

| Role | Codes |
| --- | --- |
| MANAGEMENT | 91 |
| SALES | 78 |
| ACCOUNTS | 57 |
| PRODUCTION | 47 |
| INSTALLER | 30 |
| DISPATCH | 21 |
| DESIGN | 16 |
| MARKETING | 11 |

There is no separate SURVEYOR, FINANCE, WORKSHOP, or PURCHASING role. Survey work sits with SALES and INSTALLER. Finance sits with ACCOUNTS. Workshop sits with PRODUCTION. Purchasing sits with the roles that have `purchasing.*`. The customer portal is a different login, not a staff role.

Financial screens that must stay limited: costing, margins, supplier cost, payments, credit notes, discount overrides, stock valuation, cash forecast, and financial export. A salesperson who can see a quote total cannot automatically see gross profit.

Direct URLs use the same permission as the button. A workshop user who opens a costing URL is refused. A portal user who changes a quote id to another customer’s quote is refused.

Change a role from Administration → Roles. The change is audited. Do not edit the permission tables in SQL during normal operation.
