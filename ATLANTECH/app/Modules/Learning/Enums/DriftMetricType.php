<?php

namespace App\Modules\Learning\Enums;

/**
 * `drift_measurements.metric_type`.
 */
enum DriftMetricType: string
{
    case Psi = 'PSI';
    case RollingMae = 'ROLLING_MAE';
}
