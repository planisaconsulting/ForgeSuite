# Lightbox estimator

Supports a single-sided or double-sided rectangular box, including ACM, acrylic, flexface, and a fabricated box described by the specification. Wall-mounted and freestanding are recorded as mounting notes. The estimator does not design the wall or the footing.

## Inputs

Width, height, depth, number of faces (1 or 2), specification, face, back, and return materials, illumination, mounting, finish, and quantity. Units may be mm, cm, or m.

## Geometry

Face area is width times height times faces times quantity. A double-sided 2,400 × 1,200 mm box is 5.76 m² of face.

The frame is the outer perimeter, twice width plus twice height, times quantity. The same box is 7.2 m of frame. The cut list is two lengths of the width and two of the height per unit. Braces are added only when the specification or the input gives a brace count. No bracing rule is invented.

Returns are perimeter times depth times quantity. At 200 mm deep that box is 1.44 m² of return.

If width or height exceeds the specification maximum, the calculation is marked outside the specification.

## Illumination

LED modules and power supplies use the selected profiles, as in the channel-letter estimator. `LED-MOD-12` is 25 modules per square metre at 1.2 W, so 5.76 m² is 144 modules. `PSU-100-80` is a 100 W supply planned at 80 percent. Illumination sets electrical review. The electrical disclaimer is an estimate of components, not a certificate.

## Labour

Cutting, frame fabrication, face preparation, painting, electrical, assembly, QC, and installation come from the specification labour rows and the existing installation estimator. The route follows the specification operations. `LBX-ACM-001` uses cut, fabricate, paint, electrical, assembly, QC, pack, and install.
