<?php

namespace App\Modules\MasterData\Enums;

/**
 * `terminal_productivity_profiles.rate_unit`.
 */
enum TerminalProductivityProfileRateUnit: string
{
    case MovesPerHour = 'MOVES_PER_HOUR';
    case TonnesPerHour = 'TONNES_PER_HOUR';
    case CubicMetresPerHour = 'CUBIC_METRES_PER_HOUR';
    case UnitsPerHour = 'UNITS_PER_HOUR';
}
