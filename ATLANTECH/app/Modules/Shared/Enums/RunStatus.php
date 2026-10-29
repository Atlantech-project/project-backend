<?php

namespace App\Modules\Shared\Enums;

/**
 * `baplie_parse_runs.status`, `part1_runs.status`, `yard_runs.status`.
 */
enum RunStatus: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Done = 'DONE';
    case Failed = 'FAILED';
    case Replaced = 'REPLACED';
}
