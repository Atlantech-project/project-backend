<?php

namespace App\Modules\Environment\Enums;

/**
 * `forecast_runs.wind_speed_unit`, `forecast_runs.current_speed_unit`.
 */
enum SpeedUnit: string
{
    case Knots = 'KNOTS';
    case MetresPerSecond = 'METRES_PER_SECOND';
    case KmPerHour = 'KM_PER_HOUR';
}
