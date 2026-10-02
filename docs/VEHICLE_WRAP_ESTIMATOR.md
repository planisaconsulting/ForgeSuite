# Vehicle wrap estimator

Open Estimating → Advanced estimating → Vehicle wrap.

1. Choose a vehicle template or enter panels yourself.
2. Tick the panels.
3. Choose coverage: decals, door branding, partial wrap, half wrap, full wrap, roof, bonnet, tailgate, canopy, or custom.
4. Set the selected roll width. The default comparison widths are 1,050 mm, 1,370 mm, and 1,520 mm.
5. Add laminate if the job needs it.
6. Set installation complexity: standard, moderate, complex, or specialist.
7. Calculate.

## Panels

Panel codes include the four doors, bonnet, roof, tailgate, canopy sides and rear, full sides, bumpers, and custom. A verified template supplies width and height, so you do not type the area. The seeded example is a 2017 Ford Ranger Double Cab, source `MANUAL`, marked verified, with door, tailgate, and canopy sizes. It is an example for estimating. It is not a licensed template library, and no template artwork file is shipped.

If the template is missing, type the panel sizes. Saving that measurement as a new custom template is not on this screen yet.

## Measurement source

The calculation records verified template, supplier template, customer measurement, site survey, or manual estimate. An unverified source sets technical review and a warning. The screen does not treat the template as exact when `verified` is off.

## Material

For each selected panel, print size is the panel plus bleed and overlap on every side. `RollYieldService` lays that rectangle on the selected roll. Linear metres and consumed area are the sum of those layouts. They are not the graphic area divided by a roll width.

The other roll widths are compared and recommended. The selected roll stays selected.

Laminate quantity follows the consumed area of the selected layout, including unused width on that layout. It does not follow graphic area alone.

## Labour

Hours come from settings: `wrap_hours_standard` 1.5, moderate 2.5, complex 4, specialist 6, multiplied by `wrap_factor_standard` 1, moderate 1.25, complex 1.50, and specialist 2. Existing vinyl removal uses `wrap_removal_hours` (default 1) and is labelled as an estimate. Surface preparation is a chosen labour and material allowance, not a guessed percentage. Access equipment uses `access_cost_ladder`, `access_cost_scaffold`, `access_cost_cherry_picker`, `access_cost_crane`, and `access_cost_other`. Travel uses the existing distance and rate. The trace names the setting.

## Output

The result shows the vehicle, panels, graphic area, print area, roll consumption, laminate, waste, print, lamination and cutting time where machine rates exist, installation hours, material cost, labour cost, total cost, selling price, gross profit, and margin. The schematic is labelled estimating schematic, not for fabrication.
