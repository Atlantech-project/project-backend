<?php

namespace App\Modules\Environment\Enums;

/**
 * `forecast_runs.pull_type`.
 */
enum ForecastRunPullType: string
{
    case Scheduled = 'SCHEDULED';
    case OnDemand = 'ON_DEMAND';
    case Manual = 'MANUAL';
}
