<?php

namespace App\Modules\Execution\Enums;

/**
 * `actual_status_updates.sync_source`.
 */
enum ActualStatusUpdateSyncSource: string
{
    case Mobile = 'MOBILE';
    case Tablet = 'TABLET';
    case Web = 'WEB';
    case External = 'EXTERNAL';
}
