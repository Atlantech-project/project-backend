<?php

namespace App\Modules\MasterData\Enums;

/**
 * `constraint_types.unit`.
 */
enum ConstraintTypeUnit: string
{
    case Knots = 'KNOTS';
    case MetresPerSecond = 'METRES_PER_SECOND';
    case Metres = 'METRES';
    case NauticalMiles = 'NAUTICAL_MILES';
    case Percent = 'PERCENT';
    case Degrees = 'DEGREES';
    case Minutes = 'MINUTES';
    case Tonnes = 'TONNES';
}
