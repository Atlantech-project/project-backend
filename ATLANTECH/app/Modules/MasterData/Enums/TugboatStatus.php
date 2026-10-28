<?php

namespace App\Modules\MasterData\Enums;

/**
 * `tugboats.status`.
 */
enum TugboatStatus: string
{
    case Available = 'AVAILABLE';
    case Busy = 'BUSY';
    case Maintenance = 'MAINTENANCE';
    case Inactive = 'INACTIVE';
}
