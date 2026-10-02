<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Manufacturing estimates stop short of professional certification.
 */
final class EngineeringLimits
{
    public const STRUCTURAL = 'ENGINEERING REVIEW REQUIRED. This is a manufacturing estimate. It is not structural, wind-load, foundation, fire, or building-regulation certification.';

    public const ELECTRICAL = 'ESTIMATED COMPONENT REQUIREMENT — FINAL ELECTRICAL DESIGN/INSTALLATION MUST BE VERIFIED BY APPROPRIATELY QUALIFIED PERSONNEL WHERE REQUIRED.';

    public const SCHEMATIC = 'ESTIMATING SCHEMATIC — NOT FOR FABRICATION';

    public const VERIFY_ELECTRICAL = 'VERIFY FINAL ELECTRICAL DESIGN BEFORE PRODUCTION.';
}
