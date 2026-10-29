<?php

namespace App\Modules\Baplie\Enums;

/**
 * `baplie_files.parse_status`.
 */
enum BaplieFileParseStatus: string
{
    case Pending = 'PENDING';
    case Parsed = 'PARSED';
    case Failed = 'FAILED';
    case Quarantined = 'QUARANTINED';
}
