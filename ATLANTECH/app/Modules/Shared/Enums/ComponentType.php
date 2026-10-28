<?php

namespace App\Modules\Shared\Enums;

/**
 * `actual_status_updates.component_type`, `plan_components.component_type`, `status_types.component_type`.
 */
enum ComponentType: string
{
    case Berth = 'BERTH';
    case Pilot = 'PILOT';
    case Tug = 'TUG';
    case TerminalOps = 'TERMINAL_OPS';
}
