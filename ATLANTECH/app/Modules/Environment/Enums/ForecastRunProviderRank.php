<?php

namespace App\Modules\Environment\Enums;

/**
 * `forecast_runs.provider_rank`.
 */
enum ForecastRunProviderRank: string
{
    case Primary = 'PRIMARY';
    case Secondary = 'SECONDARY';
    case Manual = 'MANUAL';
}
