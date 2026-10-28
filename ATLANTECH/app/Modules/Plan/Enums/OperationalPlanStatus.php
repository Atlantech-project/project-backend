<?php

namespace App\Modules\Plan\Enums;

/**
 * `operational_plans.status`.
 */
enum OperationalPlanStatus: string
{
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
    case Completed = 'COMPLETED';
}
