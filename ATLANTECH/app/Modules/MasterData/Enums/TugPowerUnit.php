<?php

namespace App\Modules\MasterData\Enums;

/**
 * `tugboats.tug_power_unit`.
 */
enum TugPowerUnit: string
{
    case BollardPullTonnes = 'BOLLARD_PULL_TONNES';
    case Hp = 'HP';
    case Kw = 'KW';
}
