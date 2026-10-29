<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `part1_runs.outcome`.
 */
enum Part1RunOutcome: string
{
    case WindowsFound = 'WINDOWS_FOUND';
    case NoFeasibleWindow = 'NO_FEASIBLE_WINDOW';
    case EnvDataUnavailable = 'ENV_DATA_UNAVAILABLE';
}
