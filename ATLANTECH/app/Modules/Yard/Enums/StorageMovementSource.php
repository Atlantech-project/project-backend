<?php

namespace App\Modules\Yard\Enums;

/**
 * `storage_movements.source`.
 */
enum StorageMovementSource: string
{
    case VesselDischarge = 'VESSEL_DISCHARGE';
    case VesselLoading = 'VESSEL_LOADING';
    case Manual = 'MANUAL';
}
