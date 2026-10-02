# Sign specifications

A sign specification is a versioned manufacturing default. It is not a structural certificate and it is not an electrical certificate.

Open Specifications from Estimating. The list shows code, name, category, version, status, and effective date. Search matches code, name, and category. Filters cover status, category, estimator type, approved only, and engineering review.

## Status

`DRAFT`, `REVIEW`, `APPROVED`, `SUPERSEDED`, `ARCHIVED`.

A normal estimate offers an approved specification that has not been superseded. A user with `specifications.edit` can still calculate against another status. Sales cannot approve a specification.

## Versioning

Approving a new version marks the previous approved row `SUPERSEDED` and sets `superseded_by_id`. The old row keeps its approval time. Quotes, jobs, and assets keep the specification id and version they were given. Changing the live specification does not rewrite them.

Revise copies materials, components, labour, operations, rules, and notes onto a new draft. Edit only a draft or a row in review.

## What a specification can hold

- Construction notes, frame, face, return, illumination, fasteners, mounting, and finish
- Material rows with a product, thickness, unit, and waste percent
- Component rows, including an electrical profile
- Labour rows whose minutes come from FormulaService
- Production operations
- Compatibility rules
- QC, installation, and warning notes
- A default recipe and a default production route

Thickness comes from the product or the specification row. The name `3 mm ACM` is not parsed.

## Seeded examples

`LBX-ACM-001` version 1 is an approved standard ACM illuminated lightbox. Illumination requires electrical components. Electrical review is required. The manufacturing allowance is 5 percent.

`PYL-STD-001` version 1 is an approved pylon specification. Engineering review is required. Height above 6,000 mm, the threshold stored on that specification, asks for engineering review. Face material `CORREX` is a hard exclusion. The message names the material and the specification.

## Images and documents

`specification_images` can mark a file `INTERNAL` or `CUSTOMER_VISIBLE`. The table is in place. There is no image upload screen in this release. Attach supplier sheets through the existing document store when you need a file on a job.
