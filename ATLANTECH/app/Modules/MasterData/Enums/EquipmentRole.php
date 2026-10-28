<?php

namespace App\Modules\MasterData\Enums;

/**
 * `equipment_categories.equipment_role`.
 */
enum EquipmentRole: string
{
    case VesselHandling = 'VESSEL_HANDLING';
    case CargoTransport = 'CARGO_TRANSPORT';
    case TransferSystem = 'TRANSFER_SYSTEM';
}
