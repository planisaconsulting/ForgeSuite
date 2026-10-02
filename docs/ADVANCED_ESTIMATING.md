# Advanced estimating

Estimating → Advanced estimating runs a manufacturing calculation and then the existing estimate, yield, and pricing services. The browser does not send the cost. Selling price uses the company target margin through `PricingMathService`, the same rule as other internal estimates.

The flow is estimator, calculation, review, estimate, quote. A result is not a quote line until someone adds it.

## Estimators

- Vehicle wrap
- Channel letters
- Lightbox
- Pylon
- Panel and frame

Each one returns geometry, materials, components, labour, machine time where a route supplies it, yield, warnings, review flags, and a trace. The trace shows the input, the method, the intermediate figures, and the quantity.

What-if calculates and does not save. Saving writes `sign_calculations` and an internal estimate. A later change creates a new calculation. A quoted or converted calculation is not edited in place.

## Units

Enter mm, cm, or m. The server converts to millimetres. Area is square metres, length is metres, and power is watts. Negative dimensions and a zero quantity are rejected. A dimension above `dimension_warn_mm` (default 8,000) is kept and warned. It is not corrected.

## Compatibility

Rules are data: requires, excludes, requires one of, allows only, minimum and maximum value or quantity, warning, engineering review, and electrical review. There is no `eval`. A hard failure explains the combination. A soft rule warns. Overriding a hard rule needs `estimators.override` and a reason. The original message stays on the calculation.

An illuminated checkbox is stored as `YES`. Choosing an LED profile records that electrical components are included, which satisfies a rule that requires them.

## Price, stock, and overrides

Material quantity is the manufacturing quantity. Product standard waste is not applied a second time. Stock on hand and offcut suggestions do not change the quantity. Offcuts are not consumed.

An authorised override stores the calculated quantity, the new quantity, the user, the reason, and the time. Cost is recalculated from the product snapshot. The audit event is `ESTIMATE_OVERRIDE`.

## Quote, job, asset, and project

Add to quote is blocked while the calculation is in technical review. The customer line is the description, dimensions, quantity, and price. It does not include the bill of materials, waste, supplier cost, labour rates, or margin. The internal calculation keeps those.

Accepting the quote copies the specification version, inputs, trace, route, and warnings onto the job. Production does not recalculate from a newer specification. An asset created from that job copies the specification id and a short technical snapshot.

A rollout applies one lightbox specification to many sites. Sites outside the specification width or height are listed as exceptions. Accepted jobs are not rewritten. There is no separate action that pushes a new specification version onto selected drafts.

## Reports

Estimate versus actual is under Advanced estimating. It sums material and labour on completed jobs that have a sign calculation. Fewer than three completed jobs does not change a labour formula and does not show a confidence score. The same report supplies usage by estimator type, review count, outside-specification count, and the most used specification codes.

## Events

`SPECIFICATION_CREATED`, `SPECIFICATION_APPROVED`, `SPECIFICATION_SUPERSEDED`, `ESTIMATE_CALCULATED`, `ESTIMATE_OVERRIDE`, `TECHNICAL_REVIEW_REQUIRED`, `TECHNICAL_REVIEW_COMPLETED`, and `GEOMETRY_ANALYSED` when a channel-letter calculation is saved. `VEHICLE_TEMPLATE_CREATED` is reserved for a later template editor. The seeded Ford Ranger template does not emit it.
