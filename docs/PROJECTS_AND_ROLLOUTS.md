# Projects and multi-site rollouts

v1.1 Phase 1. This sits on Sign-Forge ERP v1.0.0. It is not a new phase number on the original roadmap.

## What a project is

A customer can have a project. A project can have one site or many. A site can have one job or many. The job remains the place where artwork, materials, production, stock, quality, dispatch, installation, and actual cost are recorded.

A normal quote can still become a job with no project. `jobs.project_id` is optional.

v1.0 had no customer-site master. Addresses lived on the job and on the site survey. Project sites are the new site record. They can point at an existing survey and an existing contact. They do not copy the contact book or the stock ledger.

## Numbering

Project numbers are `SFP-YYYY-####`, from `NumberingService` and the locked `number_sequences` row. The prefix is the `project_prefix` setting.

## Status and health

Status is chosen: DRAFT, PLANNING, ACTIVE, ON_HOLD, AT_RISK, COMPLETED, CANCELLED, ARCHIVED.

Health is calculated: ON_TRACK, AT_RISK, OVERDUE, BLOCKED, COMPLETE. The overview lists the reasons, such as a late site, a blocking milestone, artwork still out, or a critical snag. Setting the status to AT_RISK by hand is separate from the calculated health.

## Commercial value

Commercial value is the sum of project commercial lines that count and are not superseded, plus job quotes marked ADDITIONAL, plus approved job variations that are not already represented by a line.

A contract allocation to a site or job is stored and does not count again. A job marked INCLUDED_IN_CONTRACT does not add its quoted revenue on top of the contract.

Example: a R500,000 contract allocated as R100,000 across five jobs is R500,000. An approved R50,000 variation makes R550,000. A superseded quote revision is marked so it does not count twice.

Gross profit is commercial value minus actual cost. Margin is gross profit divided by commercial value. Cash collected is the sum of payment allocations on the project's invoices. It is not called revenue.

Actual cost is the sum of linked job actual costs plus project-level direct costs. Use a direct cost only when the cost cannot sit on one job. The planning budget is operational and is not a ledger.

Cached totals on the project are refreshed for lists. The financial screen recalculates.

## Progress, dates, and templates

Progress is the completed milestone weight divided by the total weight. A project with no weights does not pretend that a count of finished jobs is a percentage.

Templates copy milestones onto the project at creation. Editing the template later does not change that copy.

Original target dates are kept. A later move updates the current target and stores a delay reason. The reason is recorded. It is not treated as blame.

## Rollouts

Waves group sites, for example a pilot wave and a regional wave. Site groups are a label such as REGION plus a name. Provinces are not hard-coded.

CSV import validates the whole file first. A duplicate site code or branch reference rejects the import so nothing is half saved. The same street can warn without merging.

Bulk job creation makes one draft quote and one job per selected site. The job status is NEW and `rollout_state` is DRAFT. No production release, stock movement, or invoice is created. Confirming the action is required.

## Schedule, map, and field work

The Gantt shows milestones, waves, and sites. It does not load production tasks. The calendar shows milestones, surveys, and installations. The map lists sites that have coordinates and uses a `geo:` link. It does not show staff locations.

The phone list is My projects and the site list. A field pack is one site, not the whole rollout.

## Closeout and handover

Closeout checks incomplete sites, incomplete jobs, critical snags, open purchase requests, risks, issues, and outstanding invoices. Critical snags block by default. Open invoices warn by default. Those two rules are settings.

Operational completion records who completed the project and when. It does not close the customer or the invoices.

The handover pack lists the project, sites, and customer-visible documents. It omits cost, margin, supplier prices, internal notes, and the risk register.

## Events

The project services emit PROJECT_CREATED, PROJECT_STARTED, PROJECT_STATUS_CHANGED, PROJECT_AT_RISK, PROJECT_COMPLETED, PROJECT_SITE_ADDED, PROJECT_MILESTONE_DUE, PROJECT_MILESTONE_COMPLETED, PROJECT_RISK_CREATED, PROJECT_ISSUE_CREATED, PROJECT_ISSUE_RESOLVED, and PROJECT_CHANGE_APPROVED through `BusinessEventDispatcher`. Workflows can listen for those event types. They do not write SQL themselves.

Milestone reminders run from the hourly cron.

## API

See `docs/API.md`. Scope `projects.read` is required. Money fields on the summary also require `projects.financials`.
