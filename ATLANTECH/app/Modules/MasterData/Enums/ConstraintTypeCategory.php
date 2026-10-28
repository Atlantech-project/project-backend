<?php

namespace App\Modules\MasterData\Enums;

/**
 * `constraint_types.category`.
 */
enum ConstraintTypeCategory: string
{
    case Environmental = 'ENVIRONMENTAL';
    case Ukc = 'UKC';
    case VesselSafety = 'VESSEL_SAFETY';
}
