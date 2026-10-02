# Estimator safety limits

These tools estimate manufacturing quantities. They do not certify:

- structural engineering
- wind load
- electrical installation
- building regulations
- fire engineering
- foundation engineering

Where a specification or a rule requires it, the calculation is flagged `ENGINEERING REVIEW REQUIRED` or electrical review. The wording is fixed in `EngineeringLimits`:

- Structural: a manufacturing estimate is not structural, wind-load, foundation, fire, or building-regulation certification.
- Electrical: estimated component requirement. Final electrical design and installation must be verified by qualified personnel where required.
- Power supplies: verify the final electrical design before production.
- Schematics: estimating schematic, not for fabrication.

## What is calculated

Connected LED load, a planning count of power supplies from the profile load percent, sheet and roll yield, labour from formulas and settings, and selling price from the company margin. Cable size, breaker size, and certified footing sizes are not calculated.

## What the parser will not do

- Run SVG script or event handlers
- Treat a raster image as geometry
- Guess arc commands
- Accept a file over the configured byte or path limit
- Cache a price inside the geometry cache

## What a person must not treat as certified

Estimated weight, when thickness and density both exist on the product, is for handling and transport. It is labelled estimated weight. Unknown density is not assumed.

A comparison of two options shows cost, sell, weight, material, waste, and labour. It does not name a winner.

Historical variance can say that completed jobs used more labour than the estimate. It does not change the formula.

A photograph does not supply dimensions. Site survey measurements can be typed into an estimate after a person confirms them.
