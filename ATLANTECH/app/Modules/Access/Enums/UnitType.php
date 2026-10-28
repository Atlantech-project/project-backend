<?php

namespace App\Modules\Access\Enums;

/**
 * `organization_units.unit_type`, `user_role_assignments.unit_type`.
 */
enum UnitType: string
{
    case HarbourAuthority = 'HARBOUR_AUTHORITY';
    case MarineServices = 'MARINE_SERVICES';
    case Terminal = 'TERMINAL';
}
