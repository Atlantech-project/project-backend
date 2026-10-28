<?php

namespace App\Modules\Learning\Enums;

/**
 * `evaluation_metrics.dataset_type`.
 */
enum EvaluationMetricDatasetType: string
{
    case Holdout = 'HOLDOUT';
    case Rolling = 'ROLLING';
    case Production = 'PRODUCTION';
}
