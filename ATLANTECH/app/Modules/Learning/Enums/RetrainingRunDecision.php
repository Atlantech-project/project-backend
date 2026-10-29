<?php

namespace App\Modules\Learning\Enums;

/**
 * `retraining_runs.decision`.
 */
enum RetrainingRunDecision: string
{
    case Promoted = 'PROMOTED';
    case KeptChampion = 'KEPT_CHAMPION';
    case Failed = 'FAILED';
}
