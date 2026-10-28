<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `solver_runs.trigger_type`.
 */
enum SolverRunTriggerType: string
{
    case NewRequest = 'NEW_REQUEST';
    case Replan = 'REPLAN';
    case ResourceChange = 'RESOURCE_CHANGE';
    case Manual = 'MANUAL';
}
