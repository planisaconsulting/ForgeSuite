# Production control

Released work is what the workshop queue executes. Unreleased jobs stay out of that queue. They can still be opened on the job page.

## Queue

Workshop → Released work (`/production/queue`) lists stages on jobs whose preparation status is `RELEASED` or `IN_PRODUCTION`. The query is one statement with optional filters for stage status, priority, and project id, plus a limit and offset.

The page states how many jobs are not released. Those jobs are not mixed into the rows.

Workshop → Today board (`/production/today`) uses large type and refreshes every 60 seconds. It shows overdue released jobs, blocked stages, and jobs that are not released. It does not use a websocket.

The older `/workshop/queue` path is unchanged.

## Stage execution

`ProductionStageControlService` is the released-work path.

Before a stage starts, the job must have a current `RELEASED` release, and the previous stage on the same job item must be complete. The start time is the server time. The user, stage, and release are stored. A repeated idempotency key does not start the stage twice.

Pause reasons are `BREAK`, `WAITING_MATERIAL`, `WAITING_ARTWORK`, `MACHINE`, `CUSTOMER`, `QUALITY`, and `OTHER`.

Block reasons are `MATERIAL_SHORTAGE`, `ARTWORK_QUERY`, `MACHINE_BREAKDOWN`, `QUALITY_ISSUE`, `CUSTOMER_CHANGE`, `TECHNICAL_QUERY`, `RESOURCE_SHORTAGE`, and `OTHER`. A block notifies the `MANAGEMENT` role. The reason is required.

Completion records good quantity, waste quantity, and rework quantity. Good plus waste cannot exceed the planned quantity. Rework cannot exceed good quantity. Completing a stage that is already complete does not write a second completion.

`JobService::updateStage` is the older path. It can still move a stage on a job that has not been released. Use the released-work path when the release must be enforced.

## Waste and rework

Waste quantities are stored on the stage and the job item. They are available to the existing material-usage and job-cost reports when those screens read the item quantities. The release itself does not post a stock movement.

Rework is a `production_rework` row. It is not a silent restart of the stage. Reason codes are `ARTWORK_ERROR`, `PRINT_DEFECT`, `MATERIAL_DEFECT`, `MACHINE_ERROR`, `PRODUCTION_ERROR`, `CUSTOMER_CHANGE`, `INSTALLATION_DAMAGE`, and `OTHER`. The code is a cause category. It does not name an employee.

Rework cost is added to `actual_material_cost`, `actual_labour_cost`, and `actual_other_cost` (machine plus other). `actual_total_cost` is the sum of those three after the addition. An original actual cost of 5000 plus rework material of 800 is 5800.

## QC

A failed quality check with `resolved_at` empty blocks fulfilment. The check type used by this release gate is `RELEASE_GATE` and the status is `FAIL`.

The next step is rework or an authorised disposition (`REWORK`, `ACCEPT_AS_IS`, `SCRAP`, `REMAKE`). An internal override does not hide a customer-visible deviation. Customer acceptance of a deviation is still a commercial record, not a workshop note.

## Files and colour

`production_files.category` is `PRINT`, `CUT`, `CNC`, `ROUTER`, `LASER`, `ARTWORK_REFERENCE`, `INSTALLATION`, or `OTHER`.

Status is `DRAFT`, `REVIEW`, `APPROVED_FOR_PRODUCTION`, `SUPERSEDED`, or `REJECTED`. Customer approval of a proof is not the same as `APPROVED_FOR_PRODUCTION`. A print, CNC, or router stage blocks release until a file of that category is approved for production.

`colour_references` can store Pantone, CMYK, RGB, RAL, a vinyl code, a paint code, or a brand specification, with source `CUSTOMER_ARTWORK`, `BRAND_GUIDELINE`, `MATERIAL_CODE`, or `MANUAL`. RGB on a screen is not a claim about the printed colour. There is no colour screen in this release. Rows are stored for the job.

## Planning date

`ProductionPlanningService::latestStart` walks backward through the working calendar (`BusinessTimeService`, including holidays) from the required date. Each named step is a number of days, and a day is 480 minutes. Half a day is allowed.

The result is labelled `PLANNING DATE`. It is not a promise and it does not move other jobs. If that date is before today, the job is marked at risk and the reason names the required date.

## Health and watchdog

Job health is `ON_TRACK`, `AT_RISK`, `OVERDUE`, or `BLOCKED`. The reasons are sentences: not released, release review required, a blocking check, a material shortfall, a failed QC check, a passed internal target date, or a blocked stage. There is no single risk score.

On-time in this health check is the internal `target_date`. `original_target_date` and `customer_promised_date` stay on the job and are not replaced by the planning date.

First-pass yield is `good / (good + rework)`. Waste is not in the denominator. If good and rework are both zero, yield is blank.

The hourly cron counts overdue released jobs, blocked stages, and jobs that are not released. It prints `production_overdue=`. It does not score each job.

## Projects, assets, and specifications

A project does not get its own release status. Release each job, or pass the wave’s job ids to bulk release. A blocked site stays unreleased.

An asset is still created only when someone confirms it. If the job item has an open fulfilment row, asset creation waits until that fulfilment is fulfilled or cancelled. A job item with no fulfilment row keeps the previous behaviour.

The release snapshot copies `jobs.technical_snapshot_json`. Approving a newer specification does not change a released job’s snapshot.
