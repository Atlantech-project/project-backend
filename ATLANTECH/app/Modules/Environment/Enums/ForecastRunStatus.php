<?php

namespace App\Modules\Environment\Enums;

/**
 * `forecast_runs.status`.
 */
enum ForecastRunStatus: string
{
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
}
