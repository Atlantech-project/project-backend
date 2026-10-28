<?php

namespace App\Modules\Execution\Enums;

/**
 * `deviation_logs.deviation_type`.
 */
enum DeviationType: string
{
    case Delay = 'DELAY';
    case ResourceChanged = 'RESOURCE_CHANGED';
    case StateMismatch = 'STATE_MISMATCH';
}
