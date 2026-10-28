<?php

namespace App\Modules\MasterData\Enums;

/**
 * `pilot_profiles.pilot_rank`, `pilot_rank_limits.pilot_rank`.
 */
enum PilotRank: string
{
    case First = 'FIRST';
    case Second = 'SECOND';
    case Third = 'THIRD';
}
