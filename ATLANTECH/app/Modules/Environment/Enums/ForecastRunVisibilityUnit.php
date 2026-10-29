<?php

namespace App\Modules\Environment\Enums;

/**
 * `forecast_runs.visibility_unit`.
 */
enum ForecastRunVisibilityUnit: string
{
    case NauticalMiles = 'NAUTICAL_MILES';
    case Kilometres = 'KILOMETRES';
    case Metres = 'METRES';
}
