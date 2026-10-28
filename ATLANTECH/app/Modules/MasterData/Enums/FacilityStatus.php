<?php

namespace App\Modules\MasterData\Enums;

/**
 * `berths.status`, `storage_facilities.status`.
 */
enum FacilityStatus: string
{
    case Active = 'ACTIVE';
    case Maintenance = 'MAINTENANCE';
    case Inactive = 'INACTIVE';
}
