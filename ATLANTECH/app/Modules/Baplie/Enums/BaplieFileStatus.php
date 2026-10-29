<?php

namespace App\Modules\Baplie\Enums;

/**
 * `baplie_files.status`.
 */
enum BaplieFileStatus: string
{
    case Staged = 'STAGED';
    case Active = 'ACTIVE';
    case Replaced = 'REPLACED';
    case Withdrawn = 'WITHDRAWN';
}
