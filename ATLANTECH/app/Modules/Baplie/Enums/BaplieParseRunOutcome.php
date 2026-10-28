<?php

namespace App\Modules\Baplie\Enums;

/**
 * `baplie_parse_runs.outcome`.
 */
enum BaplieParseRunOutcome: string
{
    case Parsed = 'PARSED';
    case Quarantined = 'QUARANTINED';
    case Rejected = 'REJECTED';
}
