<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * How a product is measured and costed.
 *
 * The calculator (next build) must show only the inputs this method needs.
 * AREA and SHEET are different: a sheet product can still store a sheet size
 * while an area product is priced per square metre.
 */
enum PricingMethod: string
{
    case Area = 'AREA';
    case LinearMetre = 'LINEAR_METRE';
    case Unit = 'UNIT';
    case Sheet = 'SHEET';
    case Litre = 'LITRE';
    case Hour = 'HOUR';
    case Custom = 'CUSTOM';

    public function label(): string
    {
        return match ($this) {
            self::Area => 'Area (m²)',
            self::LinearMetre => 'Linear metre',
            self::Unit => 'Unit',
            self::Sheet => 'Sheet',
            self::Litre => 'Litre',
            self::Hour => 'Hour',
            self::Custom => 'Custom',
        };
    }
}
