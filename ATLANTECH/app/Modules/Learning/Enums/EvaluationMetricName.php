<?php

namespace App\Modules\Learning\Enums;

/**
 * `evaluation_metrics.metric_name`.
 */
enum EvaluationMetricName: string
{
    case Mae = 'MAE';
    case OverrideRate = 'OVERRIDE_RATE';
    case WaitTime = 'WAIT_TIME';
    case WindowAccuracy = 'WINDOW_ACCURACY';
}
