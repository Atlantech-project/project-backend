<?php

namespace App\Modules\Berthing\Enums;

/**
 * `berthing_requests.source`.
 */
enum BerthingRequestSource: string
{
    case Manual = 'MANUAL';
    case External = 'EXTERNAL';
}
