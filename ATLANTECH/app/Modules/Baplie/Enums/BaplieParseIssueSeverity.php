<?php

namespace App\Modules\Baplie\Enums;

/**
 * `baplie_parse_issues.severity`.
 */
enum BaplieParseIssueSeverity: string
{
    case Error = 'ERROR';
    case Warning = 'WARNING';
}
