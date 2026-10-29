<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `demand_forecast_slots.method`.
 */
enum DemandForecastSlotMethod: string
{
    case Aggregation = 'AGGREGATION';
    case Lightgbm = 'LIGHTGBM';
}
