<?php

namespace App\Modules\MasterData\Enums;

/**
 * `capability_types.unit`.
 */
enum CapabilityTypeUnit: string
{
    case Tonnes = 'TONNES';
    case Metres = 'METRES';
    case TonnesPerHour = 'TONNES_PER_HOUR';
    case CubicMetresPerHour = 'CUBIC_METRES_PER_HOUR';
    case MovesPerHour = 'MOVES_PER_HOUR';
    case Count = 'COUNT';
    case Bar = 'BAR';
}
