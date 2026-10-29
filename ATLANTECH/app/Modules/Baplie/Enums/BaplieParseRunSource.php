<?php

namespace App\Modules\Baplie\Enums;

/**
 * `baplie_parse_runs.source`.
 */
enum BaplieParseRunSource: string
{
    case File = 'FILE';
    case StagedPayload = 'STAGED_PAYLOAD';
}
