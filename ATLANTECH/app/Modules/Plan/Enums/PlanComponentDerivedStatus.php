<?php

namespace App\Modules\Plan\Enums;

/**
 * `plan_components.derived_status`: not a column. The value is read from the assignment and the latest
 * `actual_status_updates` row (CT-07, CT-21).
 *
 * @derived
 */
enum PlanComponentDerivedStatus: string
{
    case Unassigned = 'UNASSIGNED';
    case Assigned = 'ASSIGNED';
    case Started = 'STARTED';
    case Done = 'DONE';
}
