<?php

namespace App\Modules\Shared\Enums;

/**
 * `storage_facilities.terminal_type`, `terminal_productivity_profiles.terminal_type`, `terminal_recommendations.terminal_type`, `terminals.terminal_type`.
 */
enum TerminalType: string
{
    case Container = 'CONTAINER';
    case NonContainer = 'NON_CONTAINER';
}
