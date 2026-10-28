<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `solver_runs.status`.
 */
enum SolverRunStatus: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Optimal = 'OPTIMAL';
    case Feasible = 'FEASIBLE';
    case Infeasible = 'INFEASIBLE';
    case Error = 'ERROR';
}
