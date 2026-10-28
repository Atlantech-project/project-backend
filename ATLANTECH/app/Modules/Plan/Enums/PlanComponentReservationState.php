<?php

namespace App\Modules\Plan\Enums;

/**
 * `plan_components.reservation_state`.
 */
enum PlanComponentReservationState: string
{
    case Reserved = 'RESERVED';
    case Released = 'RELEASED';
    case Consumed = 'CONSUMED';
}
