<?php

namespace App\Modules\MasterData\Enums;

/**
 * `cargo_types.category`.
 */
enum CargoTypeCategory: string
{
    case GeneralCargo = 'GENERAL_CARGO';
    case BreakBulk = 'BREAK_BULK';
    case DryBulk = 'DRY_BULK';
    case LiquidBulk = 'LIQUID_BULK';
    case Gas = 'GAS';
    case RoRo = 'RO_RO';
    case Containerized = 'CONTAINERIZED';
}
