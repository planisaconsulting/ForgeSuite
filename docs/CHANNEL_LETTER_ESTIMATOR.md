# Channel letter estimator

Use this for flat-cut, built-up, channel, illuminated channel, halo, and combination letters. The geometry must be a vector. A photograph is documentation only and is not measured.

## File

Upload SVG. User units are millimetres. Supported commands are M, L, H, V, C, Q, and Z, absolute or relative. Axis-aligned lines are exact. Other straight lines use a square root. Curves are flattened to 16 segments, which is an estimate of length, not a certified curve length. Arcs (`A`) and shorthand curves (`S`, `T`) stop the calculation with `MANUAL GEOMETRY REVIEW REQUIRED`. DXF and PDF are not parsed.

Script, `foreignObject`, event handlers, and `javascript:` are removed. The preview is a generated outline, not the uploaded file. A file larger than `geometry_max_bytes` (default 262,144) or with more than `geometry_max_paths` (default 200) is rejected.

An open path is warned `OPEN VECTOR PATH DETECTED` and is left out of closed area and perimeter. If nothing is closed, face and return quantities are not calculated. A repeated path is counted once and warned.

Geometry may be cached by parser version and path hash. The cache stores geometry only, not prices. Parser version is `2`.

The saved calculation stores a hash and the byte size of the upload. The raw SVG is not kept in the input snapshot. `geometry_analyses` stores the sanitised preview, area, perimeter, and warnings.

## Quantities

Nominal height scales the vector: linear dimensions by nominal height divided by the bounding height, and area by the square of that scale.

Face and back use closed area, the manufacturing allowance, and the quantity. Return length is closed perimeter, the same allowance, and the quantity. Strip area is that length times return depth. Trim cap uses the same length when trim cap is selected. LED quantity uses the vector area before the manufacturing allowance.

If a sheet size is entered, the sheet count uses a square of the same face area through `SheetYieldService`. The result warns that this is not a true letter nest.

## LED and power supplies

Module count uses the selected profile: modules per square metre, grid spacing, or a per-letter count. Spacing is never invented. Connected load is modules times module watts. A 100-module profile at 1.2 W is 120 W.

Usable planning capacity is rated watts times the profile load percent. 80 percent is not hard-coded. A 100 W supply at 80 percent plans 80 W, so 120 W needs two supplies. The result says to verify the final electrical design before production. Cable size and circuit protection are not calculated.

## Labour and route

Specification labour uses FormulaService with `W`, `H`, `D`, `Q`, `AREA`, `PERIMETER`, `FACE_AREA`, `RETURN_DEPTH`, `VECTOR_AREA`, `VECTOR_PERIMETER`, `SIDES`, and `MODULE_WATTS`. The suggested route is CNC, fabricate, paint, electrical, assemble, QC, and install. Machine time uses the existing machine estimate service when the specification has operations.
