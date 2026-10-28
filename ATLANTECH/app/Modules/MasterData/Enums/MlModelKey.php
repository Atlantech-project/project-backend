<?php

namespace App\Modules\MasterData\Enums;

/**
 * `ml_models.model_key`.
 */
enum MlModelKey: string
{
    case M2ServiceTime = 'M2_SERVICE_TIME';
    case M3DemandForecast = 'M3_DEMAND_FORECAST';
    case M4DelayRisk = 'M4_DELAY_RISK';
    case ForecastBiasCorrection = 'FORECAST_BIAS_CORRECTION';
}
